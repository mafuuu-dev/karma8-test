<?php

declare(strict_types=1);

namespace App\Services\Worker;

use Throwable;

use function App\Tools\Email\{check_email, send_email, make_subscription_notify_message};
use function App\Tools\Message\make_message;

use const App\Repositories\Queue\{QUEUE_CHECK_INVALID, QUEUE_CHECK_REQUIRED, QUEUE_CHECK_VALID};
use const App\Tools\Email\DEFAULT_SENDER;
use const App\Tools\Message\TYPE_ACTION;
use const App\Tools\Process\{PROCESS_SUCCESS, PROCESS_SKIPPED, PROCESS_ERROR};

/**
 * @throws Throwable
 */
function launch(?string $encoded_job): int
{
    if (is_null($encoded_job)) {
        print make_message('Skipped');

        return PROCESS_SKIPPED;
    }

    try {
        $job = json_decode(json: $encoded_job, flags: JSON_THROW_ON_ERROR);
        process_job($job);        
    } catch (Throwable $e) {
        print make_message($e->getMessage());

        return PROCESS_ERROR;
    }

    return PROCESS_SUCCESS;
}

/**
 * @throws Throwable
 */
function process_job(object $job): void
{
    print make_message('Processing');
    
    if ($job->status === QUEUE_CHECK_INVALID) {
        print make_message('Invalid');

        return;
    }

    if ($job->status === QUEUE_CHECK_REQUIRED) {
        $job->status = check_email($job->email) ? QUEUE_CHECK_VALID : QUEUE_CHECK_INVALID;
        
        print make_message($job->status, TYPE_ACTION);
    }

    if ($job->status === QUEUE_CHECK_VALID) {
        send_email(DEFAULT_SENDER, $job->email, make_subscription_notify_message($job->username));
    }
    
    print make_message('Processed');
}