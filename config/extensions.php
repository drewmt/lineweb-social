<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Core compatibility
    |--------------------------------------------------------------------------
    |
    | Extension manifests declare the Lineweb Social core versions they
    | support. Keep this value aligned with the public application release.
    |
    */
    'core_version' => env('LINEWEB_SOCIAL_CORE_VERSION', '0.1.0-alpha.1'),

    /*
    |--------------------------------------------------------------------------
    | Local extension discovery
    |--------------------------------------------------------------------------
    |
    | Extensions are discovered from local, deploy-time paths only. The core
    | never downloads or executes remote packages at runtime.
    |
    */
    'paths' => [
        base_path('extensions'),
    ],

    'permissions' => [
        'comments.read',
        'comments.write',
        'moderation.read',
        'notifications.write',
        'posts.read',
        'posts.write',
        'profiles.read',
        'settings.read',
        'settings.write',
        'spaces.read',
    ],

    'ui_slots' => [
        'comment.actions',
        'feed.composer.after',
        'feed.item.after',
        'post.actions',
        'profile.header.after',
        'settings.integrations',
        'space.header.after',
        'space.sidebar',
    ],
];
