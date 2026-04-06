<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub Authentication
    |--------------------------------------------------------------------------
    |
    | Supports both personal access tokens and GitHub App installation tokens.
    | For GitHub App auth, provide app_id and private_key_path — the package
    | will generate installation tokens automatically.
    |
    */

    'github' => [
        'token' => env('REVIEW_GITHUB_TOKEN'),
        'app_id' => env('REVIEW_GITHUB_APP_ID'),
        'private_key_path' => env('REVIEW_GITHUB_PRIVATE_KEY_PATH'),
        'installation_id' => env('REVIEW_GITHUB_INSTALLATION_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Routing
    |--------------------------------------------------------------------------
    |
    | The judgment call (step 5) uses a cheap model by default. High-risk PRs
    | escalate to a more capable model. Uses laravel/ai provider config.
    |
    */

    'models' => [
        'default' => env('REVIEW_MODEL', 'x-ai/grok-4.1-fast'),
        'high_risk' => env('REVIEW_HIGH_RISK_MODEL', 'anthropic/claude-sonnet-4-6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'confidence' => 0.7,
        'high_risk_escalation' => 'high',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Approve
    |--------------------------------------------------------------------------
    |
    | When enabled, low-risk PRs from trusted authors that pass CI are
    | automatically approved without posting findings.
    |
    */

    'auto_approve' => [
        'enabled' => false,
        'max_risk' => 'low',
        'require_ci_pass' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Authors
    |--------------------------------------------------------------------------
    |
    | PRs from these GitHub usernames skip the contributor gate. The consuming
    | agent decides what the gate looks like (e.g., Block Kit card in Slack).
    |
    */

    'trusted_authors' => [
        // 'jordanpartridge',
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical Paths
    |--------------------------------------------------------------------------
    |
    | Glob patterns for files/directories that automatically escalate risk.
    | When a PR touches any of these, risk floor is Medium.
    |
    */

    'critical_paths' => [
        'app/Providers/*',
        'config/*',
        'database/migrations/*',
        'routes/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Merge Strategy
    |--------------------------------------------------------------------------
    */

    'merge_strategy' => env('REVIEW_MERGE_STRATEGY', 'squash'),

    /*
    |--------------------------------------------------------------------------
    | Per-Repo Overrides
    |--------------------------------------------------------------------------
    |
    | Override any top-level config on a per-repo basis. Keys are in
    | 'owner/repo' format. Values are merged over the defaults.
    |
    */

    'repos' => [
        // 'jordanpartridge/lexi-agent' => [
        //     'auto_approve' => ['enabled' => true, 'max_risk' => 'low'],
        //     'critical_paths' => ['app/Agent/*', 'app/Jobs/*'],
        //     'trusted_authors' => ['jordanpartridge'],
        // ],
    ],

];
