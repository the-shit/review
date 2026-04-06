# CLAUDE.md

## What This Is

`the-shit/review` is a Laravel package providing PR review primitives for AI agents. It handles the plumbing (GitHub API, AST diffing, convention checking) so agents can focus on judgment.

## Design Principles

- **Deterministic first, LLM last.** Steps 1-4 of the pipeline are pure PHP. The model call is a single focused prompt with minimal context.
- **No opinions.** Conventions are injected by the consuming app, not baked into the package.
- **No style comments.** Pint and PHPStan exist. If CI passes, don't nitpick formatting.
- **Confidence gating.** Findings below threshold are suppressed. Uncertain = silent.
- **Cheap by default.** 90% of PRs use a fast/free model. Expensive models only for high-risk.

## Key Commands

```bash
composer test              # run tests
vendor/bin/pint --dirty    # code style
vendor/bin/phpstan analyse # static analysis
```

## Architecture

- `Review` — fluent builder, entry point
- `ReviewResult` — immutable DTO with classification, risk, findings, verdict
- `Finding` — typed finding with severity, confidence, file, lines, message
- `Analyzers/` — each pipeline step is a separate analyzer class
- `GitHub/` — Saloon connector + requests for PR operations
- `ReviewServiceProvider` — publishes config, registers bindings

## Dependencies

- `laravel/ai` — for the judgment call (step 5)
- `nikic/php-parser` — structural PHP analysis (ships with Laravel)
- `saloonphp/saloon` — GitHub API connector
- No Qdrant dependency — patterns are passed in by the consumer, not fetched internally
