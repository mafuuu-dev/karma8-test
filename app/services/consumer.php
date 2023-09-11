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
            throw new Exception("Process: $job->id => Failed to start");
        }

        $processes[$job->id] = $process;
        print "Process: $job->id => Is running\n";
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

    $result = [
        'process' => $process,
        'pipes' => $pipes,
        'job' => $job,
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
        $pipes = array_combine($keys, $stdouts);;

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

            print "Process: $key => $message->data\n";
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
        print "Process: $key => Ended with an error: $result\n";

        return;
    }

    dequeue_job($connection, $key);

    print "Process: $key => Completed successfully\n"; 
}

/**
 * @throws Throwable
 */
function job_handling(Connection $connection, array $processes, string $key, string $status): void
{
    change_job_status($connection, $key, $status);
    mark_as_checked($connection, $processes[$key]['job']->user_id, $status === QUEUE_CHECK_VALID);

    print "Process: $key => Job status changed to $status\n";
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