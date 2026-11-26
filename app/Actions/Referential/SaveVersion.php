<?php

namespace App\Actions\Referential;

use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Models\Version;
use Illuminate\Support\Facades\DB;

final class SaveVersion
{
    public function __construct(private readonly SetReferenceVersion $setReferenceVersion) {}

    /**
     * Moving a version to another product removes it as reference of its former product (RG-08).
     *
     * A verification decision records who took it and when.
     *
     * @param  array{product_id: int, customer_id: ?int, name: string, status: VersionStatus, is_historical_pivot: bool, technical_owner_id: ?int, deputy_id: ?int, verification_status: VerificationStatus, notes: ?string}  $data
     */
    public function handle(array $data, ?Version $version = null): Version
    {
        return DB::transaction(function () use ($data, $version): Version {
            $version ??= new Version;
            $formerProduct = $version->exists ? $version->product : null;

            $version->fill(collect($data)->except('verification_status')->all());
            $version->applyVerificationStatus($data['verification_status']);
            $version->save();

            if ($formerProduct !== null
                && $formerProduct->id !== $version->product_id
                && $formerProduct->reference_version_id === $version->id) {
                $this->setReferenceVersion->handle($formerProduct, null);
            }

            return $version;
        });
    }
}
