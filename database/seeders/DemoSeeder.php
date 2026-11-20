<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateFeature;
use App\Actions\Catalog\CreateModule;
use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Referential\SaveDeployment;
use App\Actions\Referential\SetReferenceVersion;
use App\Enums\ComponentType;
use App\Enums\EnvironmentType;
use App\Enums\ImplementationStatus;
use App\Enums\IntegrationProtocol;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Enums\RepositoryRole;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\SnippetKind;
use App\Enums\VerificationStatus;
use App\Models\BusinessRule;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Environment;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Integration;
use App\Models\Issue;
use App\Models\Module;
use App\Models\Product;
use App\Models\Release;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\Version;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

/**
 * Fictitious demo data to try the platform: php artisan db:seed --class=DemoSeeder
 * Customers and figures are invented; do not run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(CreateModule $createModule, CreateFeature $createFeature, AttachCatalogItems $attach, SetReferenceVersion $setReference, SaveDeployment $saveDeployment): void
    {
        $macro = Product::query()->create(['code' => 'MACRO', 'name' => 'Satis Macro', 'description' => 'Solution de gestion pour la microfinance.']);
        Product::query()->create(['code' => 'PRO', 'name' => 'Satis Pro', 'description' => 'Solution pour les banques.']);

        $customerA = Customer::query()->create(['name' => 'Banque Démo A', 'country' => 'Pays A']);
        $customerB = Customer::query()->create(['name' => 'Institution Démo B', 'country' => 'Pays B']);

        $baseline = Version::query()->create(['product_id' => $macro->id, 'name' => 'Socle Macro', 'status' => 'in_production']);
        $versionA = Version::query()->create(['product_id' => $macro->id, 'customer_id' => $customerA->id, 'name' => 'Satis Macro – Banque Démo A', 'status' => 'in_production', 'is_historical_pivot' => true]);
        $versionB = Version::query()->create(['product_id' => $macro->id, 'customer_id' => $customerB->id, 'name' => 'Satis Macro – Institution Démo B', 'status' => 'in_testing']);
        $setReference->handle($macro, $baseline);

        // A future perspective: listed apart, never counted as a production application.
        Version::query()->create(['product_id' => $macro->id, 'name' => 'Satis Macro SaaS', 'status' => 'future']);

        $apiA = Repository::query()->create([
            'version_id' => $versionA->id, 'path' => 'satis/macro-demo-a-api', 'role' => RepositoryRole::Backend,
            'production_branch' => 'main', 'active_branches' => ['main', 'develop'], 'stack' => ['php' => '8.1.27', 'laravel' => '9.52.16'],
        ]);
        $frontA = Repository::query()->create([
            'version_id' => $versionA->id, 'path' => 'satis/macro-demo-a-front', 'role' => RepositoryRole::Frontend,
            'production_branch' => 'main', 'active_branches' => ['main'], 'stack' => ['react' => '17.0.2', 'node' => '16.20.0'],
        ]);

        $productionA = Environment::query()->create([
            'version_id' => $versionA->id, 'repository_id' => $apiA->id, 'type' => EnvironmentType::Production, 'name' => 'Production',
            'database_engine' => 'MySQL', 'database_version' => '5.7', 'php_version' => '8.1.27',
        ]);
        $testingA = Environment::query()->create([
            'version_id' => $versionA->id, 'repository_id' => $apiA->id, 'type' => EnvironmentType::Testing, 'name' => 'Recette',
            'database_engine' => 'MySQL', 'database_version' => '8.0', 'php_version' => '8.1.27',
        ]);
        $release23 = Release::query()->create(['repository_id' => $apiA->id, 'name' => 'v2.3.0', 'commit_sha' => '4f1c2e9a7b3d5f60812c3d4e5f6a7b8c9d0e1f2a', 'released_on' => '2026-06-15']);
        $release24 = Release::query()->create(['repository_id' => $apiA->id, 'name' => 'v2.4.0', 'commit_sha' => '9a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3e2f1a0b', 'released_on' => '2026-10-02']);
        $saveDeployment->handle(['environment_id' => $productionA->id, 'release_id' => $release23->id, 'commit_sha' => null, 'deployed_at' => '2026-06-20 21:00', 'verification_status' => VerificationStatus::Verified, 'evidence' => 'Contrôle de la version affichée sur le serveur de production', 'notes' => null]);
        $saveDeployment->handle(['environment_id' => $testingA->id, 'release_id' => $release24->id, 'commit_sha' => null, 'deployed_at' => '2026-10-05 10:30', 'verification_status' => VerificationStatus::ToVerify, 'evidence' => null, 'notes' => null]);

        $this->seedArchitecture($versionA, $apiA, $frontA);

        foreach ([['php', '8.1', '2025-12-31'], ['php', '8.3', '2027-12-31'], ['laravel', '9', '2024-02-06'], ['laravel', '12', '2027-02-24'], ['node', '16', '2023-09-11'], ['mysql', '5.7', '2023-10-31'], ['mysql', '8.0', '2026-04-30']] as [$component, $cycle, $end]) {
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

        $this->seedQualification($versionA, $versionB);
    }

    private function seedQualification(Version $versionA, Version $versionB): void
    {
        $implementationOf = fn (Version $version, string $featureName): Implementation => $version->implementations()
            ->whereHas('feature', fn ($query) => $query->where('name', $featureName))->sole();

        $penaltiesA = $implementationOf($versionA, 'Pénalités de retard');
        $penaltiesA->update(['deviation' => "Les pénalités sont calculées **par jour calendaire** au lieu de jours ouvrés.\n\nÀ valider avec le métier."]);

        $rule = BusinessRule::query()->create([
            'implementation_id' => $penaltiesA->id,
            'title' => 'Plafond des pénalités',
            'description' => 'Le total des pénalités ne peut pas dépasser 10 % du capital restant dû.',
            'source' => RuleSource::Code,
            'status' => RuleStatus::Validated,
        ]);
        $rule->codeSnippets()->create([
            'kind' => SnippetKind::Finding,
            'language' => 'php',
            'path' => 'app/Services/PenaltyCalculator.php',
            'line_start' => 41,
            'line_end' => 44,
            'content' => "\$penalty = \$daysLate * \$dailyRate * \$remaining;\n\$cap = \$remaining * 0.10;\n\nreturn min(\$penalty, \$cap);",
        ]);

        $issue = Issue::query()->create([
            'title' => 'Arrondi des échéances au centime inférieur',
            'description' => 'Les montants des échéances sont tronqués au lieu d\'être arrondis, ce qui crée un écart sur la dernière échéance.',
            'type' => IssueType::Bug,
            'severity' => IssueSeverity::Major,
            'proposed_solution' => 'Utiliser un arrondi bancaire et reporter le reliquat sur la dernière échéance.',
            'impact' => 'La dernière échéance de chaque crédit est fausse de quelques centimes.',
            'effort_min_days' => 1,
            'effort_max_days' => 2,
            'confidence' => 'medium',
        ]);
        $issue->implementations()->attach([
            $implementationOf($versionA, 'Échéancier')->id,
            $implementationOf($versionB, 'Échéancier')->id,
        ]);

        $security = Issue::query()->create([
            'title' => 'Contrôle d\'accès absent sur l\'export des clients',
            'description' => 'La route d\'export n\'applique pas le middleware d\'autorisation.',
            'type' => IssueType::Security,
            'severity' => IssueSeverity::Blocking,
            'impact' => 'N’importe quel utilisateur connecté peut exporter tous les clients.',
            'effort_min_days' => 0.5,
            'effort_max_days' => 0.5,
            'confidence' => 'high',
        ]);
        $security->implementations()->attach($implementationOf($versionA, 'Recherche')->id);

        $this->seedWorkflow($versionA, $rule);
    }

    /**
     * A business workflow whose steps point to catalog features, one of them with a rule (SPEC-001 L2).
     */
    private function seedWorkflow(Version $version, BusinessRule $penaltyRule): void
    {
        $workflow = Workflow::query()->create([
            'product_id' => $version->product_id,
            'name' => 'Octroi et suivi d\'un crédit',
            'description' => 'De la simulation demandée par le client jusqu\'au suivi des remboursements.',
        ]);

        $feature = fn (string $name): ?int => Feature::query()->where('name', $name)->value('id');
        $steps = [
            ['Simulation de l\'offre', 'Conseiller', 'Simulation'],
            ['Constitution du dossier', 'Conseiller', 'Montage du dossier'],
            ['Comité : crédit accordé ?', 'Comité de crédit', 'Comité de crédit', 'decision'],
            ['Suivi des échéances', 'Back-office', 'Échéancier'],
            ['Recouvrement des retards', 'Recouvrement', 'Pénalités de retard'],
            ['Crédit soldé', null, null, 'end'],
            ['Notifier le refus', 'Conseiller', null, 'end'],
        ];

        $created = [];
        foreach ($steps as $index => $definition) {
            [$name, $actor, $featureName] = $definition;
            $step = $workflow->steps()->create([
                'position' => $index + 1, 'kind' => $definition[3] ?? 'action', 'name' => $name, 'actor' => $actor,
                'feature_id' => $featureName !== null ? $feature($featureName) : null,
            ]);
            $created[$name] = $step;

            if ($featureName === 'Pénalités de retard') {
                $step->businessRules()->attach($penaltyRule->id);
            }
        }

        // Conditions (RG-17): granted, refused, or back to the file when it is incomplete.
        foreach ([['Oui', 'Suivi des échéances'], ['Non', 'Notifier le refus'], ['Dossier incomplet', 'Constitution du dossier']] as $position => [$label, $target]) {
            $created['Comité : crédit accordé ?']->transitions()->create(['target_step_id' => $created[$target]->id, 'label' => $label, 'position' => $position]);
        }
    }

    /**
     * Components and integrations of an application (SPEC-001 L3).
     */
    private function seedArchitecture(Version $version, Repository $api, Repository $front): void
    {
        $component = fn (string $name, ComponentType $type, ?Repository $repository, ?string $technology, ?string $technologyVersion): Component => Component::query()->create([
            'version_id' => $version->id, 'repository_id' => $repository?->id, 'name' => $name, 'type' => $type,
            'technology' => $technology, 'technology_version' => $technologyVersion,
        ]);

        $backend = $component('API métier', ComponentType::Api, $api, 'Laravel', '9.52.16');
        $web = $component('Front agences', ComponentType::Web, $front, 'React', '17.0.2');
        $batch = $component('Batch de clôture journalière', ComponentType::Batch, $api, 'Laravel', '9.52.16');
        $database = $component('Base de production', ComponentType::Database, null, 'MySQL', '5.7');
        $backend->applyVerificationStatus(VerificationStatus::Verified);
        $backend->save();

        Integration::query()->create(['source_component_id' => $web->id, 'target_component_id' => $backend->id, 'protocol' => IntegrationProtocol::Rest, 'description' => 'Toutes les opérations des agences.']);
        Integration::query()->create(['source_component_id' => $backend->id, 'target_component_id' => $database->id, 'protocol' => IntegrationProtocol::Database]);
        Integration::query()->create(['source_component_id' => $backend->id, 'external_system' => 'Opérateur de paiement mobile', 'protocol' => IntegrationProtocol::Rest, 'description' => 'Décaissements et remboursements par mobile money.']);
        Integration::query()->create(['source_component_id' => $batch->id, 'external_system' => 'Centrale des risques', 'protocol' => IntegrationProtocol::File, 'description' => 'Déclaration mensuelle des encours.']);
    }
}
