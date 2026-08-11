<?php

$configuredPaths = array_values(array_filter(array_map(
    static fn (string $path): string => trim($path),
    explode(PATH_SEPARATOR, (string) env('LINEWEB_SOCIAL_EXTENSION_PATHS', '')),
)));

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
    'core_version' => env('LINEWEB_SOCIAL_CORE_VERSION', '0.2.0-beta.1'),

    /*
    |--------------------------------------------------------------------------
    | Local extension discovery
    |--------------------------------------------------------------------------
    |
    | Extensions are discovered from local, deploy-time paths only. The core
    | never downloads or executes remote packages at runtime.
    |
    */
    'paths' => $configuredPaths !== [] ? $configuredPaths : [base_path('extensions')],

    /*
    |--------------------------------------------------------------------------
    | Explicit activation
    |--------------------------------------------------------------------------
    |
    | Only reviewed extensions listed here may register a service provider.
    | Activation is a deployment decision; the web interface remains read-only.
    |
    */
    'enabled' => array_values(array_filter(array_map(
        static fn (string $id): string => trim($id),
        explode(',', (string) env('LINEWEB_SOCIAL_EXTENSIONS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Migration inspection limits
    |--------------------------------------------------------------------------
    |
    | Extension migration source is reviewed deploy-time PHP. These limits keep
    | read-only inspection bounded before an operator explicitly executes it.
    |
    */
    'migrations' => [
        'max_files' => 100,
        'max_file_bytes' => 262144,
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser asset publication
    |--------------------------------------------------------------------------
    |
    | Extensions may declare reviewed, pre-built CSS and ES modules. They are
    | copied into immutable content-addressed public releases by an explicit
    | deployment command; uploaded or remote browser assets are unsupported.
    |
    */
    'assets' => [
        'max_files' => 12,
        'max_file_bytes' => 524288,
        'max_total_bytes' => 2097152,
        'public_root' => public_path('extensions'),
        'registry_root' => storage_path('app/private/platform/extensions/assets'),
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
