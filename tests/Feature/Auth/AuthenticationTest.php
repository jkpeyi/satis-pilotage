<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('shows the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('Connexion');
});

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'correct-horse-battery')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('logs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('shows the dashboard to authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tableau de bord');
});

it('creates a local account from the command line', function () {
    $this->artisan('user:create', [
        '--name' => 'Awa Koné',
        '--email' => 'awa.kone@example.com',
        '--password' => 'un-mot-de-passe-solide',
    ])->assertSuccessful();

    $this->assertDatabaseHas('users', ['email' => 'awa.kone@example.com', 'name' => 'Awa Koné']);
});

it('refuses a short password when creating an account', function () {
    $this->artisan('user:create', [
        '--name' => 'Awa Koné',
        '--email' => 'awa.kone@example.com',
        '--password' => 'court',
    ])->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => 'awa.kone@example.com']);
});

it('resets the password of a local account', function () {
    $user = User::factory()->create();

    $this->artisan('user:password', ['email' => $user->email, '--password' => 'nouveau-mot-de-passe'])
        ->assertSuccessful();

    expect(auth()->attempt(['email' => $user->email, 'password' => 'nouveau-mot-de-passe']))->toBeTrue();
});
