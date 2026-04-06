# the-shit/review

PR review toolkit for Laravel agents. Structural analysis, convention checking, and contextual verdicts — not a nitpick bot.

## What This Is

A Laravel package that gives any agent the primitives to review pull requests intelligently. CI tells you if checks pass. This package answers: **"Checks pass — but should this be merged?"**

## What It Doesn't Do

- **No style checking.** Pint exists. PHPStan exists. If CI passes, we don't comment on formatting.
- **No generic "best practices" opinions.** Conventions are injected per-project, not baked in.
- **No commenting on every file.** Findings have confidence scores. Below threshold = silent.

## Usage

```php
use TheShit\Review\Review;

$review = Review::for('jordanpartridge/lexi-agent', prNumber: 118)
    ->withConventions(file_get_contents('CLAUDE.md'))
    ->analyze();

$review->classification;    // 'bug_fix', 'feature', 'refactor', 'docs', 'dependency'
$review->risk;              // Risk::Low / Medium / High / Critical
$review->blastRadius;       // Files affected beyond the diff
$review->findings;          // Collection of typed, weighted Finding objects
$review->verdict;           // Verdict::Approve / RequestChanges / Comment
$review->summary;           // One-paragraph human-readable summary

// Act on it
$review->approve();         // Post approval with summary
$review->requestChanges();  // Post findings as inline comments
$review->merge();           // Squash merge after approval
```

## Findings Are Typed, Not Flat

```php
foreach ($review->findings as $finding) {
    $finding->severity;     // 'bug', 'convention', 'risk', 'suggestion'
    $finding->file;         // 'app/Console/Commands/Watchdog.php'
    $finding->lines;        // [62, 63]
    $finding->message;      // 'JSON_EXTRACT is MySQL syntax, Postgres needs ->>'
    $finding->confidence;   // 0.95
}
```

Only `bug` and `convention` findings above the confidence threshold trigger `RequestChanges`. Everything else is informational.

## Analysis Pipeline

```
PR opened
  |
  +- 1. Classify ---------- title, description, file paths (zero LLM)
  |                          -> "bug_fix touching database queries"
  |
  +- 2. Risk gate --------- file count, critical paths, caller analysis (zero LLM)
  |                          -> Risk::Medium
  |
  +- 3. Structural diff ---- nikic/php-parser for PHP files (zero LLM)
  |                          -> signature changes, new branches, removed checks
  |
  +- 4. Convention check --- CLAUDE.md rules as structured checks (zero LLM)
  |                          -> only flag violations
  |
  +- 5. Judgment call ------ one focused LLM call with minimal context
  |                          -> "given these findings + diff, anything I missed?"
  |
  +- 6. Verdict ------------ deterministic from findings
                             -> Approve / RequestChanges / Comment
```

Steps 1-4 are deterministic PHP. Step 5 is a single cheap model call (~500 tokens). The expensive path only triggers for high-risk PRs.

## Model Routing

```php
// config/review.php
'models' => [
    'default' => 'x-ai/grok-4.1-fast',       // 90% of PRs
    'high_risk' => 'anthropic/claude-sonnet-4-6', // critical paths only
],
```

## Multi-Repo Awareness

The package doesn't just review a PR in isolation. It can check:

- **Cross-repo contracts** — if a package changes its public API, who depends on it?
- **Ecosystem conventions** — patterns from a shared knowledge base (Qdrant, optional)
- **Dependency graph** — does this change ripple into other repos?

```php
$review = Review::for('the-shit/health', prNumber: 5)
    ->withConventions($claudeMd)
    ->withDependents(['jordanpartridge/lexi-agent'])  // check for contract breaks
    ->withPatterns($qdrantHits)                        // optional knowledge context
    ->analyze();
```

## Configuration

```php
// config/review.php
return [
    'github' => [
        'token' => env('REVIEW_GITHUB_TOKEN'),        // PAT or GitHub App token
        'app_id' => env('REVIEW_GITHUB_APP_ID'),       // for GitHub App auth
        'private_key' => env('REVIEW_GITHUB_PRIVATE_KEY'),
    ],

    'models' => [
        'default' => env('REVIEW_MODEL', 'x-ai/grok-4.1-fast'),
        'high_risk' => env('REVIEW_HIGH_RISK_MODEL', 'anthropic/claude-sonnet-4-6'),
    ],

    'thresholds' => [
        'confidence' => 0.7,             // don't post findings below this
        'high_risk_escalation' => 'high', // escalate to expensive model at this risk level
    ],

    'auto_approve' => [
        'enabled' => true,
        'max_risk' => 'low',             // only auto-approve low-risk PRs
        'require_ci_pass' => true,
    ],

    'trusted_authors' => [
        // PRs from these authors skip the contributor gate
    ],

    'repos' => [
        // Per-repo overrides
        'jordanpartridge/lexi-agent' => [
            'conventions' => 'CLAUDE.md',
            'auto_merge' => true,
        ],
    ],
];
```

## Requirements

- PHP 8.4+
- Laravel 13+
- `laravel/ai` for the judgment call
- `nikic/php-parser` (ships with Laravel) for structural analysis
- GitHub API access (PAT or GitHub App)

## Architecture

```
the-shit/review
├── src/
│   ├── Review.php                  # Entry point / builder
│   ├── ReviewResult.php            # Analysis result DTO
│   ├── Finding.php                 # Individual finding DTO
│   ├── Enums/
│   │   ├── Classification.php      # bug_fix, feature, refactor, docs, dependency
│   │   ├── Risk.php                # Low, Medium, High, Critical
│   │   ├── Severity.php            # bug, convention, risk, suggestion
│   │   └── Verdict.php             # Approve, RequestChanges, Comment
│   ├── Analyzers/
│   │   ├── Classifier.php          # PR type classification (deterministic)
│   │   ├── RiskAssessor.php        # Risk scoring (deterministic)
│   │   ├── StructuralDiff.php      # PHP AST diffing via nikic/php-parser
│   │   ├── ConventionChecker.php   # CLAUDE.md rule matching
│   │   └── JudgmentCall.php        # The one LLM call
│   ├── GitHub/
│   │   ├── GitHubConnector.php     # Saloon connector
│   │   ├── Requests/               # PR diff, checks, reviews, merge
│   │   └── Auth/                   # PAT + GitHub App token strategies
│   └── ReviewServiceProvider.php
├── config/
│   └── review.php
└── tests/
```

## License

MIT
