<?php

namespace App\Actions\Remediation;

use App\Enums\WorkPackageStatus;
use App\Models\WorkPackage;
use DomainException;

/**
 * Creates or updates a work package of the remediation roadmap (L8).
 */
final class SaveWorkPackage
{
    /**
     * @param  array{product_id: int, name: string, objective: ?string, starts_on: ?string, due_on: ?string, status: WorkPackageStatus}  $data
     */
    public function handle(array $data, ?WorkPackage $workPackage = null): WorkPackage
    {
        if ($data['starts_on'] !== null && $data['due_on'] !== null && $data['due_on'] < $data['starts_on']) {
            throw new DomainException('Le jalon précède le début du lot.');
        }

        if ($workPackage !== null && $workPackage->product_id !== $data['product_id'] && $workPackage->actions()->exists()) {
            throw new DomainException('Ce lot contient des actions : il reste rattaché à son produit.');
        }

        $workPackage ??= new WorkPackage(['position' => (int) WorkPackage::query()->where('product_id', $data['product_id'])->max('position') + 1]);
        $workPackage->fill($data)->save();

        return $workPackage;
    }
}
