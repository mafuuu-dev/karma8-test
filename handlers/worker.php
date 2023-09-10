<?php

declare(strict_types=1);

namespace Handlers;

require_once __DIR__ . '/../vendor/autoload.php';

use function App\Services\Worker\launch;

exit(launch($argv[1] ?? null));