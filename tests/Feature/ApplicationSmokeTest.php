<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    public function test_application_boots(): void
    {
        $this->assertTrue($this->app->bound('router'));
    }

    public function test_routes_can_be_listed(): void
    {
        $exitCode = Artisan::call('route:list');

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Route::has('employee.timesheets.index'));
        $this->assertTrue(Route::has('manage.users.index'));
    }

    public function test_blade_views_compile_successfully(): void
    {
        try {
            $this->assertSame(0, Artisan::call('view:cache'));
        } finally {
            Artisan::call('view:clear');
        }
    }
}
