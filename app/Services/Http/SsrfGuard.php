<?php

namespace App\Services\Http;

use App\Exceptions\Http\BlockedUrlException;

/**
 * Guards outbound-HTTP call sites (Call API, workspace notification
 * channels, polling triggers: anything that fetches a tenant-supplied URL)
 * against SSRF: a tenant is any authenticated user, and without this a "make
 * an HTTP request" feature lets them make the server fetch cloud metadata
 * endpoints or internal-network services on their behalf.
 *
 * Call sites should go through {@see GuardedHttp}, which re-validates every
 * redirect hop and pins the connection to the addresses validated here so a
 * DNS answer that changes between the check and the connect (DNS rebinding)
 * cannot reach a blocked address.
 */
class SsrfGuard
{
    /**
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Special-purpose IPv4 ranges that are never a legitimate public target.
     * PHP's `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` misses
     * several of these (carrier-grade NAT, benchmarking, multicast), so the
     * table is explicit. The TEST-NET documentation ranges are left out on
     * purpose: they are unrouted, and tests use them as stand-in public hosts.
     *
     * @var list<string>
     */
    private const BLOCKED_IPV4_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /**
     * @var list<string>
     */
    private const BLOCKED_IPV6_RANGES = [
        '::/128',
        '::1/128',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    /**
     * @var callable(string): list<string>
     */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Resolves a
     *                                                           hostname to its IP addresses. Defaults to a real DNS lookup; tests
     *                                                           substitute a fake so assertions don't depend on outbound network
     *                                                           access being available.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? $this->dnsResolve(...);
    }

    /**
     * Full check, including DNS: every address the host resolves to must be
     * public.
     */
    public function assertUrlIsAllowed(string $url): void
    {
        $this->resolve($url);
    }

    /**
     * The same check as {@see self::assertUrlIsAllowed()} but without a DNS
     * lookup: scheme, credentials, literal IPs and obviously-internal host
     * names only. Cheap enough for request validation, where a slow or
     * unavailable resolver must not block saving a form; the runtime check
     * still enforces the rest.
     */
    public function assertUrlSyntaxIsAllowed(string $url): void
    {
        $this->parse($url);
    }

    /**
     * Validates the URL and returns the addresses it may be connected to.
     * Callers pin the connection to these (see {@see GuardedHttp}) instead
     * of letting the HTTP client resolve the host a second time.
     *
     * @return array{host: string, port: int, ips: list<string>, pinnable: bool}
     */
    public function resolve(string $url): array
    {
        ['host' => $host, 'port' => $port] = $this->parse($url);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return ['host' => $host, 'port' => $port, 'ips' => [$host], 'pinnable' => false];
        }

        $ips = ($this->resolver)($host);

        if ($ips === []) {
            throw BlockedUrlException::forUrl($url, "the host \"{$host}\" could not be resolved.");
        }

        foreach ($ips as $ip) {
            $this->assertIpIsAllowed($ip, $url);
        }

        return ['host' => $host, 'port' => $port, 'ips' => $ips, 'pinnable' => true];
    }

    /**
     * @return array{host: string, port: int}
     */
    private function parse(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw BlockedUrlException::forUrl($url, 'the URL could not be parsed.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw BlockedUrlException::forUrl($url, "the \"{$scheme}\" scheme is not allowed.");
        }

        // `http://public.example@10.0.0.1/` reads as one host to a human and
        // another to a naive filter; there is no legitimate reason to send
        // credentials in the URL, so refuse the ambiguity outright.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw BlockedUrlException::forUrl($url, 'credentials in the URL are not allowed.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($isIp) {
            $this->assertIpIsAllowed($host, $url);
        } else {
            $this->assertHostNameIsAllowed($host, $url);
        }

        return [
            'host' => $host,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
        ];
    }

    private function assertHostNameIsAllowed(string $host, string $url): void
    {
        // Decimal (2130706433), octal (0177.0.0.1) and hex (0x7f.1) spellings
        // of an IPv4 address are accepted by the system resolver and by curl
        // but rejected by FILTER_VALIDATE_IP, so they would slip through as
        // "just a hostname". No real DNS name looks like this.
        if (preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+))*$/', $host) === 1) {
            throw BlockedUrlException::forUrl($url, "the host \"{$host}\" is an ambiguous IP address notation.");
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw BlockedUrlException::forUrl($url, "the host \"{$host}\" is not a public host name.");
        }
    }

    /**
     * @return list<string>
     */
    private function dnsResolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    private function assertIpIsAllowed(string $ip, string $url): void
    {
        if (! $this->isPublicIp($ip)) {
            throw BlockedUrlException::forUrl($url, "the host resolves to a non-public address ({$ip}).");
        }
    }

    private function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            return ! $this->isInAnyRange($packed, self::BLOCKED_IPV4_RANGES);
        }

        // IPv6 forms that smuggle an IPv4 address: judge them by the address
        // they carry, otherwise `::ffff:127.0.0.1` is a loopback bypass.
        $embedded = $this->embeddedIpv4($packed);

        if ($embedded !== null) {
            return $this->isPublicIp($embedded);
        }

        return ! $this->isInAnyRange($packed, self::BLOCKED_IPV6_RANGES);
    }

    /**
     * IPv4-mapped (`::ffff:a.b.c.d`), IPv4-compatible (`::a.b.c.d`), NAT64
     * (`64:ff9b::a.b.c.d`) and 6to4 (`2002:aabb:ccdd::`) addresses.
     */
    private function embeddedIpv4(string $packed): ?string
    {
        $isMapped = str_starts_with($packed, str_repeat("\0", 10)."\xff\xff");
        $isCompatible = str_starts_with($packed, str_repeat("\0", 12)) && substr($packed, 12) !== "\0\0\0\0" && substr($packed, 12) !== "\0\0\0\x01";
        $isNat64 = str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\0", 8));

        if ($isMapped || $isCompatible || $isNat64) {
            return inet_ntop(substr($packed, 12)) ?: null;
        }

        if (str_starts_with($packed, "\x20\x02")) {
            return inet_ntop(substr($packed, 2, 4)) ?: null;
        }

        return null;
    }

    /**
     * @param  list<string>  $ranges
     */
    private function isInAnyRange(string $packedIp, array $ranges): bool
    {
        foreach ($ranges as $range) {
            [$subnet, $bits] = explode('/', $range);
            $packedSubnet = inet_pton($subnet);

            if ($packedSubnet === false || strlen($packedSubnet) !== strlen($packedIp)) {
                continue;
            }

            if ($this->matchesPrefix($packedIp, $packedSubnet, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPrefix(string $packedIp, string $packedSubnet, int $bits): bool
    {
        $wholeBytes = intdiv($bits, 8);

        if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedSubnet, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedSubnet[$wholeBytes]) & $mask);
    }
}
