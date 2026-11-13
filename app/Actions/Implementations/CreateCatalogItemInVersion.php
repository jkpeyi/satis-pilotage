<?php

namespace App\Actions\Implementations;

use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Version;
use Illuminate\Support\Facades\DB;

/**
 * Creates a catalog item from a version and attaches it right away (EF-203).
 */
final class CreateCatalogItemInVersion
{
    public function __construct(
        private readonly CreateModule $createModule,
        private readonly CreateFeature $createFeature,
        private readonly AttachCatalogItems $attachCatalogItems,
    ) {}

    /**
     * @param  'module'|'feature'  $kind
     */
    public function handle(Version $version, string $kind, string $name, ?int $parentModuleId): Module|Feature
    {
        return DB::transaction(function () use ($version, $kind, $name, $parentModuleId): Module|Feature {
            if ($kind === 'feature') {
                $feature = $this->createFeature->handle(['name' => $name, 'module_id' => (int) $parentModuleId]);
                $this->attachCatalogItems->handle([$version->id], [], [$feature->id]);

                return $feature;
            }

            $module = $this->createModule->handle(['name' => $name, 'parent_id' => $parentModuleId]);
            $this->attachCatalogItems->handle([$version->id], [$module->id]);

            return $module;
        });
    }
}
