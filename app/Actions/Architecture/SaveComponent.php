<?php

namespace App\Actions\Architecture;

use App\Enums\ComponentType;
use App\Enums\VerificationStatus;
use App\Models\Component;
use App\Models\Repository;
use DomainException;

final class SaveComponent
{
    /**
     * @param  array{version_id: int, repository_id: ?int, name: string, type: ComponentType, technology: ?string, technology_version: ?string, description: ?string, evidence_url: ?string, verification_status: VerificationStatus}  $data
     */
    public function handle(array $data, ?Component $component = null): Component
    {
        if ($data['repository_id'] !== null
            && Repository::query()->whereKey($data['repository_id'])->value('version_id') !== $data['version_id']) {
            throw new DomainException('Le dépôt choisi n’appartient pas à cette version.');
        }

        $component ??= new Component;
        $component->fill(collect($data)->except('verification_status')->all());
        $component->applyVerificationStatus($data['verification_status']);
        $component->save();

        return $component;
    }
}
