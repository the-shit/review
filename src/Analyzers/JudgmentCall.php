<?php

namespace TheShit\Review\Analyzers;

use TheShit\Review\Data\StructuralChange;
use TheShit\Review\Enums\Classification;
use TheShit\Review\Enums\Risk;
use TheShit\Review\Enums\Severity;
use TheShit\Review\Finding;

final class JudgmentCall
{
    private const float MAX_LLM_CONFIDENCE = 0.8;

    /**
     * Run the LLM judgment call against the analysis context.
     *
     * @param  StructuralChange[]  $structuralChanges
     * @param  Finding[]  $existingFindings
     * @param  array<string, string>  $patches  file => patch content
     * @return Finding[]
     */
    public function call(
        Classification $classification,
        Risk $risk,
        array $structuralChanges,
        array $existingFindings,
        array $patches,
        ?string $conventions = null,
    ): array {
        $model = $this->selectModel($risk);
        $prompt = $this->buildPrompt($classification, $risk, $structuralChanges, $existingFindings, $patches, $conventions);

        try {
            $response = (string) JudgmentAgent::make($model)->prompt($prompt);

            return $this->parseFindings($response);
        } catch (\Throwable) {
            return [];
        }
    }

    private function selectModel(Risk $risk): string
    {
        $threshold = Risk::tryFrom(config('review.thresholds.high_risk_escalation', 'high')) ?? Risk::High;

        if ($risk->meetsThreshold($threshold)) {
            return config('review.models.high_risk', 'anthropic/claude-sonnet-4-6');
        }

        return config('review.models.default', 'x-ai/grok-4.1-fast');
    }

    /**
     * @param  StructuralChange[]  $structuralChanges
     * @param  Finding[]  $existingFindings
     * @param  array<string, string>  $patches
     */
    private function buildPrompt(
        Classification $classification,
        Risk $risk,
        array $structuralChanges,
        array $existingFindings,
        array $patches,
        ?string $conventions,
    ): string {
        $sections = [];

        $sections[] = "## PR Classification: {$classification->value} | Risk: {$risk->value}";

        if ($structuralChanges !== []) {
            $sections[] = "## Structural Changes\n".$this->formatStructuralChanges($structuralChanges);
        }

        if ($existingFindings !== []) {
            $sections[] = "## Existing Findings (already caught)\n".$this->formatFindings($existingFindings);
        }

        if ($conventions !== null && $conventions !== '') {
            $sections[] = "## Project Conventions\n".mb_substr($conventions, 0, 500);
        }

        $sections[] = "## Diff\n".$this->formatPatches($patches);

        return implode("\n\n", $sections);
    }

    /**
     * @param  StructuralChange[]  $changes
     */
    private function formatStructuralChanges(array $changes): string
    {
        $lines = [];

        foreach (array_slice($changes, 0, 20) as $change) {
            $detail = $change->before !== null && $change->after !== null
                ? " ({$change->before} → {$change->after})"
                : '';
            $lines[] = "- {$change->file}: {$change->type->value} {$change->symbol}{$detail}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Finding[]  $findings
     */
    private function formatFindings(array $findings): string
    {
        $lines = [];

        foreach (array_slice($findings, 0, 10) as $finding) {
            $lines[] = "- [{$finding->severity->value}] {$finding->file}: {$finding->message}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $patches
     */
    private function formatPatches(array $patches): string
    {
        $output = '';
        $budget = 2000;

        foreach ($patches as $file => $patch) {
            $section = "### {$file}\n```diff\n{$patch}\n```\n";

            if (strlen($output) + strlen($section) > $budget) {
                $output .= '... (truncated, '.count($patches)." files total)\n";
                break;
            }

            $output .= $section;
        }

        return $output;
    }

    /**
     * @return Finding[]
     */
    private function parseFindings(string $response): array
    {
        $json = trim($response);
        $json = preg_replace('/^```json?\s*/i', '', $json);
        $json = preg_replace('/\s*```$/', '', (string) $json);

        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $findings = [];

        foreach ($decoded as $item) {
            if (! is_array($item) || ! isset($item['severity'], $item['message'])) {
                continue;
            }

            $severity = Severity::tryFrom($item['severity']);

            if ($severity === null) {
                continue;
            }

            $confidence = min((float) ($item['confidence'] ?? 0.6), self::MAX_LLM_CONFIDENCE);

            $findings[] = new Finding(
                severity: $severity,
                confidence: $confidence,
                file: $item['file'] ?? '',
                lines: $item['lines'] ?? [],
                message: $item['message'],
                source: 'llm',
            );
        }

        return $findings;
    }
}
