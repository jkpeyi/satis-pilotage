<?php

use App\Livewire\Customers;
use App\Livewire\Products;
use App\Livewire\Technologies;
use App\Livewire\Versions;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\User;
use App\Models\Version;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('requires authentication for every référentiel page', function (string $url) {
    auth()->logout();

    $this->get($url)->assertRedirect(route('login'));
})->with(['/products', '/products/create', '/customers', '/versions', '/technologies']);

it('renders every référentiel page', function () {
    $version = Version::factory()->create();
    Repository::factory()->for($version)->create();
    Technology::factory()->create(['component' => 'php', 'cycle' => '8.1']);

    foreach ([
        route('products.index'), route('products.create'), route('products.show', $version->product), route('products.edit', $version->product),
        route('customers.index'), route('customers.create'), route('customers.edit', $version->customer),
        route('versions.index'), route('versions.create'), route('versions.show', $version), route('versions.edit', $version),
        route('technologies.index'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('creates a product and records its author', function () {
    Livewire::test(Products\Form::class)
        ->set('form.code', 'macro')
        ->set('form.name', 'Satis Macro')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->sole();
    expect($product->code)->toBe('MACRO')
        ->and($product->created_by)->toBe($this->user->id);
});

it('rejects a duplicate product code', function () {
    Product::factory()->create(['code' => 'MACRO']);

    Livewire::test(Products\Form::class)
        ->set('form.code', 'macro')
        ->set('form.name', 'Doublon')
        ->call('save')
        ->assertHasErrors(['form.code' => 'unique']);
});

it('edits a product', function () {
    $product = Product::factory()->create(['code' => 'PRO']);

    Livewire::test(Products\Form::class, ['product' => $product])
        ->assertSet('form.code', 'PRO')
        ->set('form.name', 'Satis Pro')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->refresh()->name)->toBe('Satis Pro');
});

it('sets and clears the reference version from the product page', function () {
    $version = Version::factory()->baseline()->create();

    Livewire::test(Products\Show::class, ['product' => $version->product])
        ->call('setReference', $version->id)
        ->assertHasNoErrors();
    expect($version->product->refresh()->reference_version_id)->toBe($version->id);

    Livewire::test(Products\Show::class, ['product' => $version->product->refresh()])
        ->call('clearReference');
    expect($version->product->refresh()->reference_version_id)->toBeNull();
});

it('archives and restores a product', function () {
    $product = Product::factory()->create();

    Livewire::test(Products\Show::class, ['product' => $product])->call('toggleArchive');
    expect($product->refresh()->isArchived())->toBeTrue();

    Livewire::test(Products\Index::class)->assertDontSee($product->name)
        ->set('showArchived', true)->assertSee($product->name);
});

it('creates a customer', function () {
    Livewire::test(Customers\Form::class)
        ->set('form.name', 'Banque Atlantique')
        ->set('form.country', "Côte d'Ivoire")
        ->call('save')
        ->assertHasNoErrors();

    expect(Customer::query()->sole()->country)->toBe("Côte d'Ivoire");
});

it('creates a baseline version without customer', function () {
    $product = Product::factory()->create();

    Livewire::test(Versions\Form::class)
        ->set('form.product_id', (string) $product->id)
        ->set('form.name', 'Socle Macro')
        ->call('save')
        ->assertHasNoErrors();

    expect(Version::query()->sole()->isBaseline())->toBeTrue();
});

it('rejects two versions with the same name in a product', function () {
    $version = Version::factory()->create(['name' => 'Banque X']);

    Livewire::test(Versions\Form::class)
        ->set('form.product_id', (string) $version->product_id)
        ->set('form.name', 'Banque X')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('filters versions by product and baseline', function () {
    $baseline = Version::factory()->baseline()->create();
    $customerVersion = Version::factory()->create(['product_id' => $baseline->product_id]);
    $otherProductVersion = Version::factory()->create();

    Livewire::test(Versions\Index::class)
        ->set('product', (string) $baseline->product_id)
        ->assertSee($customerVersion->name)
        ->assertDontSee($otherProductVersion->name)
        ->set('customer', 'none')
        ->assertSee($baseline->name)
        ->assertDontSee($customerVersion->name);
});

it('adds a repository with its stack from the version page', function () {
    $version = Version::factory()->create();

    Livewire::test(Versions\Show::class, ['version' => $version])
        ->call('createRepository')
        ->assertSet('showRepositoryForm', true)
        ->set('repositoryForm.path', 'satis/macro-x-api')
        ->set('repositoryForm.stack.0.version', '8.1.27')
        ->call('addStackRow')
        ->set('repositoryForm.stack.3.component', 'node')
        ->set('repositoryForm.stack.3.version', '16.20.0')
        ->call('saveRepository')
        ->assertHasNoErrors()
        ->assertSet('showRepositoryForm', false)
        ->assertDispatched('notify')
        ->assertSee('satis/macro-x-api');

    expect($version->repositories()->sole()->stack)->toEqual(['php' => '8.1.27', 'node' => '16.20.0']);
});

it('adds an environment linked to a repository of the version', function () {
    $repository = Repository::factory()->create();

    Livewire::test(Versions\Show::class, ['version' => $repository->version])
        ->call('createEnvironment')
        ->set('environmentForm.name', 'Production Abidjan')
        ->set('environmentForm.repository_id', (string) $repository->id)
        ->set('environmentForm.database_engine', 'Oracle')
        ->call('saveEnvironment')
        ->assertHasNoErrors();

    expect($repository->version->environments()->sole()->repository_id)->toBe($repository->id);
});

it('creates a technology cycle and rejects a duplicate', function () {
    Livewire::test(Technologies\Index::class)
        ->call('create')
        ->set('form.componentName', 'PHP')
        ->set('form.cycle', '8.1')
        ->set('form.end_of_support', '2025-12-31')
        ->call('save')
        ->assertHasNoErrors();

    expect(Technology::query()->sole()->component)->toBe('php');

    Livewire::test(Technologies\Index::class)
        ->call('create')
        ->set('form.componentName', 'php')
        ->set('form.cycle', '8.1')
        ->call('save')
        ->assertHasErrors(['form.cycle' => 'unique']);
});
