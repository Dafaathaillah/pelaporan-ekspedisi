<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrivatePhotoResizer
{
    public function store(UploadedFile $file, string $directory, string $field): string
    {
        try {
            $source = imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (! $source) {
                throw new \RuntimeException('Gambar tidak dapat dibaca.');
            }

            $source = $this->orient($source, $file);
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, 1920 / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefill($target, 0, 0, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $quality = 80;
            do {
                ob_start();
                imagejpeg($target, null, $quality);
                $contents = ob_get_clean();
                if (strlen($contents) <= 512 * 1024) {
                    break;
                }
                if ($quality > 60) {
                    $quality -= 10;

                    continue;
                }
                $smaller = imagescale($target, max(1, (int) (imagesx($target) * 0.8)), -1, IMG_BICUBIC);
                imagedestroy($target);
                $target = $smaller;
                $quality = 80;
            } while (true);
            imagedestroy($source);
            imagedestroy($target);

            $path = trim($directory, '/').'/'.$field.'-'.Str::uuid().'.jpg';
            if (! Storage::disk('local')->put($path, $contents)) {
                throw new \RuntimeException('Foto gagal disimpan.');
            }

            return $path;
        } catch (\Throwable $error) {
            throw ValidationException::withMessages([$field.'_photo' => 'Foto gagal diproses. Gunakan foto JPG, PNG, atau WebP yang valid.']);
        }
    }

    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = match ($orientation) {
            3 => 180, 6 => -90, 8 => 90, default => 0
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }
}
