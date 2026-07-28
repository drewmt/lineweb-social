<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'max_gallery_items' => 4,
    'max_gallery_upload_kilobytes' => 20 * 1024,
    'max_upload_kilobytes' => 8 * 1024,
    'max_source_pixels' => 12_000_000,
    'max_output_dimension' => 2048,
    'webp_quality' => 82,
];
