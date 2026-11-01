<?php

namespace App\Services\SuperAdmin\System\Checks;

/**
 * Opens a TCP connection to the Reverb server, if broadcasting is set up to use it.
 */
class ReverbCheck implements HealthCheck
{
    public function name(): string
    {
        return 'reverb';
    }

    public function run(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['status' => self::SKIPPED, 'message' => 'Broadcasting does not use Reverb.'];
        }

        $host = (string) config('reverb.servers.reverb.hostname', config('broadcasting.connections.reverb.options.host', '127.0.0.1'));
        $port = (int) config('reverb.servers.reverb.port', 8080);

        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 2);

        if ($socket === false) {
            return ['status' => self::DOWN, 'message' => "Reverb is not accepting connections on {$host}:{$port}.", 'details' => ['error' => $errorMessage]];
        }

        fclose($socket);

        return ['status' => self::HEALTHY, 'message' => 'Reverb accepted a connection.', 'details' => ['host' => $host, 'port' => $port]];
    }
}
