<?php

use App\Actions\Implementations\ChangeImplementationStatus;
use App\Enums\ImplementationStatus;
use App\Models\Implementation;
use App\Models\ImplementationStatusChange;
use App\Models\User;

it('RG-06: records a history row when the status is saved through the model', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $implementation = Implementation::factory()->create();

    $implementation->update(['status' => ImplementationStatus::Defective]);

    $change = $implementation->statusChanges()->sole();
    expect($change->from_status)->toBe(ImplementationStatus::Unknown)
        ->and($change->to_status)->toBe(ImplementationStatus::Defective)
        ->and($change->user_id)->toBe($user->id);
});

it('RG-06: does not record history when another field changes', function () {
    $implementation = Implementation::factory()->create();

    $implementation->update(['deviation' => 'Le calcul des pénalités diffère.']);

    expect($implementation->statusChanges()->count())->toBe(0);
});

it('RG-06: changes many statuses at once and records one history row each', function () {
    $user = User::factory()->create();
    $toChange = Implementation::factory()->count(3)->create();
    $alreadyDefective = Implementation::factory()->create(['status' => ImplementationStatus::Defective]);

    $changed = app(ChangeImplementationStatus::class)->handle(
        [...$toChange->modelKeys(), $alreadyDefective->id],
        ImplementationStatus::Defective,
        $user,
        'Revue du module Crédit',
    );

    expect($changed)->toBe(3)
        ->and(ImplementationStatusChange::query()->count())->toBe(3)
        ->and(ImplementationStatusChange::query()->pluck('note')->unique()->all())->toBe(['Revue du module Crédit'])
        ->and($alreadyDefective->statusChanges()->count())->toBe(0);

    $toChange->each(function (Implementation $implementation) use ($user): void {
        $implementation->refresh();
        expect($implementation->status)->toBe(ImplementationStatus::Defective)
            ->and($implementation->assessed_by)->toBe($user->id)
            ->and($implementation->assessed_at)->not->toBeNull();
    });
});

it('RG-06: keeps the status history append-only', function () {
    $implementation = Implementation::factory()->create();
    $implementation->update(['status' => ImplementationStatus::Operational]);
    $change = $implementation->statusChanges()->sole();

    expect(fn () => $change->update(['note' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $change->delete())->toThrow(LogicException::class);
});
