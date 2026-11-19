<?php

use App\Actions\Reports\GenerateReport;
use App\Enums\RepositoryRole;
use App\Models\Customer;
use App\Models\Environment;
use App\Models\Product;
use App\Models\ReportGeneration;
use App\Models\Repository;
use App\Models\Technology;
use App\Models\User;
use App\Models\Version;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Text of the main part of a generated DOCX; fails if the XML is not well formed.
 */
function docxText(string $path): string
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();

    return (string) preg_replace('/\s+/u', ' ', strip_tags(str_replace(['</w:p>', '</w:tc>'], [' ¶ ', ' | '], $xml)));
}

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['name' => 'Awa Koné']);
    $this->product = Product::factory()->create(['name' => 'Satis Macro', 'code' => 'MACRO']);
    $this->customer = Customer::factory()->create(['name' => 'Banque Alpha']);
    $this->version = Version::factory()->for($this->product)->for($this->customer)->create(['name' => 'Macro Alpha']);
    $this->repository = Repository::factory()->for($this->version)->create([
        'path' => 'satis/macro-alpha-api',
        'role' => RepositoryRole::Backend,
        'web_url' => 'https://gitlab.example.test/satis/macro-alpha-api',
        'stack' => ['php' => '7.4.33'],
    ]);
    Environment::factory()->for($this->version)->create([
        'repository_id' => $this->repository->id,
        'name' => 'Production Alpha',
        'deployed_ref' => 'a1b2c3d4',
        'database_engine' => 'MySQL',
        'database_version' => '5.7',
    ]);
    Technology::factory()->create(['component' => 'php', 'cycle' => '7.4', 'end_of_support' => '2022-11-28']);

    $this->generate = fn (?int $customerId = null): ReportGeneration => app(GenerateReport::class)
        ->handle('L1', new ReportScope($this->product->id, $customerId), $this->user);
});

it('generates the L1 Word document and its XLSX annex from the central data', function () {
    $generation = ($this->generate)();

    expect($generation->documentId())->toBe(sprintf('L1-%04d', $generation->id))
        ->and($generation->statusLabel())->toBe('Brouillon')
        ->and($generation->version_number)->toBe(1)
        ->and($generation->created_by)->toBe($this->user->id);

    Storage::disk('local')->assertExists([$generation->docx_path, $generation->xlsx_path]);
    expect($generation->docx_sha256)->toBe(hash_file('sha256', Storage::disk('local')->path($generation->docx_path)));

    $text = docxText(Storage::disk('local')->path($generation->docx_path));
    expect($text)
        ->not->toContain('{{')
        ->toContain($generation->documentId())
        ->toContain('Satis Macro (MACRO)')
        ->toContain('Tous les clients')
        ->toContain('Awa Koné')
        ->toContain('Brouillon')
        ->toContain($this->version->displayCode())
        ->toContain($this->customer->displayCode().' · Banque Alpha')
        ->toContain('satis/macro-alpha-api (php 7.4.33)')
        ->toContain('https://gitlab.example.test/satis/macro-alpha-api')
        ->toContain('a1b2c3d4')
        ->toContain('MySQL 5.7')
        ->toContain('php 7.4.33, hors support');
});

it('shows missing data as "À confirmer" and lists it in the pending items', function () {
    $generation = ($this->generate)();
    $text = docxText(Storage::disk('local')->path($generation->docx_path));

    expect($text)
        ->toContain('À confirmer')
        ->toContain('Applications — Responsable technique : 1 valeur à confirmer')
        ->toContain('Applications — Frontend : 1 valeur à confirmer')
        ->and($generation->missing_count)->toBeGreaterThan(0)
        ->and((float) $generation->completeness)->toBeLessThan(100.0);
});

it('escapes user data and never takes it for a template variable', function () {
    $this->version->update(['name' => 'Alpha <b>& "Co" {{document.id}}']);

    $generation = ($this->generate)();
    $text = docxText(Storage::disk('local')->path($generation->docx_path));

    expect(html_entity_decode($text, ENT_XML1 | ENT_QUOTES))->toContain("Alpha <b>& \"Co\" {\u{200B}{document.id}}");
});

it('gives the same data hash to the same data and numbers the versions per scope', function () {
    $first = ($this->generate)();
    $second = ($this->generate)();

    expect($second->data_sha256)->toBe($first->data_sha256)
        ->and($second->version_number)->toBe(2)
        ->and($second->documentId())->not->toBe($first->documentId());

    $this->repository->update(['stack' => ['php' => '8.3.1']]);
    $third = ($this->generate)();
    $narrowed = ($this->generate)($this->customer->id);

    expect($third->data_sha256)->not->toBe($first->data_sha256)
        ->and($third->version_number)->toBe(3)
        ->and($narrowed->version_number)->toBe(1);
});

it('keeps a generated report immutable', function () {
    $generation = ($this->generate)();

    expect(fn () => $generation->update(['title' => 'Autre']))->toThrow(LogicException::class)
        ->and(fn () => $generation->delete())->toThrow(LogicException::class);
});

it('restricts the report to the versions of the chosen customer', function () {
    Version::factory()->for($this->product)->create(['name' => 'Macro Beta']);

    $text = docxText(Storage::disk('local')->path(($this->generate)($this->customer->id)->docx_path));

    expect($text)->toContain('Macro Alpha')->toContain('Banque Alpha')->not->toContain('Macro Beta');
});

it('caps long tables in the Word document and keeps every row in the XLSX annex', function () {
    config(['reports.docx_max_rows' => 1]);
    Version::factory()->for($this->product)->create(['name' => 'Macro Beta']);

    $generation = ($this->generate)();
    $text = docxText(Storage::disk('local')->path($generation->docx_path));
    expect($text)->toContain('Extrait : 1 premières lignes sur 2');

    $spreadsheet = IOFactory::load(Storage::disk('local')->path($generation->xlsx_path));
    expect($spreadsheet->getSheetNames())->toBe(['Contrôle', 'Applications', 'Déploiements'])
        ->and($spreadsheet->getSheetByName('Applications')?->getHighestRow())->toBe(3)
        ->and($spreadsheet->getSheetByName('Contrôle')?->getCell('B2')->getValue())->toBe($generation->documentId());
});

it('refuses a deliverable that is not exportable yet', function () {
    app(GenerateReport::class)->handle('L4', new ReportScope($this->product->id), $this->user);
})->throws(InvalidArgumentException::class);
