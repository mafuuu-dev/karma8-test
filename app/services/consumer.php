<?php

declare(strict_types=1);

namespace App\Services\Consumer;

use Exception;
use Throwable;
use PgSql\Connection;

use function App\Repositories\Queue\{get_jobs, change_job_status, dequeue_job};
use function App\Repositories\Users\mark_as_checked;
use function App\Tools\Message\parse_message;
use function App\Tools\Process\{make_process_command, create_descriptors};
use function App\Tools\Transaction\transaction;

use const App\Repositories\Queue\QUEUE_CHECK_VALID;
use const App\Tools\Message\TYPE_ACTION;
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
        $process = start_process($job);
        if (!$process) {
            process_output($job->id, 'Failed to start');
            
            continue;
        }

        $processes[$job->id] = $process;

        process_output($job->id, 'Is running');
    }

    return $processes;
}

/**
 * @throws Throwable
 */
function start_process(object $job): false|array
{
    $pipes = [];
    $encoded_job = json_encode(value: $job, flags: JSON_THROW_ON_ERROR);

    $process = proc_open(make_process_command($encoded_job), create_descriptors(), $pipes);
    if (!is_resource($process)) {
        return false;
    }

    $result = [
        'process' => $process,
        'pipes' => $pipes,
        'job' => $job,
    ];

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
            $output = fgets($stdout);
            if (!$output) {
                close_process($connection, $processes, $key);
                unset($processes[$key]);

                continue;
            }

            $message = parse_message($output);
            if ($message->type === TYPE_ACTION) {
                job_handling($connection, $processes, $key, $message->data);

                continue;
            }

            process_output($key, $message->data);
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
        process_output($key, "Ended with an error: $result");

        return;
    }

    dequeue_job($connection, $key);
    
    process_output($key, 'Completed successfully');
}

/**
 * @throws Throwable
 */
function job_handling(Connection $connection, array $processes, string $key, string $status): void
{
    change_job_status($connection, $key, $status);
    mark_as_checked($connection, $processes[$key]['job']->user_id, $status === QUEUE_CHECK_VALID);

    process_output($key, "Status changed to $status");
}

function process_output(string $key, string $message): void
{
    print "Process $key => $message\n";
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