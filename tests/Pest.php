<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Hermetic test database
|--------------------------------------------------------------------------
|
| Laravel's dotenv loader is immutable, so DB_* variables exported in the
| surrounding shell (common in container and CI shells) take precedence over
| phpunit.xml / .env.testing and would point the suite at a live database.
| Clearing them here — before any test boots the application — keeps the test
| database resolution under the control of the test configuration and
| guarantees the suite stays hermetic (in-memory SQLite via RefreshDatabase,
| per NFR-13).
|
*/

foreach (['DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DB_SOCKET'] as $databaseVariable) {
    putenv($databaseVariable);
    unset($_ENV[$databaseVariable], $_SERVER[$databaseVariable]);
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createBusinessWithRole(string $role, ?Business $business = null): array
{
    // make a fake one if null
    $business ??= Business::factory()->create();

    // crea a new fake user
    $user = User::factory()->create();

    $business->users()->attach($user, ['role' => $role]);

    return [$business, $user];
}

function createOwner(?Business $business = null)
{
    return createBusinessWithRole('owner', $business);
}

function createManager(?Business $business = null)
{
    return createBusinessWithRole('manager', $business);
}

function createStaff(?Business $business = null)
{
    return createBusinessWithRole('staff', $business);
}
