<?php

namespace App\Http\Controllers;

use App\Models\ReportGeneration;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a generated file from the private disk, after checking it still matches its recorded hash.
 */
class ReportDownloadController extends Controller
{
    public function __invoke(ReportGeneration $generation, string $format): StreamedResponse
    {
        [$path, $hash] = match ($format) {
            'docx' => [$generation->docx_path, $generation->docx_sha256],
            'xlsx' => [$generation->xlsx_path, $generation->xlsx_sha256],
            default => abort(404),
        };

        abort_if($generation->isDeleted(), 410, 'Cet export a été supprimé.');

        $disk = Storage::disk((string) config('reports.disk'));
        abort_if($path === null || ! $disk->exists($path), 404);
        abort_if(hash_file('sha256', $disk->path($path)) !== $hash, 409, 'Le fichier ne correspond plus à son empreinte : il a été modifié après sa génération.');

        $name = sprintf('%s %s%s.%s', $generation->documentId(), $generation->title, $format === 'xlsx' ? ' - annexe' : '', $format);

        return $disk->download($path, $name);
    }
}
