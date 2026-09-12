<?php

namespace Tests\Unit;

use App\Services\PrivatePhotoResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatePhotoResizerTest extends TestCase
{
    public function test_it_resizes_large_photo_to_private_jpeg_without_upscaling(): void
    {
        Storage::fake('local');

        $path = app(PrivatePhotoResizer::class)->store(
            UploadedFile::fake()->image('camera.png', 3000, 2000),
            'private/expedition-reports/TEST',
            'arrival',
        );

        Storage::disk('local')->assertExists($path);
        $this->assertStringEndsWith('.jpg', $path);
        [$width, $height, $type] = getimagesize(Storage::disk('local')->path($path));
        $this->assertSame([1920, 1280, IMAGETYPE_JPEG], [$width, $height, $type]);
    }
}
