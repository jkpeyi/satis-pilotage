<?php

namespace App\Livewire\Concerns;

use App\Enums\Confidence;
use App\Models\Component;
use App\Models\Repository;
use App\Models\User;

/**
 * Choices of the issue fields that depend on the versions the issue concerns (SPEC-001 L5).
 */
final class IssueFieldOptions
{
    /**
     * @param  list<int>  $versionIds
     * @return array<string, mixed> users, issueRepositories, issueComponents, confidences
     */
    public static function for(array $versionIds): array
    {
        return [
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'issueRepositories' => Repository::query()->active()->whereIn('version_id', $versionIds)->orderBy('path')->pluck('path', 'id'),
            'issueComponents' => Component::query()->active()->whereIn('version_id', $versionIds)->orderBy('name')->get()
                ->mapWithKeys(fn (Component $item): array => [$item->id => $item->displayCode().' · '.$item->name]),
            'confidences' => collect(Confidence::cases())->mapWithKeys(fn (Confidence $confidence): array => [$confidence->value => $confidence->label()])->all(),
        ];
    }
}
