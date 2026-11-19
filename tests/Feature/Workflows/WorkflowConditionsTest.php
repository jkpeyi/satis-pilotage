<?php

use App\Actions\Reports\GenerateReport;
use App\Actions\Workflows\ArchiveWorkflowStep;
use App\Actions\Workflows\SaveWorkflowStep;
use App\Enums\WorkflowStepKind;
use App\Livewire\Workflows;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Queries\WorkflowGraph;
use App\Reports\ReportScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->workflow = Workflow::factory()->create(['name' => 'Traitement des réclamations']);
    $this->save = fn (string $name, array $data = [], ?WorkflowStep $step = null): WorkflowStep => app(SaveWorkflowStep::class)->handle([
        'workflow_id' => $this->workflow->id, 'kind' => WorkflowStepKind::Action, 'name' => $name, 'actor' => null,
        'feature_id' => null, 'description' => null, 'business_rule_ids' => [], 'transitions' => [], ...$data,
    ], $step);

    // 1 Enregistrer → 2 Fondée ? → Oui: 3 Traiter → 5 Clôturer (fin) ; Non: 4 Rejeter → 5 ; Incomplète: retour à 1.
    $this->register = ($this->save)('Enregistrer la réclamation');
    $this->check = ($this->save)('Réclamation fondée ?');
    $this->handle = ($this->save)('Traiter la réclamation');
    $this->reject = ($this->save)('Notifier le rejet');
    $this->close = ($this->save)('Clôturer', ['kind' => WorkflowStepKind::End]);
    ($this->save)('Réclamation fondée ?', ['kind' => WorkflowStepKind::Decision, 'transitions' => [
        ['label' => 'Oui', 'target_step_id' => $this->handle->id],
        ['label' => 'Non', 'target_step_id' => $this->reject->id],
        ['label' => 'Incomplète', 'target_step_id' => $this->register->id],
    ]], $this->check);
    ($this->save)('Traiter la réclamation', ['transitions' => [['label' => null, 'target_step_id' => $this->close->id]]], $this->handle);
});

it('RG-17: describes a decision with its labelled exits, implicit next steps and a loop back', function () {
    $graph = WorkflowGraph::for($this->workflow);

    expect($graph->exitsLabel($this->check->id))->toBe('Oui → 3. Traiter la réclamation ; Non → 4. Notifier le rejet ; Incomplète → 1. Enregistrer la réclamation')
        ->and($graph->exitsLabel($this->register->id))->toBe('→ 2. Réclamation fondée ? (suite)')
        ->and($graph->exitsLabel($this->handle->id))->toBe('→ 5. Clôturer')
        ->and($graph->exitsLabel($this->reject->id))->toBe('→ 5. Clôturer (suite)')
        ->and($graph->exitsLabel($this->close->id))->toBe('')
        ->and($graph->warnings)->toBe([]);

    $layout = $graph->layout();
    $nodes = collect($layout['nodes'])->keyBy('id');
    expect($nodes[$this->check->id]['kind'])->toBe('decision')
        ->and($nodes[$this->handle->id]['y'])->toBe($nodes[$this->reject->id]['y'])
        ->and($nodes[$this->close->id]['y'])->toBeGreaterThan($nodes[$this->handle->id]['y'])
        ->and(collect($layout['edges'])->where('loop', true)->count())->toBe(1)
        ->and(collect($layout['edges'])->pluck('label')->filter()->values()->all())->toBe(['Oui', 'Non', 'Incomplète']);
});

it('RG-17: refuses exits that do not fit the kind of step', function () {
    expect(fn () => ($this->save)('Fin', ['kind' => WorkflowStepKind::End, 'transitions' => [['label' => null, 'target_step_id' => $this->register->id]]], $this->close))
        ->toThrow(DomainException::class, 'Une étape de fin n’a pas de sortie.')
        ->and(fn () => ($this->save)('Traiter', ['transitions' => [['label' => null, 'target_step_id' => $this->close->id], ['label' => null, 'target_step_id' => $this->reject->id]]], $this->handle))
        ->toThrow(DomainException::class, 'utilisez une décision')
        ->and(fn () => ($this->save)('Fondée ?', ['kind' => WorkflowStepKind::Decision, 'transitions' => [['label' => ' ', 'target_step_id' => $this->handle->id]]], $this->check))
        ->toThrow(DomainException::class, 'indique sa condition')
        ->and(fn () => ($this->save)('Traiter', ['transitions' => [['label' => null, 'target_step_id' => $this->handle->id]]], $this->handle))
        ->toThrow(DomainException::class, 'mener à elle-même')
        ->and(fn () => ($this->save)('Traiter', ['transitions' => [['label' => null, 'target_step_id' => WorkflowStep::factory()->create()->id]]], $this->handle))
        ->toThrow(DomainException::class, 'n’appartient pas à ce workflow');
});

