<?php

namespace Database\Seeders;

use App\Actions\Implementations\AttachCatalogItems;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Performance check data (ENF-05, ENF-06): about 2 100 catalog rows attached to 20 versions,
 * i.e. 42 000 implementations. php artisan db:seed --class=VolumeSeeder. Never in production.
 */
class VolumeSeeder extends Seeder
{
    public function run(AttachCatalogItems $attach): void
    {
        $product = Product::query()->create(['code' => 'VOLUME', 'name' => 'Produit volumétrie']);
        $versionIds = [];
        for ($v = 1; $v <= 20; $v++) {
            $versionIds[] = Version::query()->create(['product_id' => $product->id, 'name' => sprintf('Version %02d', $v)])->id;
        }
        $product->update(['reference_version_id' => $versionIds[0]]);

        $now = now();
        $rootIds = [];
        for ($m = 1; $m <= 100; $m++) {
            $root = Module::query()->create(['name' => "Module {$m}", 'position' => $m]);
            $rootIds[] = $root->id;

            for ($s = 1; $s <= 4; $s++) {
                $sub = Module::query()->create(['name' => "Sous-module {$m}.{$s}", 'parent_id' => $root->id, 'position' => $s]);
                $features = [];
                for ($f = 1; $f <= 4; $f++) {
                    $features[] = ['module_id' => $sub->id, 'name' => "Fonctionnalité {$m}.{$s}.{$f}", 'position' => $f, 'created_at' => $now, 'updated_at' => $now];
                }
                Feature::query()->insert($features);
            }
        }

        $attach->handle($versionIds, $rootIds);

        DB::statement("UPDATE implementations SET status = ELT(1 + ((id * 7 + version_id) % 5), 'not_assessed', 'operational', 'needs_improvement', 'defective', 'missing') WHERE version_id IN (".implode(',', $versionIds).')');
        DB::statement("UPDATE implementations SET deviation = 'Écart de démonstration' WHERE id % 11 = 0 AND version_id IN (".implode(',', $versionIds).')');
    }
}
