<?php

declare(strict_types=1);

namespace App\Tools\Db;

use Exception;
use PgSql\{Result, Connection};
use Throwable;

const DSN_MASK = 'host=%s port=%s dbname=%s user=%s password=%s';

/**
 * @throws Exception
 */
function connect(): Connection
{
    $host = 'psql';
    $port = '5432';
    $dbname = 'karma8';
    $user = 'karma8';
    $password = 'test';

    $dsn = sprintf(DSN_MASK, $host, $port, $dbname, $user, $password);
    $connection = pg_connect($dsn) or throw new Exception('Failed to connect'); 
    
    return $connection;
}

/**
 * @throws Exception
 */
function close(Connection $connection): void
{
    pg_close($connection) or throw new Exception('Failed to close connection: ' . pg_last_error($connection));
}

/**
 * @throws Exception
 */
function free(Result $result): void
{
    pg_free_result($result) or throw new Exception('Failed to free result');
}

function fetch(Result $result): false|object
{
    return pg_fetch_object($result);
}

/**
 * @throws Exception
 */
function exec(Connection $connection, string $query, array $parameters = []): Result
{
    $result = pg_query_params($connection, $query, $parameters) 
        or throw new Exception('An error has occurred: ' . pg_last_error($connection));

    return $result;
}

/**
 * @throws Throwable
 */
function handle(callable $handlers): mixed
{
    $connection = connect();

    try {
        $result = $handlers($connection);
    } finally {
        close($connection);
    }

    return $result;
}

function placeholder(int $count, int $index): string
{
    $placeholder = [];
    for ($field = 1; $field <= $count; $field++) {
        $placeholder[] = '$' . $count * $index + $field;
    }

    return '(' . implode(',', $placeholder) . ')';
}
