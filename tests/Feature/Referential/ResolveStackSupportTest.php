<?php

use App\Actions\Referential\ResolveStackSupport;
use App\Enums\SupportStatus;
use App\Models\Technology;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Technology::factory()->create(['component' => 'php', 'cycle' => '8', 'end_of_support' => '2030-01-01']);
    Technology::factory()->create(['component' => 'php', 'cycle' => '8.1', 'end_of_support' => '2025-12-31']);
    Technology::factory()->create(['component' => 'php', 'cycle' => '8.3', 'end_of_support' => '2027-03-31']);
    Technology::factory()->create(['component' => 'laravel', 'cycle' => '9', 'end_of_support' => '2024-02-06']);
    Technology::factory()->create(['component' => 'laravel', 'cycle' => '12', 'end_of_support' => null]);
});

it('matches the longest cycle a version starts with', function () {
    $support = app(ResolveStackSupport::class)->handle(['php' => '8.1.27'], Carbon::parse('2026-11-13'));

    expect($support['php']['technology']->cycle)->toBe('8.1')
        ->and($support['php']['status'])->toBe(SupportStatus::OutOfSupport);
});

it('reads composer constraints such as ^8.3', function () {
    $support = app(ResolveStackSupport::class)->handle(['php' => '^8.3'], Carbon::parse('2026-11-13'));

    expect($support['php']['technology']->cycle)->toBe('8.3')
        ->and($support['php']['status'])->toBe(SupportStatus::EndingSoon);
});

it('does not confuse cycle 1 with version 12', function () {
    Technology::factory()->create(['component' => 'laravel', 'cycle' => '1', 'end_of_support' => '2014-01-01']);

    $support = app(ResolveStackSupport::class)->handle(['laravel' => '12.3.0'], Carbon::parse('2026-11-13'));

    expect($support['laravel']['technology']->cycle)->toBe('12')
        ->and($support['laravel']['status'])->toBe(SupportStatus::Unknown);
});

it('reports supported, out of support and unknown components', function () {
    $support = app(ResolveStackSupport::class)->handle([
        'php' => '8.4.1',
        'laravel' => '9.52.16',
        'react' => '17.0.2',
    ], Carbon::parse('2026-11-13'));

    expect($support['php']['status'])->toBe(SupportStatus::Supported)
        ->and($support['laravel']['status'])->toBe(SupportStatus::OutOfSupport)
        ->and($support['react']['status'])->toBe(SupportStatus::Unknown)
        ->and(array_keys($support))->toBe(['laravel', 'php', 'react']);
});
