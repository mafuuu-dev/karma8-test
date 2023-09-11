<?php

declare(strict_types=1);

namespace App\Repositories\Queue;

use Throwable;
use PgSql\Connection;

use function App\Tools\Db\{exec, free, fetch, placeholder};
use function App\Tools\Format\format_job;

const QUEUE_CHECK_REQUIRED = 'check_required';
const QUEUE_CHECK_VALID = 'check_valid';
const QUEUE_CHECK_INVALID = 'check_invalid';

const RECORDS_LIMIT = 100;

/**
 * @return object[]
 * @throws Throwable
 */
function get_jobs(Connection $connection): array
{
    $query = '
        SELECT * FROM queue WHERE sent_at <= extract(EPOCH FROM now()) AND status != $1
        ORDER BY sent_at, id FOR UPDATE SKIP LOCKED LIMIT $2
    ';
    $result = exec($connection, $query, [QUEUE_CHECK_INVALID, RECORDS_LIMIT]);

    $jobs = [];
    while ($job = fetch($result)) {
        $jobs[] = format_job($job);
    }
    free($result);

    return $jobs;
}

/**
 * @throws Throwable
 */
function change_job_status(Connection $connection, string $id, string $status): void
{
    free(exec($connection, 'UPDATE queue SET status=$1 WHERE id=$2', [$status, $id]));
}

/**
 * @param object[] $jobs
 * @throws Throwable
 */
function enqueue_jobs(Connection $connection, array $jobs): void 
{
    if (!count($jobs)) {
        return;
    }

    $parameters = [];
    $placeholders = [];
    foreach ($jobs as $index => $job) {
        $record = [
            $job->user_id,
            $job->username,
            $job->email,
            $job->sent_at,
            $job->status
        ];

        array_push($parameters, ...$record);
        $placeholders[] = placeholder(count($record), $index);
    }

    $query_mask = 'INSERT INTO queue (user_id, username, email, sent_at, status) VALUES %s';
    $query = sprintf($query_mask, implode(',', $placeholders));

    free(exec($connection, $query, $parameters));
}

/**
 * @throws Throwable
 */
function dequeue_job(Connection $connection, string $id): void
{
    free(exec($connection, 'DELETE FROM queue WHERE id=$1', [$id]));
}