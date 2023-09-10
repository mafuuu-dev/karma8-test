<?php

declare(strict_types=1);

namespace App\Tools\Transaction;

use Throwable;
use PgSql\Connection;

use function App\Tools\Db\{connect, close, exec};

const READ_UNCOMMITTED = 'READ UNCOMMITTED';
const READ_COMMITTED = 'READ COMMITTED';
const REPEATABLE_READ = 'REPEATABLE READ';
const SERIALIZABLE = 'SERIALIZABLE';

/**
 * @throws Throwable
 */
function transaction(callable $handler, string $isolation = READ_COMMITTED): mixed
{
    $connection = connect();

    begin($connection, $isolation);
    try {
        $result = $handler($connection);

        commit($connection);
    } catch (Throwable $e) {
        rollback($connection);

        throw $e;
    } finally {
        close($connection);
    }

    return $result;
}

/**
 * @throws Throwable
 */
function begin(Connection $connection, string $isolation = READ_COMMITTED): void
{
    exec($connection, "BEGIN ISOLATION LEVEL $isolation");

    if ($isolation !== READ_UNCOMMITTED) {
        return;
    }

    exec($connection, 'SET LOCAL default_transaction_read_only = off');
}

/**
 * @throws Throwable
 */
function commit(Connection $connection): void
{
    exec($connection, 'COMMIT');
}

/**
 * @throws Throwable
 */
function rollback(Connection $connection): void
{
    exec($connection, 'ROLLBACK');
}