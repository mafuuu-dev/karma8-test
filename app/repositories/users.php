<?php

declare(strict_types=1);

namespace App\Repositories\Users;

use Throwable;
use PgSql\Connection;

use function App\Tools\Db\{exec, free, fetch};
use function App\Tools\Format\format_user;

const RECORDS_LIMIT = 1000;

/**
 * @return object[]
 * @throws Throwable
 */
function get_users_with_expiring_subscription(Connection $connection, int $last_id = 0): array
{
    $query = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.is_valid,
            extract(EPOCH FROM CASE
                WHEN
                    to_timestamp(u.expired_at) BETWEEN date_trunc('day', current_timestamp) + INTERVAL '1' DAY
                    AND date_trunc('day', current_timestamp) + INTERVAL '2' DAY - INTERVAL '1' SECOND
                THEN to_timestamp(u.expired_at) - INTERVAL '1' DAY
                ELSE to_timestamp(u.expired_at) - INTERVAL '3' DAY
            END)::INTEGER AS sent_at
        FROM users AS u
        LEFT JOIN queue q ON u.id = q.user_id
        WHERE
            u.id > $1
            AND u.expired_at != 0
            AND u.is_confirmed = true
            AND (u.is_checked = false OR u.is_valid = true)
            AND (
                (
                    to_timestamp(u.expired_at) BETWEEN date_trunc('day', current_timestamp) + INTERVAL '1' day
                    AND date_trunc('day', current_timestamp) + INTERVAL '2' DAY - INTERVAL '1' SECOND
                ) OR (
                    to_timestamp(u.expired_at) BETWEEN date_trunc('day', current_timestamp) + INTERVAL '3' day
                    AND date_trunc('day', current_timestamp) + INTERVAL '4' DAY - INTERVAL '1' SECOND
                )
            )
            AND q.user_id IS NULL
        ORDER BY u.id
        LIMIT $2
    ";
    $parameters = [$last_id, RECORDS_LIMIT];

    $users = [];
    $result = exec($connection, $query, $parameters);
    while ($row = fetch($result)) {
        $users[] = format_user($row);
    }
    free($result);

    return $users;
}

/**
 * @throws Throwable
 */
function mark_as_checked(Connection $connection, int $id, bool $is_valid): void
{
    $query = 'UPDATE users SET is_checked = true, is_valid = $1 WHERE id = $2';
    $parameters = [(int) $is_valid, $id];

    free(exec($connection, $query, $parameters));
}