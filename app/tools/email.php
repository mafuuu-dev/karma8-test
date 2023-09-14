<?php

declare(strict_types=1);

namespace App\Tools\Email;

use Throwable;

use function App\Tools\Message\make_message;

const SUBSCRIPTION_NOTIFY_MESSAGE_MASK = '%s, your subscription is expiring soon!';
const DEFAULT_SENDER = 'subscription@karma8.io';

/**
 * @throws Throwable
 */
function check_email(string $email): int
{
    $duration = rand(1, 60);
    $result = rand(0, 1);

    make_message("check_email(duration: $duration, result: $result)");

    sleep($duration);
    return $result;
}

/**
 * @throws Throwable
 */
function send_email(string $from, string $to, string $text): void 
{
    $duration = rand(1, 10);

    make_message("send_email(duration: $duration)");
    
    sleep($duration);
}

function make_subscription_notify_message(string $username): string
{
    return sprintf(SUBSCRIPTION_NOTIFY_MESSAGE_MASK, $username);
}