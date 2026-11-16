<?php

namespace App\Actions\Referential;

use App\Models\Version;
use Illuminate\Support\Facades\DB;

/**
 * Archives a version (RG-12). An archived version cannot stay the reference of its product.
 */
final class ArchiveVersion
{
    public function __construct(private readonly SetReferenceVersion $setReferenceVersion) {}

    public function handle(Version $version): void
    {
        DB::transaction(function () use ($version): void {
            if ($version->isReferenceOfItsProduct()) {
                $this->setReferenceVersion->handle($version->product, null);
            }

            $version->archive();
        });
    }
}
