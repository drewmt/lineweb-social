<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'max_gallery_items' => 4,
    'max_gallery_upload_kilobytes' => 20 * 1024,
    'max_upload_kilobytes' => 8 * 1024,
    'max_source_pixels' => 12_000_000,
    'max_output_dimension' => 2048,
    'webp_quality' => 82,
    'video' => [
        'enabled' => (bool) env('VIDEO_POSTS_ENABLED', false),
        'max_input_kilobytes' => 64 * 1024,
        'max_duration_seconds' => 90,
        'max_output_height' => 720,
        'space_quota_bytes' => 1024 * 1024 * 1024,
        'queue' => 'media',
        'process_timeout_seconds' => 180,
        'failed_draft_retention_days' => 7,
    ],
];
