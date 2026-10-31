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

    public function test_high_detail_photo_is_compressed_below_half_a_megabyte(): void
    {
        Storage::fake('local');
        $image = imagecreatetruecolor(1920, 1280);
        for ($y = 0; $y < 1280; $y++) {
            for ($x = 0; $x < 1920; $x++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);
        $source = ob_get_clean();
        imagedestroy($image);
        $file = UploadedFile::fake()->createWithContent('camera.png', $source);
        $path = app(PrivatePhotoResizer::class)->store($file, 'private/test', 'p5m');
        $this->assertLessThanOrEqual(512 * 1024, Storage::disk('local')->size($path));
        $this->assertSame(IMAGETYPE_JPEG, getimagesize(Storage::disk('local')->path($path))[2]);
    }
}
