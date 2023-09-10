<?php

declare(strict_types=1);

namespace App\Services\Consumer;

use Exception;
use Throwable;
use PgSql\Connection;

use function App\Repositories\Queue\{get_jobs, change_job_status, dequeue_job};
use function App\Tools\Transaction\transaction;
use function App\Tools\Process\{make_process_command, create_descriptors};

use const App\Repositories\Queue\{QUEUE_CHECK_VALID, QUEUE_CHECK_INVALID};
use const App\Tools\Process\{PROCESS_SUCCESS, PIPE_STDOUT};

const BASE_DELAY = 3; // sec
const MAX_ATTEMPTS = 4;

/**
 * @throws Throwable
 */
function launch(): void
{
    $attempts = 0;

    while (true) {
        $is_processed = transaction(function (Connection $connection) {
            $jobs = get_jobs($connection);

            if (!count($jobs)) {
                return false;
            }

            $processes = create_processes($jobs);
            processes_handling($connection, $processes);

            return true;
        });

        $attempts = get_attempts($is_processed, $attempts);
        delay($attempts);
    }
}

/**
 * @throws Throwable
 */
function create_processes(array $jobs): array
{
    $processes = [];
    foreach ($jobs as $job) {
        $encoded_job = json_encode(value: $job, flags: JSON_THROW_ON_ERROR);
        $process = start_process($encoded_job);

        if (!$process) {
            throw new Exception("Failed to start process: $job->id");
        }

        $processes[$job->id] = $process;
        print "Process is running: $job->id\n";
    }

    return $processes;
}

function start_process(string $encoded_job): false|array
{
    $pipes = [];
    $process = proc_open(make_process_command($encoded_job), create_descriptors(), $pipes);

    $result = [
        'process' => $process, 
        'pipes' => $pipes
    ];
    if (!is_resource($process)) {
        return false;
    }

    stream_set_blocking($result['pipes'][PIPE_STDOUT], false);

    return $result;
}

/**
 * @throws Throwable
 */
function processes_handling(Connection $connection, array $processes): void
{
    while (count($processes) > 0) {
        $write = [];
        $except = [];

        $keys = array_keys($processes);
        $stdouts = array_column(array_column($processes, 'pipes'), PIPE_STDOUT);
        $pipes = array_combine($keys, $stdouts);

        if (!stream_select($pipes, $write, $except, null)) {
            continue;
        }

        foreach ($pipes as $key => $stdout) {
            $output = stream_get_contents($stdout);

            if ($output === "") {
                close_process($connection, $processes, $key);
                unset($processes[$key]);

                continue;
            }

            if (in_array($output, [QUEUE_CHECK_VALID, QUEUE_CHECK_INVALID])) {
                change_job_status($connection, $key, $output);
                print "Process: $key => Job status changed to $output\n";

                continue;
            }

            print "Process: $key => $output\n";
        }
    }
}

/**
 * @throws Throwable
 */
function close_process(Connection $connection, array $processes, string $key): void
{
    fclose($processes[$key]['pipes'][PIPE_STDOUT]);
    $result = proc_close($processes[$key]['process']);

    if ($result > PROCESS_SUCCESS) {
        print "Process $key ended with an error: $result\n";

        return;
    }

    dequeue_job($connection, $key);

    print "Process $key completed successfully\n"; 
}

function get_attempts(bool $is_processed, int $attempts): int
{
    return $is_processed ? 0 : min(++$attempts, MAX_ATTEMPTS);
}

function delay(int $attempts): void
{
    if ($attempts === 0) {
        return;
    }

    $delay = BASE_DELAY ** $attempts;
    print "Consumer is sleeping: $delay seconds\n";

    sleep($delay);
}