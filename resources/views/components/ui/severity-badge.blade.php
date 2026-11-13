@props(['severity'])

<x-ui.badge :color="match ($severity) {
    \App\Enums\IssueSeverity::Blocking => 'red',
    \App\Enums\IssueSeverity::Major => 'amber',
    default => 'gray',
}" {{ $attributes }}>{{ $severity->label() }}</x-ui.badge>
