<?php

use App\Enums\FeatureOperation;
use App\Enums\FeaturePresence;
use App\Enums\ImplementationStatus;

it('maps every state to one presence and one operation, and back', function (ImplementationStatus $status) {
    expect(ImplementationStatus::fromAxes($status->presence(), $status->operation()))->toBe($status);
})->with(ImplementationStatus::cases());

it('gives an operation only to a present item', function () {
    expect(ImplementationStatus::fromAxes(FeaturePresence::Absent, FeatureOperation::Operational))->toBe(ImplementationStatus::Absent)
        ->and(ImplementationStatus::fromAxes(FeaturePresence::Unknown, FeatureOperation::Defective))->toBe(ImplementationStatus::Unknown)
        ->and(ImplementationStatus::fromAxes(FeaturePresence::Present, FeatureOperation::Partial))->toBe(ImplementationStatus::Partial)
        ->and(ImplementationStatus::Absent->operation())->toBe(FeatureOperation::NotApplicable)
        ->and(ImplementationStatus::Unknown->operation())->toBe(FeatureOperation::NotTested);
});

it('RG-05: counts only an absent item as a gap', function () {
    $gaps = array_filter(ImplementationStatus::cases(), fn (ImplementationStatus $status): bool => $status->isGap());

    expect(array_values($gaps))->toBe([ImplementationStatus::Absent]);
});

it('keeps the matrix status indexes of the former states', function () {
    expect(array_slice(array_map(fn (ImplementationStatus $status): string => $status->value, ImplementationStatus::cases()), 0, 5))
        ->toBe(['unknown', 'operational', 'partial', 'defective', 'absent']);
});
