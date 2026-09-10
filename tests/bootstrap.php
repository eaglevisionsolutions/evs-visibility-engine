<?php

declare(strict_types=1);

/**
 * Tests run against a real database — no mocking PDO, because the thing
 * under test (BaseModel's tenant scoping) IS the SQL being generated.
 * Point .env.local (or .env) at a disposable test database before running
 * `vendor/bin/phpunit`; migrations must already be applied to it.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/env.php';
