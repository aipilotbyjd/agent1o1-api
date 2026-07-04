<?php

namespace App\Engine\Nodes\Apps\Redis;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis;

class RedisNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $connection = $this->buildConnection($input);

        return match ($operation) {
            'get' => $this->success(['value' => $connection->get($input->config['key'])]),
            'set' => $this->set($connection, $input),
            'delete' => $this->success(['deleted' => $connection->del($input->config['key'])]),
            'increment' => $this->success(['value' => $connection->incrby($input->config['key'], (int) ($input->config['by'] ?? 1))]),
            'keys' => $this->success(['keys' => $connection->keys($input->config['pattern'] ?? '*')]),
            'publish' => $this->success(['receivers' => $connection->publish($input->config['channel'], $input->config['message'] ?? '')]),
            default => $this->fail("Redis: unknown operation '{$operation}'"),
        };
    }

    private function buildConnection(NodeInput $input): mixed
    {
        $name = 'engine_redis_'.md5(json_encode($input->credentials));

        config(["database.redis.{$name}" => [
            'host' => $input->credentials['host'] ?? '127.0.0.1',
            'port' => (int) ($input->credentials['port'] ?? 6379),
            'password' => $input->credentials['password'] ?? null,
            'database' => (int) ($input->credentials['database'] ?? 0),
        ]]);

        return Redis::connection($name);
    }

    private function set(mixed $connection, NodeInput $input): NodeResult
    {
        $key = $input->config['key'];
        $value = is_array($input->config['value'] ?? null)
            ? json_encode($input->config['value'])
            : (string) ($input->config['value'] ?? '');

        if (isset($input->config['ttl_seconds'])) {
            $connection->setex($key, (int) $input->config['ttl_seconds'], $value);
        } else {
            $connection->set($key, $value);
        }

        return $this->success(['key' => $key, 'stored' => true]);
    }
}
