<?php

namespace App\Actions\Architecture;

use App\Actions\Referential\RegisterTechnologies;
use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\Repository;
use DomainException;

final class SaveComponent
{
    public function __construct(private readonly RegisterTechnologies $registerTechnologies) {}

    /**
     * @param  array{version_id: int, repository_id: ?int, name: string, type: ComponentType, technology: ?string, technology_version: ?string, description: ?string}  $data  description is shown as « Note »
     */
    public function handle(array $data, ?Component $component = null): Component
    {
        if ($data['repository_id'] !== null
            && Repository::query()->whereKey($data['repository_id'])->value('version_id') !== $data['version_id']) {
            throw new DomainException('Le dépôt choisi n’appartient pas à cette version.');
        }

        $component ??= new Component;
        $component->fill($data)->save();

        if ($component->technology !== null) {
            $this->registerTechnologies->handle([$component->technology => $component->technology_version]);
        }

        return $component;
    }
}
