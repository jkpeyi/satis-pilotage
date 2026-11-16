<?php

use App\Actions\Implementations\AttachCatalogItems;
use App\Enums\ImplementationStatus;
use App\Livewire\ComparisonMatrix\Index;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = Product::factory()->create(['name' => 'Satis Macro']);
    $this->baseline = Version::factory()->baseline()->for($this->product)->create(['name' => 'Socle']);
    $this->client = Version::factory()->for($this->product)->create(['name' => 'Banque A']);
    $this->archived = Version::factory()->for($this->product)->create(['name' => 'Ancienne']);
    $this->archived->archive();
    $this->product->update(['reference_version_id' => $this->baseline->id]);
    $this->module = Module::factory()->create(['name' => 'Crédit']);
    $this->feature = Feature::factory()->for($this->module)->create(['name' => 'Simulation']);
    app(AttachCatalogItems::class)->handle([$this->baseline->id, $this->client->id], [$this->module->id]);
});

it('renders the matrix page', function () {
    $this->get(route('comparison.index', ['product' => $this->product->id]))
        ->assertOk()
        ->assertSee('Cartographie des écarts')
        ->assertSee('Banque A');
});

it('RG-08: selects the active versions of the product with the reference version as pivot', function () {
    Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->assertSet('versionIds', [(string) $this->baseline->id, (string) $this->client->id])
        ->assertSet('pivot', (string) $this->baseline->id);
});

it('EF-302: serves compact cells to the browser', function () {
    $this->client->implementations()->where('feature_id', $this->feature->id)->sole()
        ->update(['status' => ImplementationStatus::Defective, 'deviation' => 'Écart']);

    $payload = Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->instance()
        ->payload();

    $cell = $payload['cells']['feature:'.$this->feature->id][$this->client->id];
    expect($payload['pivot'])->toBe($this->baseline->id)
        ->and(array_column($payload['versions'], 'name'))->toBe(['Socle', 'Banque A'])
        ->and(array_slice($cell, 1))->toBe([3, 1, 0, 0]);
});

it('keeps the pivot among the selected versions', function () {
    Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->set('versionIds', [(string) $this->client->id])
        ->assertSet('pivot', (string) $this->client->id)
        ->assertDispatched('matrix-reload');
});

it('ignores versions of another product passed in the URL', function () {
    $foreign = Version::factory()->create();

    $payload = Livewire::withQueryParams(['product' => (string) $this->product->id, 'versions' => [(string) $this->client->id, (string) $foreign->id]])
        ->test(Index::class)
        ->instance()
        ->payload();

    expect(array_column($payload['versions'], 'id'))->toBe([$this->client->id]);
});

it('EF-305: opens the panel of a cell', function () {
    $implementation = $this->client->implementations()->where('feature_id', $this->feature->id)->sole();
    $implementation->update(['deviation' => 'Le **taux** est mensuel.']);

    Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->call('openCell', $implementation->id)
        ->assertSeeHtml('<strong>taux</strong>')
        ->assertSee('Ouvrir la fiche')
        ->call('closePanel')
        ->assertDontSee('Ouvrir la fiche');
});

it('EF-306: compares one row across the selected versions', function () {
    Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->call('compareRow', 'feature:'.$this->feature->id)
        ->assertSet('showComparison', true)
        ->assertSeeInOrder(['Simulation', 'Socle', 'Banque A']);
});

it('EF-307: exports the visible rows to Excel', function () {
    Livewire::withQueryParams(['product' => (string) $this->product->id])
        ->test(Index::class)
        ->call('export', ['module:'.$this->module->id, 'feature:'.$this->feature->id], $this->baseline->id)
        ->assertFileDownloaded('cartographie-satis-macro-'.now()->format('Y-m-d').'.xlsx');
});
