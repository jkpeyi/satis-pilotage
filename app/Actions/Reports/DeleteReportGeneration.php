<?php

namespace App\Actions\Reports;

use App\Models\ReportGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an export: its DOCX and XLSX files are removed from the disk and the registry hides it.
 * The row stays as a trace (who, when), so that its document id and version number are never
 * reused and the export history remains complete.
 */
final class DeleteReportGeneration
{
    public function handle(ReportGeneration $generation, User $user): void
    {
        if ($generation->isDeleted()) {
            return;
        }

        $disk = Storage::disk((string) config('reports.disk'));
        foreach ([$generation->docx_path, $generation->xlsx_path] as $path) {
            if ($path !== null) {
                $disk->delete($path);
            }
        }
        if ($generation->docx_path !== null) {
            $disk->deleteDirectory(dirname($generation->docx_path));
        }

        $generation->forceFill(['deleted_at' => now(), 'deleted_by' => $user->id])->save();
    }
}
