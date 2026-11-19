<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Actions\Reports\GenerateReport;
use App\Enums\ImplementationStatus;
use App\Enums\RuleSource;
use App\Enums\RuleStatus;
use App\Enums\VerificationStatus;
use App\Enums\VersionStatus;
use App\Models\BusinessRule;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\User;
use App\Models\Version;
use App\Models\Workflow;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->credit = Module::factory()->create(['name' => 'Crédit']);
    $this->granting = Module::factory()->create(['name' => 'Octroi', 'parent_id' => $this->credit->id]);
    $this->simulation = Feature::factory()->for($this->granting)->create(['name' => 'Simulation', 'standard_description' => 'Calcule **les échéances**.']);
    $this->scoring = Feature::factory()->for($this->credit)->create(['name' => 'Scoring', 'standard_description' => null]);

    $this->alpha = Version::factory()->for($this->product)->for(Customer::factory()->create(['name' => 'Banque Alpha']))->create(['name' => 'Macro Alpha']);
    $this->beta = Version::factory()->for($this->product)->create(['name' => 'Macro Beta']);
    $this->saas = Version::factory()->for($this->product)->create(['name' => 'Satis_SaaS', 'customer_id' => null, 'status' => VersionStatus::Future]);
    app(AttachCatalogItems::class)->handle([$this->alpha->id, $this->beta->id, $this->saas->id], [$this->credit->id]);

    $implementation = fn (Version $version, Feature $feature) => $version->implementations()->where('feature_id', $feature->id)->sole();
    $verified = $implementation($this->alpha, $this->simulation);
    $verified->forceFill(['status' => ImplementationStatus::Partial, 'deviation' => 'Taux **mensuel**.']);
    $verified->applyVerificationStatus(VerificationStatus::Verified);
    $verified->save();
    $implementation($this->beta, $this->scoring)->update(['status' => ImplementationStatus::Absent]);

    $this->rule = BusinessRule::query()->create([
        'implementation_id' => $verified->id, 'title' => 'Taux plafond', 'description' => 'Plafond',
        'source' => RuleSource::Code, 'status' => RuleStatus::Pending,
    ]);
    $workflow = Workflow::factory()->for($this->product)->create(['name' => 'Octroi de crédit']);
    $step = $workflow->steps()->create(['position' => 1, 'name' => 'Simulation', 'actor' => 'Conseiller', 'feature_id' => $this->simulation->id]);
    $step->businessRules()->attach($this->rule->id);
    $workflow->steps()->create(['position' => 2, 'name' => 'Décision']);

    $this->generate = fn (?int $customerId = null): ReportGeneration => app(GenerateReport::class)
        ->handle('L2', new ReportScope($this->product->id, $customerId), $this->user);
    $this->sections = fn (ReportGeneration $generation): array => json_decode($generation->snapshot, true)['sections'];
});

it('lists the product catalog with module, sub-module and plain standard description', function () {
    $rows = ($this->sections)(($this->generate)())['features.table']['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'feature_id' => $this->simulation->displayCode(),
            'module' => 'Crédit',
            'submodule' => 'Octroi',
            'label' => 'Simulation',
            'description' => 'Calcule les échéances.',
            'source_sheet' => null,
            'source_row' => null,
        ])
        ->and($rows[1])->toMatchArray(['label' => 'Scoring', 'submodule' => 'Sans objet', 'description' => null]);
});

it('gives each customer variant its presence, operation and verification, without the future perspective', function () {
    $rows = collect(($this->sections)(($this->generate)())['feature_implementations.table']['rows']);

    expect($rows->pluck('application_id')->unique()->values()->all())->toBe([$this->alpha->displayCode(), $this->beta->displayCode()])
        ->and($rows->firstWhere('application_id', $this->alpha->displayCode()))->toMatchArray([
            'label' => 'Simulation',
            'presence_status' => 'Présent',
            'operational_status' => 'Partiel',
            'deviation' => 'Taux mensuel.',
            'verification_status' => 'Vérifié',
            'verified_by' => 'Awa Koné',
        ])
        ->and($rows->where('application_id', $this->beta->displayCode())->firstWhere('label', 'Scoring'))->toMatchArray([
            'presence_status' => 'Absent',
            'operational_status' => 'Non applicable',
            'verified_by' => null,
        ]);
});

it('describes the workflows with the rules of each step, and the gaps', function () {
    $generation = ($this->generate)();
    $sections = ($this->sections)($generation);

    expect($sections['workflows.table']['rows'][0])->toMatchArray([
        'step' => '1. Simulation',
        'actor' => 'Conseiller',
        'feature' => $this->simulation->displayCode().' · Crédit › Octroi › Simulation',
        'business_rules' => $this->rule->displayCode().' Taux plafond',
    ])
        ->and($sections['workflows.table']['rows'][1]['feature'])->toBeNull();

    $gaps = collect($sections['functional.gaps']['blocks'])->pluck('text')->implode("\n");
    expect($gaps)
        ->toContain($this->beta->displayCode().' · Macro Beta : 1 absente')
        ->toContain($this->scoring->displayCode().' · Crédit › Scoring — '.$this->beta->displayCode())
        ->toContain('1 règle métier en attente de validation');

    $text = collect($sections['functional.source_references']['blocks'])->pluck('text')->implode("\n");
    expect($text)->toContain('937 observations')->toContain('Code : 1');

    expect($generation->docx_path)->not->toBeNull()
        ->and($generation->completeness)->toBeLessThan(100)
        ->and(collect(json_decode($generation->snapshot, true)['sections'])->keys()->all())
        ->toBe(['functional.scope', 'features.table', 'feature_implementations.table', 'workflows.table', 'functional.gaps', 'functional.source_references']);
});

it('narrows the variants to the chosen customer', function () {
    $rows = collect(($this->sections)(($this->generate)($this->alpha->customer_id))['feature_implementations.table']['rows']);

    expect($rows->pluck('application_id')->unique()->all())->toBe([$this->alpha->displayCode()]);
});
