<?php

declare(strict_types=1);

/*
 * Only what the demo changes. Laravel merges a package's config one level deep, so a key that is
 * a list of settings (`routes`) must be given whole.
 */
return [

    'name' => 'Demo newsletter',

    // Serve the public signup page and the signed confirm and unsubscribe links (off by default),
    // so the demo can show them working. The packaged pages are bare: a real app names its own
    // under `views`.
    'routes' => [
        'enabled' => true,
        'prefix' => 'newsletter',
        'middleware' => ['web'],
        'signup_per_minute' => 5,
        'views' => [
            'show' => 'filagora-newsletters::show',
            'confirmed' => 'filagora-newsletters::confirmed',
            'unsubscribed' => 'filagora-newsletters::unsubscribed',
        ],
    ],

    // The campaign preview is for staff (the ability decides). The demo has no `login` route, so
    // the `auth` middleware the package adds by default would fail for a guest: a guest gets a 403.
    'preview' => [
        'enabled' => true,
        'prefix' => 'staff/newsletters',
        'middleware' => ['web'],
    ],

    // The demo's mail goes to the log, so no provider is needed: campaigns use the default mailer.
    'delivery' => [
        'provider' => null,
        'campaign_daily_limit' => 90,
        'queue' => null,
    ],
];
