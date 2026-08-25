<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\VendorDocument;
use App\Models\VendorSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentController extends Controller
{
    public function show(Request $request, VendorDocument $document)
    {
        $submission = $document->submission;
        $user = $request->user();
        abort_unless($submission->site === $user->site && ($submission->created_by === $user->id || (in_array($user->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true) && $user->hasPermission('coordinator.vendor'))), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->original_name, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename='.$document->original_name.'']);
    }

    public function downloadAll(Request $request, VendorSubmission $submission)
    {
        $user = $request->user();
        abort_unless($submission->site === $user->site && ($submission->created_by === $user->id || (in_array($user->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true) && $user->hasPermission('coordinator.vendor'))), 403);
        $submission->load('documents');
        abort_if($submission->documents->isEmpty(), 404, 'Tidak ada dokumen untuk diunduh.');
        $temporary = tempnam(sys_get_temp_dir(), 'vendor-documents-');
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($submission->documents as $document) {
            if (Storage::disk('local')->exists($document->file_path)) {
                $zip->addFile(Storage::disk('local')->path($document->file_path), $document->document_code.'-'.$document->original_name);
            }
        }
        $zip->close();

        return response()->download($temporary, $submission->submission_number.'-documents.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
