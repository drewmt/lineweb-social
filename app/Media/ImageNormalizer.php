<?php

namespace App\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ImageNormalizer
{
    /** @var list<int> */
    private const SUPPORTED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function normalize(UploadedFile $upload): NormalizedImage
    {
        if (! function_exists('imagewebp') || (imagetypes() & IMG_WEBP) === 0) {
            throw new RuntimeException('The configured GD extension does not support WebP encoding.');
        }

        $path = $upload->getRealPath();
        $source = file_get_contents($path);

        if ($source === false) {
            throw $this->invalidImage();
        }

        $dimensions = @getimagesizefromstring($source);

        if (! is_array($dimensions)
            || ! in_array($dimensions[2], self::SUPPORTED_TYPES, true)
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || $this->integerConfig('media.max_source_pixels') < $dimensions[0] * $dimensions[1]) {
            throw $this->invalidImage('The image dimensions are not supported.');
        }

        try {
            $image = @imagecreatefromstring($source);
        } catch (Throwable) {
            $image = false;
        }

        if (! $image instanceof GdImage) {
            throw $this->invalidImage();
        }

        try {
            if ($dimensions[2] === IMAGETYPE_JPEG) {
                $image = $this->applyOrientation($image, $path);
            }

            $image = $this->resize($image, $this->integerConfig('media.max_output_dimension'));
            $contents = $this->encodeWebp($image, $this->integerConfig('media.webp_quality'));
            $width = imagesx($image);
            $height = imagesy($image);
        } catch (ValidationException|RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->invalidImage();
        } finally {
            imagedestroy($image);
        }

        return new NormalizedImage(
            contents: $contents,
            width: $width,
            height: $height,
            mimeType: 'image/webp',
            sizeBytes: strlen($contents),
            checksum: hash('sha256', $contents),
        );
    }

    private function applyOrientation(GdImage $image, string $path): GdImage
    {
        $metadata = @exif_read_data($path, 'IFD0', true, false);
        $value = is_array($metadata)
            ? ($metadata['IFD0']['Orientation'] ?? $metadata['Orientation'] ?? 1)
            : 1;
        $orientation = is_numeric($value) ? (int) $value : 1;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), -90),
            6 => $this->rotate($image, -90),
            7 => $this->rotate($this->flip($image, IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(GdImage $image, int $mode): GdImage
    {
        if (! imageflip($image, $mode)) {
            throw $this->invalidImage();
        }

        return $image;
    }

    private function rotate(GdImage $image, int $angle): GdImage
    {
        $background = imagecolorallocatealpha($image, 0, 0, 0, 127);

        if ($background === false) {
            throw $this->invalidImage();
        }

        $rotated = imagerotate($image, $angle, $background);

        if (! $rotated instanceof GdImage) {
            throw $this->invalidImage();
        }

        imagesavealpha($rotated, true);
        imagedestroy($image);

        return $rotated;
    }

    private function resize(GdImage $image, int $maximum): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largest = max($width, $height);

        if ($largest <= $maximum) {
            return $image;
        }

        $ratio = $maximum / $largest;
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $resized instanceof GdImage) {
            throw $this->invalidImage();
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);

        if ($transparent === false) {
            imagedestroy($resized);

            throw $this->invalidImage();
        }

        imagefill($resized, 0, 0, $transparent);

        if (! imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        )) {
            imagedestroy($resized);

            throw $this->invalidImage();
        }

        imagedestroy($image);

        return $resized;
    }

    private function encodeWebp(GdImage $image, int $quality): string
    {
        ob_start();

        try {
            $encoded = imagewebp($image, null, $quality);
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! $encoded || $contents === '') {
            throw $this->invalidImage();
        }

        return $contents;
    }

    private function integerConfig(string $key): int
    {
        $value = config($key);

        if (! is_int($value) || $value < 1) {
            throw new RuntimeException("Invalid media configuration: {$key}.");
        }

        return $value;
    }

    private function invalidImage(string $message = 'We could not process this image. Try another JPEG, PNG, or WebP file.'): ValidationException
    {
        return ValidationException::withMessages(['image' => $message]);
    }
}
