<?php

namespace Tests\Unit;

use App\Http\Requests\StoreVendorSubmissionRequest;
use App\Http\Requests\UpdateSubmissionStatusRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadSizePolicyTest extends TestCase
{
    public function test_large_pdf_uploads_pass_without_losing_format_validation(): void
    {
        $rules = (new StoreVendorSubmissionRequest)->rules();
        $releaseRules = (new UpdateSubmissionStatusRequest)->rules();
        foreach ([$rules['documents.*'], $rules['units.*.documents.*'], $releaseRules['release_document']] as $fileRules) {
            $large = UploadedFile::fake()->create('scan.pdf', 100000, 'application/pdf');
            $this->assertTrue(Validator::make(['file' => $large], ['file' => $fileRules])->passes());
            $invalid = UploadedFile::fake()->create('script.txt', 1, 'text/plain');
            $this->assertTrue(Validator::make(['file' => $invalid], ['file' => $fileRules])->fails());
        }
    }
}
