<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/env.php';

use App\Core\Request;

$router = (require __DIR__ . '/../app/bootstrap.php')();

$router->dispatch(Request::fromGlobals())->send();
