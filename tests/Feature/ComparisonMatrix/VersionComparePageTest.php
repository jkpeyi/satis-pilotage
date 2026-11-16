<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Livewire\Versions\Compare;
use App\Models\Feature;
use App\Models\Module;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->credit = Module::factory()->create(['name' => 'Crédit']);
    $this->simulation = Feature::factory()->for($this->credit)->create(['name' => 'Simulation']);
    $this->epargne = Module::factory()->create(['name' => 'Épargne']);
    $this->a = Version::factory()->create(['name' => 'Banque A']);
    $this->b = Version::factory()->for($this->a->product)->create(['name' => 'Banque B']);
    app(AttachCatalogItems::class)->handle([$this->a->id], [$this->credit->id, $this->epargne->id]);
    app(AttachCatalogItems::class)->handle([$this->b->id], [$this->credit->id]);
    $this->b->implementations()->where('feature_id', $this->simulation->id)->update(['status' => ImplementationStatus::Defective->value]);
});

it('renders the comparison page', function () {
    $this->get(route('versions.compare', ['a' => $this->a->id, 'b' => $this->b->id]))
        ->assertOk()
        ->assertSee('Uniquement dans A')
        ->assertSee('Épargne');
});

it('shows each section and swaps the versions', function () {
    Livewire::withQueryParams(['a' => (string) $this->a->id, 'b' => (string) $this->b->id])
        ->test(Compare::class)
        ->assertViewHas('diff', fn (array $diff) => array_column($diff['onlyInA'], 'name') === ['Épargne']
            && $diff['onlyInB'] === []
            && array_column($diff['different'], 'name') === ['Simulation'])
        ->call('swap')
        ->assertSet('a', (string) $this->b->id)
        ->assertViewHas('diff', fn (array $diff) => array_column($diff['onlyInB'], 'name') === ['Épargne']);
});

it('asks for two different versions', function () {
    Livewire::withQueryParams(['a' => (string) $this->a->id, 'b' => (string) $this->a->id])
        ->test(Compare::class)
        ->assertSee('Choisissez deux versions différentes.');
});

it('opens the detailed comparison of a different item', function () {
    Livewire::withQueryParams(['a' => (string) $this->a->id, 'b' => (string) $this->b->id])
        ->test(Compare::class)
        ->call('compareRow', 'feature:'.$this->simulation->id)
        ->assertSet('showComparison', true)
        ->assertSee('Défaillant');
});

it('exports the three sections to Excel', function () {
    Livewire::withQueryParams(['a' => (string) $this->a->id, 'b' => (string) $this->b->id])
        ->test(Compare::class)
        ->call('export')
        ->assertFileDownloaded('differences-banque-a-banque-b.xlsx');
});
