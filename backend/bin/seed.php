#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use Database\Seeders\RolesAndPermissionsSeeder;

require dirname(__DIR__) . '/vendor/autoload.php';

loadEnvFile(dirname(__DIR__) . '/.env');

$connection = Connection::fromEnv();

(new RolesAndPermissionsSeeder())->run($connection);

echo "Seeded roles and permissions.\n";