it('points out incomplete decisions, unreachable steps and exits to archived steps', function () {
    $orphan = ($this->save)('Archiver le dossier');
    ($this->save)('Clôturer', ['kind' => WorkflowStepKind::End], $this->close);
    app(ArchiveWorkflowStep::class)->handle($this->reject);
    ($this->save)('Escalader ?', ['kind' => WorkflowStepKind::Decision, 'transitions' => [['label' => 'Oui', 'target_step_id' => $this->close->id]]]);

    expect(WorkflowGraph::for($this->workflow)->warnings)->toBe([
        'Étape 2 « Réclamation fondée ? » : une sortie mène à une étape archivée.',
        'Étape 6 « Escalader ? » : une décision a au moins deux sorties (si / sinon).',
        'Étape 5 « Archiver le dossier » : aucun chemin n’y mène depuis la première étape.',
        'Étape 6 « Escalader ? » : aucun chemin n’y mène depuis la première étape.',
    ])->and($orphan->exists)->toBeTrue();
});

it('edits a decision and its exits from the workflow page, and draws the diagram', function () {
    Livewire::test(Workflows\Show::class, ['workflow' => $this->workflow])
        ->assertSee('Schéma')
        ->assertSeeHtml('<polygon')
        ->assertSee('Incomplète')
        ->call('createStep')
        ->set('stepForm.name', 'Montant élevé ?')
        ->set('stepForm.kind', 'decision')
        ->assertSet('stepForm.transitions.0.label', 'Oui')
        ->assertSet('stepForm.transitions.1.label', 'Non')
        ->call('saveStep')
        ->assertHasErrors(['stepForm.transitions.0.target_step_id', 'stepForm.transitions.1.target_step_id'])
        ->set('stepForm.transitions.0.target_step_id', (string) $this->handle->id)
        ->set('stepForm.transitions.1.target_step_id', (string) $this->close->id)
        ->set('stepForm.transitions.1.label', 'Sinon')
        ->call('addTransition')
        ->call('removeTransition', 2)
        ->call('saveStep')
        ->assertHasNoErrors()
        ->assertSee('Oui → 3. Traiter la réclamation ; Sinon → 5. Clôturer');

    $step = $this->workflow->steps()->where('name', 'Montant élevé ?')->sole();
    expect($step->kind)->toBe(WorkflowStepKind::Decision)->and($step->transitions)->toHaveCount(2);

    Livewire::test(Workflows\Show::class, ['workflow' => $this->workflow])
        ->call('editStep', $step->id)
        ->set('stepForm.kind', 'action')
        ->call('saveStep')
        ->assertHasNoErrors();

    expect($step->refresh()->kind)->toBe(WorkflowStepKind::Action)
        ->and($step->transitions()->pluck('target_step_id')->all())->toBe([$this->handle->id])
        ->and($step->transitions()->value('label'))->toBeNull();
});

it('lists the exits in L2 and embeds the diagram of each workflow in the Word document', function () {
    Storage::fake('local');

    $generation = app(GenerateReport::class)
        ->handle('L2', new ReportScope($this->workflow->product_id), User::query()->firstOrFail());
    $snapshot = json_decode($generation->snapshot, true);
    $rows = collect($snapshot['sections']['workflows.table']['rows'])->keyBy('step');

    expect($rows['2. Réclamation fondée ?'])->toMatchArray([
        'kind' => 'Décision (si / sinon)',
        'exits' => 'Oui → 3. Traiter la réclamation ; Non → 4. Notifier le rejet ; Incomplète → 1. Enregistrer la réclamation',
        'feature' => 'Sans objet',
    ])
        ->and($rows['5. Clôturer']['kind'])->toBe('Fin')
        ->and($snapshot['figures']['workflows.table'])->toBe(['Schéma du workflow '.$this->workflow->displayCode().' · Traitement des réclamations']);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($generation->docx_path));
    $media = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => (string) $zip->getNameIndex($i))->filter(fn (string $name): bool => str_starts_with($name, 'word/media/'));
    $png = $zip->getFromName((string) $media->first());
    $document = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    expect($media)->toHaveCount(1)
        ->and(substr((string) $png, 1, 3))->toBe('PNG')
        ->and($document)->not->toContain('report_figure_')
        ->toContain('Schéma du workflow');
});
