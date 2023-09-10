<?php

declare(strict_types=1);

namespace App\Tools\Email;

const SUBSCRIPTION_NOTIFY_MESSAGE_MASK = '%s, your subscription is expiring soon!';
const DEFAULT_SENDER = 'subscription@karma8.io';

function check_email(string $email): int
{
    $duration = rand(1, 60); 
    $result = rand(0, 1);
    print("Check email => Duration: $duration sec; Result: $result;\n");

    sleep($duration);
    return $result;
}

function send_email(string $from, string $to, string $text): void 
{
    $duration = rand(1, 10);
    print("Send email => Duration: $duration sec;\n");
    
    sleep($duration);
}

function make_subscription_notify_message(string $username): string
{
    return sprintf(SUBSCRIPTION_NOTIFY_MESSAGE_MASK, $username);
}