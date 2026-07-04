<?php

namespace App\Engine\Nodes\Apps\Postgresql;

use App\Engine\Nodes\Apps\Mysql\MysqlNode;

class PostgresqlNode extends MysqlNode
{
    protected string $driver = 'pgsql';

    protected int $defaultPort = 5432;
}
