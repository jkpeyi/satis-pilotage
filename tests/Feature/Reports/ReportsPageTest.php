<?php

use App\Actions\Reports\GenerateReport;
use App\Livewire\Reports\Index;
use App\Models\Customer;
use App\Models\ReportGeneration;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->version = Version::factory()->create();
});

it('renders the reports page with the exportable deliverables', function () {
    $this->actingAs($this->user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Registre des exports')
        ->assertSee('L1 — Cartographie du patrimoine applicatif')
        ->assertSee('(à venir)');
});

it('generates a report from the page', function () {
    $this->actingAs($this->user);

    Livewire::test(Index::class)
        ->set('form.code', 'L1')
        ->set('form.product_id', (string) $this->version->product_id)
        ->set('form.customer_id', (string) $this->version->customer_id)
        ->call('generate')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $generation = ReportGeneration::query()->sole();
    expect($generation->code)->toBe('L1')
        ->and($generation->scope)->toBe(['product_id' => $this->version->product_id, 'customer_id' => $this->version->customer_id]);
});

it('rejects a customer without version of the product and a deliverable not exportable yet', function () {
    $this->actingAs($this->user);

    Livewire::test(Index::class)
        ->set('form.code', 'L4')
        ->set('form.product_id', (string) $this->version->product_id)
        ->set('form.customer_id', (string) Customer::factory()->create()->id)
        ->call('generate')
        ->assertHasErrors(['form.code', 'form.customer_id']);

    expect(ReportGeneration::query()->count())->toBe(0);
});

it('downloads a generated file only for an authenticated user', function () {
    $generation = app(GenerateReport::class)->handle('L1', new ReportScope($this->version->product_id), $this->user);

    $this->get(route('reports.download', [$generation, 'docx']))->assertRedirect(route('login'));

    $this->actingAs($this->user)
        ->get(route('reports.download', [$generation, 'docx']))
        ->assertOk()
        ->assertDownload($generation->documentId().' Cartographie du patrimoine applicatif.docx');

    $this->get(route('reports.download', [$generation, 'xlsx']))->assertOk();
    $this->get(route('reports.download', [$generation, 'pdf']))->assertNotFound();
});

it('refuses to serve a file that no longer matches its hash', function () {
    $generation = app(GenerateReport::class)->handle('L1', new ReportScope($this->version->product_id), $this->user);
    Storage::disk('local')->put((string) $generation->docx_path, 'altéré');

    $this->actingAs($this->user)
        ->get(route('reports.download', [$generation, 'docx']))
        ->assertStatus(409);
});
