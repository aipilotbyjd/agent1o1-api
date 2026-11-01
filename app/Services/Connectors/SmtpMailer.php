<?php

namespace App\Services\Connectors;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Talks to the mail server a member connected, from the settings stored on the
 * account (host, port, encryption, login). The one place a connection to it is
 * made, so the Email nodes and the connection check can't disagree about how.
 */
class SmtpMailer
{
    /**
     * An SMTP connection to the account's server (not opened until used).
     *
     * @param  array<string, mixed>  $account
     *
     * @throws InvalidArgumentException when the settings are incomplete
     */
    public function transport(array $account): EsmtpTransport
    {
        $host = trim((string) ($account['host'] ?? ''));
        $port = (int) ($account['port'] ?? 0);

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('The SMTP account needs a host and a port. Edit the connection in Apps.');
        }

        $encryption = strtolower(trim((string) ($account['encryption'] ?? '')));

        // "ssl" is a connection that is encrypted from the first byte (port 465). "tls" asks
        // the server to upgrade (STARTTLS, port 587). With nothing chosen the port decides.
        $transport = new EsmtpTransport($host, $port, match ($encryption) {
            'ssl' => true,
            'tls', 'none' => false,
            default => null,
        });

        if ($encryption === 'none') {
            $transport->setAutoTls(false);
        }

        $username = (string) ($account['username'] ?? '');

        if ($username !== '') {
            $transport->setUsername($username);
            $transport->setPassword((string) ($account['api_key'] ?? ''));
        }

        return $transport;
    }

    /**
     * Open a connection to the server and log in, without sending anything.
     *
     * @param  array<string, mixed>  $account
     *
     * @throws InvalidArgumentException when the settings are incomplete
     * @throws Throwable when the server can't be reached or refuses the login
     */
    public function verify(array $account): void
    {
        $transport = $this->transport($account);

        if (is_callable([$transport, 'start'])) {
            try {
                $transport->start();
            } finally {
                try {
                    $transport->stop();
                } catch (Throwable) {
                    // Closing a connection that is already gone is not a failure.
                }
            }

            return;
        }

        // Without a way to log in, at least prove the server answers on its port.
        $socket = @fsockopen(($account['encryption'] ?? '') === 'ssl' ? 'ssl://'.$account['host'] : (string) $account['host'], (int) $account['port'], $code, $message, 10);

        if ($socket === false) {
            throw new RuntimeException("Could not connect to {$account['host']}:{$account['port']} ({$message}).");
        }

        fclose($socket);
    }

    /**
     * Send one message through the account's server.
     *
     * @param  array<string, mixed>  $account
     */
    public function send(array $account, Email $message): void
    {
        (new Mailer($this->transport($account)))->send($message);
    }

    /**
     * The "From" of the account, used when a message doesn't name its own.
     *
     * @param  array<string, mixed>  $account
     * @return array{address: string, name: string}
     *
     * @throws InvalidArgumentException when the account has no address
     */
    public function sender(array $account): array
    {
        $address = trim((string) ($account['from_address'] ?? ''));

        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The SMTP account needs a valid From address. Edit the connection in Apps.');
        }

        return ['address' => $address, 'name' => trim((string) ($account['from_name'] ?? ''))];
    }
}
