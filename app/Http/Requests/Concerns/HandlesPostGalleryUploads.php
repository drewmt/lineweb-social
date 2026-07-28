<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

trait HandlesPostGalleryUploads
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function galleryUploadRules(): array
    {
        $imageRules = [
            'file',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.(int) config('media.max_upload_kilobytes'),
            'dimensions:max_width=6000,max_height=6000',
        ];

        return [
            'images' => ['nullable', 'array', 'max:'.(int) config('media.max_gallery_items')],
            'images.*' => $imageRules,
            'image_alts' => ['nullable', 'array', 'max:'.(int) config('media.max_gallery_items')],
            'image_alts.*' => ['nullable', 'string', 'max:300'],
            // Kept during the alpha transition for existing clients.
            'image' => ['nullable', ...$imageRules],
            'image_alt' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    protected function galleryUploadMessages(): array
    {
        return [
            'images.array' => 'Choose up to four images.',
            'images.max' => 'A post can contain up to four images.',
            'images.*.mimetypes' => 'Choose JPEG, PNG, or WebP images.',
            'images.*.max' => 'Each image can be up to 8 MiB.',
            'images.*.dimensions' => 'Images can be up to 6,000 pixels wide or tall.',
            'image_alts.array' => 'Add one alternative-text description per image.',
            'image_alts.*.max' => 'Alternative text can be up to 300 characters.',
            'image.mimetypes' => 'Choose a JPEG, PNG, or WebP image.',
            'image.max' => 'Images can be up to 8 MiB.',
            'image.dimensions' => 'Images can be up to 6,000 pixels wide or tall.',
            'image_alt.max' => 'Alternative text can be up to 300 characters.',
        ];
    }

    /** @return list<UploadedFile> */
    public function galleryUploads(): array
    {
        $uploads = $this->file('images');

        if ($uploads === null) {
            $uploads = [];
        } elseif ($uploads instanceof UploadedFile) {
            $uploads = [$uploads];
        } else {
            $uploads = array_values($uploads);
        }

        $legacy = $this->file('image');

        if ($uploads === [] && $legacy instanceof UploadedFile) {
            return [$legacy];
        }

        return $uploads;
    }

    /** @return list<string> */
    public function galleryAltTexts(): array
    {
        $altTexts = $this->input('image_alts', []);

        if (! is_array($altTexts)) {
            $altTexts = [];
        }

        $altTexts = array_values(array_map(
            static fn (mixed $alt): string => trim(is_string($alt) ? $alt : ''),
            $altTexts,
        ));

        if ($altTexts === [] && $this->hasFile('image')) {
            return [trim((string) $this->input('image_alt'))];
        }

        return $altTexts;
    }

    protected function prepareGalleryForValidation(): void
    {
        $altTexts = $this->input('image_alts');

        $this->merge([
            'image_alts' => is_array($altTexts)
                ? array_values(array_map(
                    static fn (mixed $alt): mixed => is_string($alt) ? trim($alt) : $alt,
                    $altTexts,
                ))
                : $altTexts,
            'image_alt' => $this->filled('image_alt')
                ? trim((string) $this->input('image_alt'))
                : null,
        ]);
    }

    protected function validateGalleryUploads(Validator $validator, int $retainedCount = 0): void
    {
        $uploads = $this->galleryUploads();
        $altTexts = $this->galleryAltTexts();
        $maximumItems = (int) config('media.max_gallery_items');
        $maximumTotalKilobytes = (int) config('media.max_gallery_upload_kilobytes');

        if ($this->hasFile('image') && $this->hasFile('images')) {
            $validator->errors()->add('images', 'Use one gallery upload field per request.');
        }

        if ($retainedCount + count($uploads) > $maximumItems) {
            $validator->errors()->add('images', "A post can contain up to {$maximumItems} images.");
        }

        if (count($uploads) !== count($altTexts)
            || collect($altTexts)->contains(static fn (string $alt): bool => $alt === '')) {
            $validator->errors()->add(
                $this->hasFile('image') && ! $this->hasFile('images')
                    ? 'image_alt'
                    : 'image_alts',
                'Describe every image for members using screen readers.',
            );
        }

        $totalKilobytes = (int) ceil(array_sum(array_map(
            static fn (UploadedFile $upload): int => max(0, (int) $upload->getSize()),
            $uploads,
        )) / 1024);

        if ($totalKilobytes > $maximumTotalKilobytes) {
            $validator->errors()->add(
                'images',
                'The combined gallery upload can be up to 20 MiB.',
            );
        }
    }
}
