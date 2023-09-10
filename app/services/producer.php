<?php

declare(strict_types=1);

namespace App\Services\Producer;

use Throwable;
use PgSql\Connection;

use function App\Repositories\Users\get_users_with_expiring_subscription;
use function App\Repositories\Queue\enqueue_jobs;
use function App\Tools\Transaction\transaction;

use const App\Repositories\Queue\{QUEUE_CHECK_REQUIRED, QUEUE_CHECK_VALID};
use const App\Tools\Transaction\REPEATABLE_READ;

/**
 * @throws Throwable
 */
function launch(): void
{
    $last_id = 0;

    while (true) {
        $users = transaction(
            fn (Connection $connection) => get_users_with_expiring_subscription($connection, $last_id), 
            REPEATABLE_READ
        );

        if (!count($users)) {
            break;
        }

        $last_id = transaction(function (Connection $connection) use ($users) {
            enqueue_jobs($connection, enrich_up_to_jobs($users));

            print 'Enqueue ' . count($users) . " jobs\n";

            return $users[count($users) - 1]->id;
        });
    }
}

/**
 * @param object[] $users
 * @return object[]
 */
function enrich_up_to_jobs(array $users): array
{
    return array_map(function ($user) {
        $enriched_user = clone $user;
        $enriched_user->user_id = $enriched_user->id;
        $enriched_user->status = $enriched_user->is_valid ? QUEUE_CHECK_VALID : QUEUE_CHECK_REQUIRED;
        unset($enriched_user->id);

        return $enriched_user;
    }, $users);
}