<?php

declare(strict_types=1);

namespace App\Tools\Message;

use Throwable;

const TYPE_INFO = 'info';
const TYPE_ACTION = 'action';

/**
 * @throws Throwable
 */
function make_message(string $data, string $type = TYPE_INFO): void
{
    print json_encode(value: ['type' => $type, 'data' => $data], flags: JSON_THROW_ON_ERROR) . PHP_EOL;
}

/**
 * @throws Throwable
 */
function parse_message(string $message): false|object
{
    return json_decode(json: $message, flags: JSON_THROW_ON_ERROR);
}