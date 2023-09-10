<?php

declare(strict_types=1);

namespace App\Tools\Format;

function format_boolean(string $value): bool
{
    return $value === 't';
}

function format_user(object $user): object
{
    return (object) [
        'id' => (int) $user->id,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'is_valid' => format_boolean($user->is_valid),
        'sent_at' => (int) $user->sent_at
    ];
}

function format_job(object $job): object
{
    return (object) [
        'id' => (string) $job->id,
        'user_id' => (int) $job->user_id,
        'username' => (string) $job->username,
        'email' => (string) $job->email,
        'sent_at' => (int) $job->sent_at,
        'status' => (string) $job->status
    ];
}