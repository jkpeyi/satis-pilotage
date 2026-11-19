<?php

namespace App\Actions\Architecture;

use App\Enums\IntegrationProtocol;
use App\Enums\VerificationStatus;
use App\Models\Component;
use App\Models\Integration;
use DomainException;

/**
 * Records a flow from a component to another component (possibly of another application) or to an
 * external system. Exactly one target, never the source itself.
 */
final class SaveIntegration
{
    /**
     * @param  array{source_component_id: int, target_component_id: ?int, external_system: ?string, protocol: IntegrationProtocol, description: ?string, evidence_url: ?string, verification_status: VerificationStatus}  $data
     */
    public function handle(array $data, ?Integration $integration = null): Integration
    {
        if (($data['target_component_id'] === null) === ($data['external_system'] === null)) {
            throw new DomainException('Indiquez soit un composant cible, soit un système externe.');
        }

        if ($data['target_component_id'] === $data['source_component_id']) {
            throw new DomainException('Un composant ne peut pas s’intégrer avec lui-même.');
        }

        if ($data['target_component_id'] !== null && ! Component::query()->active()->whereKey($data['target_component_id'])->exists()) {
            throw new DomainException('Le composant cible est introuvable ou archivé.');
        }

        $integration ??= new Integration;
        $integration->fill(collect($data)->except('verification_status')->all());
        $integration->applyVerificationStatus($data['verification_status']);
        $integration->save();

        return $integration;
    }
}
