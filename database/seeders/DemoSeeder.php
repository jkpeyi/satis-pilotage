<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Referential\SetReferenceVersion;
use App\Enums\ImplementationStatus;
use App\Enums\RepositoryRole;
use App\Models\Customer;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\Version;
use Illuminate\Database\Seeder;

/**
 * Fictitious demo data to try the platform: php artisan db:seed --class=DemoSeeder
 * Customers and figures are invented; do not run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(CreateModule $createModule, CreateFeature $createFeature, AttachCatalogItems $attach, SetReferenceVersion $setReference): void
    {
        $macro = Product::query()->create(['code' => 'MACRO', 'name' => 'Satis Macro', 'description' => 'Solution de gestion pour la microfinance.']);
        Product::query()->create(['code' => 'PRO', 'name' => 'Satis Pro', 'description' => 'Solution pour les banques.']);

        $customerA = Customer::query()->create(['name' => 'Banque Démo A', 'country' => 'Pays A']);
        $customerB = Customer::query()->create(['name' => 'Institution Démo B', 'country' => 'Pays B']);

        $baseline = Version::query()->create(['product_id' => $macro->id, 'name' => 'Socle Macro', 'status' => 'in_production']);
        $versionA = Version::query()->create(['product_id' => $macro->id, 'customer_id' => $customerA->id, 'name' => 'Satis Macro – Banque Démo A', 'status' => 'in_production']);
        $versionB = Version::query()->create(['product_id' => $macro->id, 'customer_id' => $customerB->id, 'name' => 'Satis Macro – Institution Démo B', 'status' => 'in_testing']);
        $setReference->handle($macro, $baseline);

        Repository::query()->create([
            'version_id' => $versionA->id, 'path' => 'satis/macro-demo-a-api', 'role' => RepositoryRole::Backend,
            'production_branch' => 'main', 'active_branches' => ['main', 'develop'], 'stack' => ['php' => '8.1.27', 'laravel' => '9.52.16'],
        ]);
        Repository::query()->create([
            'version_id' => $versionA->id, 'path' => 'satis/macro-demo-a-front', 'role' => RepositoryRole::Frontend,
            'production_branch' => 'main', 'active_branches' => ['main'], 'stack' => ['react' => '17.0.2', 'node' => '16.20.0'],
        ]);

        foreach ([['php', '8.1', '2025-12-31'], ['php', '8.3', '2027-12-31'], ['laravel', '9', '2024-02-06'], ['laravel', '12', '2027-02-24'], ['node', '16', '2023-09-11']] as [$component, $cycle, $end]) {
            Technology::query()->create(['component' => $component, 'cycle' => $cycle, 'end_of_support' => $end]);
        }

        $catalog = [
            'Clients' => ['Fiche client' => ['Création', 'Modification', 'Recherche'], 'KYC' => ['Pièces justificatives', 'Contrôle des listes']],
            'Crédit' => ['Octroi' => ['Simulation', 'Montage du dossier', 'Comité de crédit'], 'Remboursement' => ['Échéancier', 'Remboursement anticipé', 'Pénalités de retard']],
            'Épargne' => ['Comptes' => ['Ouverture', 'Clôture'], 'Opérations' => ['Dépôt', 'Retrait', 'Virement interne']],
            'Comptabilité' => ['Écritures' => ['Génération automatique', 'Saisie manuelle'], 'États' => ['Balance', 'Grand livre']],
        ];

        foreach ($catalog as $moduleName => $submodules) {
            $module = $createModule->handle(['name' => $moduleName, 'standard_description' => "Gestion du domaine {$moduleName}."]);
            $module->products()->sync([$macro->id]);

            foreach ($submodules as $submoduleName => $features) {
                $submodule = $createModule->handle(['name' => $submoduleName, 'parent_id' => $module->id]);

                foreach ($features as $featureName) {
                    $createFeature->handle(['name' => $featureName, 'module_id' => $submodule->id]);
                }
            }
        }

        $allModuleIds = Module::query()->whereNull('parent_id')->pluck('id')->all();
        $attach->handle([$baseline->id, $versionA->id], $allModuleIds);
        $attach->handle([$versionB->id], Module::query()->whereIn('name', ['Clients', 'Crédit'])->pluck('id')->all());

        $statuses = ImplementationStatus::cases();
        Implementation::query()->whereIn('version_id', [$versionA->id, $versionB->id])->get()
            ->each(function (Implementation $implementation, int $index) use ($statuses): void {
                $implementation->update(['status' => $statuses[$index % count($statuses)]]);
            });
        Implementation::query()->where('version_id', $baseline->id)->update(['status' => ImplementationStatus::Operational->value]);
    }
}
