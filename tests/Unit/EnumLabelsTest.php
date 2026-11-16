<?php

use App\Contracts\HasLabel;

it('gives a non-empty French label to every enum case', function (string $enum) {
    expect(is_subclass_of($enum, HasLabel::class))->toBeTrue();

    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
})->with(fn () => collect(glob(app_path('Enums/*.php')))
    ->map(fn (string $file): string => 'App\\Enums\\'.basename($file, '.php'))
    ->all());
