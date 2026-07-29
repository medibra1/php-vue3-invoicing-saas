#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Database\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

loadEnvFile(dirname(__DIR__) . '/.env');

$migrator = new Migrator(Connection::fromEnv(), dirname(__DIR__) . '/database/migrations');
$ran = $migrator->run();

if ($ran === []) {
    echo "Nothing to migrate.\n";
    exit(0);
}

foreach ($ran as $name) {
    echo "Migrated: {$name}\n";
}
