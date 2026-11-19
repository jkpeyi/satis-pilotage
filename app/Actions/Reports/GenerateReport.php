<?php

namespace App\Actions\Reports;

use App\Models\ReportGeneration;
use App\Models\User;
use App\Reports\Contracts\ReportBuilder;
use App\Reports\Rendering\DocxRenderer;
use App\Reports\Rendering\XlsxAnnexRenderer;
use App\Reports\ReportScope;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Generates a deliverable (SPEC-001): builds its content from the central data, freezes it as a
 * snapshot, renders the Word document and its XLSX annex, and records the generation.
 * The report is a draft: no approval circuit for now.
 */
final class GenerateReport
{
    public function __construct(
        private readonly Container $container,
        private readonly DocxRenderer $docxRenderer,
        private readonly XlsxAnnexRenderer $xlsxRenderer,
    ) {}

    public function handle(string $code, ReportScope $scope, User $author): ReportGeneration
    {
        /** @var array{title: string, template: string, template_version: string, builder: class-string<ReportBuilder>|null}|null $definition */
        $definition = config('reports.deliverables.'.$code);
        if ($definition === null || $definition['builder'] === null) {
            throw new InvalidArgumentException("Le livrable {$code} n’est pas encore exportable.");
        }

        /** @var ReportBuilder $builder */
        $builder = $this->container->make($definition['builder']);
        $content = $builder->build($scope);

        $snapshot = json_encode($content->toSnapshot(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $dataHash = hash('sha256', json_encode([
            'code' => $code,
            'template' => $definition['template'],
            'template_version' => $definition['template_version'],
            'scope' => $scope->toArray(),
            'content' => $snapshot,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $disk = Storage::disk((string) config('reports.disk'));
        $directory = null;

        try {
            return DB::transaction(function () use ($code, $scope, $author, $definition, $content, $snapshot, $dataHash, $disk, &$directory): ReportGeneration {
                $versionNumber = ReportGeneration::query()
                    ->where('code', $code)
                    ->where('scope_hash', $scope->hash())
                    ->lockForUpdate()
                    ->count() + 1;

                $generation = ReportGeneration::query()->create([
                    'code' => $code,
                    'title' => $definition['title'],
                    'scope' => $scope->toArray(),
                    'scope_hash' => $scope->hash(),
                    'scope_label' => $scope->label(),
                    'template_file' => $definition['template'],
                    'template_version' => $definition['template_version'],
                    'version_number' => $versionNumber,
                    'completeness' => $content->completeness(),
                    'missing_count' => $content->missingCount(),
                    'snapshot' => $snapshot,
                    'data_sha256' => $dataHash,
                    'created_by' => $author->id,
                ]);

                $documentId = $generation->documentId();
                $directory = "reports/{$code}/{$documentId}";
                $disk->makeDirectory($directory);

                $control = [
                    'document.id' => $documentId,
                    'scope.product' => $content->product,
                    'scope.client' => $content->client,
                    'scope.repository' => $content->repository,
                    'scope.commit' => $content->commit,
                    'document.generated_at' => $generation->created_at->format('d/m/Y H:i').' UTC',
                    'document.author' => $author->name,
                    'document.approver' => 'Sans objet (circuit de validation non activé)',
                    'document.status' => $generation->statusLabel(),
                    'document.version' => (string) $versionNumber,
                    'document.approval' => 'Non soumis à validation (brouillon).',
                ];

                $docxPath = "{$directory}/{$documentId}.docx";
                $this->docxRenderer->render(
                    resource_path('report-templates/'.$definition['template']),
                    $content,
                    $control,
                    $disk->path($docxPath),
                    $definition['template_version'],
                );

                $xlsxPath = "{$directory}/{$documentId}-annexe.xlsx";
                $this->xlsxRenderer->render($content, [
                    'Identifiant du document' => $documentId,
                    'Livrable' => $code.' — '.$definition['title'],
                    'Produit' => $content->product,
                    'Client' => $content->client,
                    'Dépôt' => $content->repository,
                    'Commit' => $content->commit,
                    'Généré le' => $control['document.generated_at'],
                    'Auteur' => $author->name,
                    'Statut' => $control['document.status'],
                    'Version' => $control['document.version'],
                    'Modèle' => $definition['template'].' v'.$definition['template_version'],
                    'Complétude' => number_format($content->completeness(), 2, ',', ' ').' %',
                    'Valeurs à confirmer' => (string) $content->missingCount(),
                    'Empreinte des données (SHA-256)' => $dataHash,
                ], $disk->path($xlsxPath));

                $generation->update([
                    'docx_path' => $docxPath,
                    'docx_sha256' => hash_file('sha256', $disk->path($docxPath)),
                    'xlsx_path' => $xlsxPath,
                    'xlsx_sha256' => hash_file('sha256', $disk->path($xlsxPath)),
                ]);

                return $generation;
            });
        } catch (Throwable $exception) {
            if ($directory !== null) {
                $disk->deleteDirectory($directory);
            }

            throw $exception;
        }
    }
}
