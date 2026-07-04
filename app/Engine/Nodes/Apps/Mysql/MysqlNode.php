<?php

namespace App\Engine\Nodes\Apps\Mysql;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class MysqlNode extends AppNode
{
    protected string $driver = 'mysql';

    protected int $defaultPort = 3306;

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $connection = $this->buildConnection($input, $this->driver, $this->defaultPort);

        return match ($operation) {
            'select' => $this->select($connection, $input),
            'insert' => $this->insert($connection, $input),
            'update' => $this->update($connection, $input),
            'delete' => $this->delete($connection, $input),
            'raw_query' => $this->rawQuery($connection, $input),
            default => $this->fail(class_basename($this).": unknown operation '{$operation}'"),
        };
    }

    protected function buildConnection(NodeInput $input, string $driver, int $defaultPort): Connection
    {
        $name = 'engine_'.$driver.'_'.md5(json_encode($input->credentials));

        config(["database.connections.{$name}" => [
            'driver' => $driver,
            'host' => $input->credentials['host'] ?? 'localhost',
            'port' => (int) ($input->credentials['port'] ?? $defaultPort),
            'database' => $input->credentials['database'] ?? '',
            'username' => $input->credentials['username'] ?? '',
            'password' => $input->credentials['password'] ?? '',
        ]]);

        return DB::connection($name);
    }

    protected function select(Connection $db, NodeInput $input): NodeResult
    {
        $rows = $db->table($input->config['table'])
            ->when($input->config['where'] ?? null, fn ($q, $where) => $q->where($where))
            ->limit((int) ($input->config['limit'] ?? 100))
            ->get();

        return $this->success(['rows' => $rows->toArray(), 'count' => $rows->count()]);
    }

    protected function insert(Connection $db, NodeInput $input): NodeResult
    {
        $id = $db->table($input->config['table'])->insertGetId($input->config['data'] ?? []);

        return $this->success(['inserted_id' => $id]);
    }

    protected function update(Connection $db, NodeInput $input): NodeResult
    {
        $affected = $db->table($input->config['table'])
            ->where($input->config['where'] ?? [])
            ->update($input->config['data'] ?? []);

        return $this->success(['affected_rows' => $affected]);
    }

    protected function delete(Connection $db, NodeInput $input): NodeResult
    {
        $affected = $db->table($input->config['table'])
            ->where($input->config['where'] ?? [])
            ->delete();

        return $this->success(['affected_rows' => $affected]);
    }

    protected function rawQuery(Connection $db, NodeInput $input): NodeResult
    {
        $sql = $input->config['query'] ?? '';
        $bindings = $input->config['bindings'] ?? [];

        if (preg_match('/^\s*select/i', $sql)) {
            return $this->success(['rows' => $db->select($sql, $bindings)]);
        }

        $affected = $db->affectingStatement($sql, $bindings);

        return $this->success(['affected_rows' => $affected]);
    }
}
