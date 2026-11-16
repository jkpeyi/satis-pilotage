<?php

return [
    // Phase 1: false. Repositories and stacks are entered manually and the code explorer is hidden.
    'enabled' => (bool) env('GITLAB_ENABLED', false),

    'url' => env('GITLAB_URL'),

    // Read-only service account token (read_api, read_repository).
    'token' => env('GITLAB_TOKEN'),

    // Phase 2: OAuth application used for "Se connecter avec GitLab".
    'oauth' => [
        'client_id' => env('GITLAB_CLIENT_ID'),
        'client_secret' => env('GITLAB_CLIENT_SECRET'),
        'redirect' => env('GITLAB_REDIRECT_URI'),
    ],
];
