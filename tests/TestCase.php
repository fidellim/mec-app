<?php

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstUnsafeTestingDatabase($app);

        return $app;
    }

    private function guardAgainstUnsafeTestingDatabase(Application $app): void
    {
        if (
            $app->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:'
        ) {
            return;
        }

        throw new RuntimeException(
            'Unsafe test database configuration detected. Tests must run with APP_ENV=testing, DB_CONNECTION=sqlite, and DB_DATABASE=:memory:. Run php artisan config:clear before testing.'
        );
    }
}
