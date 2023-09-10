<?php

declare(strict_types=1);

namespace App\Tools\Process;

const PROCESS_SUCCESS = 0;
const PROCESS_SKIPPED = 1;
const PROCESS_ERROR = 2;

const PIPE_STDIN = 0;
const PIPE_STDOUT = 1;
const PIPE_STDERR = 2;

const PROCESS_MASK = "php /app/handlers/worker.php '%s'";

function create_descriptors(): array
{
    return [
        PIPE_STDIN => ["pipe", "r"],
        PIPE_STDOUT => ["pipe", "w"],
        PIPE_STDERR => ["pipe", "w"]
    ];
}

function make_process_command(string $encoded_job): string
{
    return sprintf(PROCESS_MASK, $encoded_job);
}