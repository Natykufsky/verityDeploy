<?php

namespace App\Support;

class SiteEnvironmentPreview
{
    /**
     * Build a preview payload for the Site .env editor.
     *
     * @param  array<string, mixed>  $environmentVariables
     * @return array<string, mixed>
     */
    public static function build(array $environmentVariables, ?string $sharedEnvContents): array
    {
        $hasOverride = filled($sharedEnvContents);
        $generatedContents = static::generatedContents($environmentVariables);
        $customContents = $hasOverride ? rtrim((string) $sharedEnvContents, "\r\n") : '';
        $effectiveContents = $hasOverride ? $customContents : $generatedContents;

        $generatedLines = static::splitLines($generatedContents);
        $customLines = $hasOverride ? static::splitLines($customContents) : [];
        $effectiveLines = static::splitLines($effectiveContents);

        return [
            'mode' => $hasOverride ? 'custom' : 'generated',
            'has_override' => $hasOverride,
            'generated_contents' => $generatedContents,
            'custom_contents' => $customContents,
            'effective_contents' => $effectiveContents,
            'generated_lines' => $generatedLines,
            'custom_lines' => $customLines,
            'effective_lines' => $effectiveLines,
            'generated_count' => count($generatedLines),
            'custom_count' => count($customLines),
            'effective_count' => count($effectiveLines),
            'generated_pairs' => static::parseEnvironmentPairs($generatedContents),
            'custom_pairs' => $hasOverride ? static::parseEnvironmentPairs($customContents) : [],
            'diff' => static::diff($generatedContents, $customContents, $hasOverride),
        ];
    }

    /**
     * @param  array<string, mixed>  $environmentVariables
     */
    public static function generatedContents(array $environmentVariables): string
    {
        $lines = [];

        foreach ($environmentVariables as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $lines[] = $key.'='.static::formatEnvironmentValue($value);
        }

        return filled($lines)
            ? implode(PHP_EOL, $lines).PHP_EOL
            : '';
    }

    /**
     * @param  array<string, mixed>  $environmentVariables
     * @return array<string, mixed>
     */
    public static function parseEnvironmentPairsFromVariables(array $environmentVariables): array
    {
        return static::parseEnvironmentPairs(static::generatedContents($environmentVariables));
    }

    /**
     * @return array<string, mixed>
     */
    public static function diff(string $generatedContents, string $customContents, bool $hasOverride): array
    {
        if (! $hasOverride) {
            return [
                'has_changes' => false,
                'message' => 'No custom override is set. The generated preview becomes the effective .env file.',
                'added' => [],
                'removed' => [],
                'changed' => [],
            ];
        }

        $generatedPairs = static::parseEnvironmentPairs($generatedContents);
        $customPairs = static::parseEnvironmentPairs($customContents);

        $added = [];
        foreach (array_diff_key($customPairs, $generatedPairs) as $key => $value) {
            $added[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        $removed = [];
        foreach (array_diff_key($generatedPairs, $customPairs) as $key => $value) {
            $removed[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        $changed = [];
        foreach (array_intersect_key($generatedPairs, $customPairs) as $key => $generatedValue) {
            $customValue = $customPairs[$key];

            if ($generatedValue === $customValue) {
                continue;
            }

            $changed[] = [
                'key' => $key,
                'generated' => $generatedValue,
                'custom' => $customValue,
            ];
        }

        return [
            'has_changes' => filled($added) || filled($removed) || filled($changed),
            'message' => filled($added) || filled($removed) || filled($changed)
                ? 'The custom override will replace the generated values for matching keys.'
                : 'The custom override matches the generated preview.',
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function parseEnvironmentPairs(string $contents): array
    {
        $pairs = [];

        foreach (static::splitLines($contents) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = trim(substr($trimmed, 7));
            }

            if (! str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $pairs[$key] = $value;
        }

        return $pairs;
    }

    /**
     * @return array<int, string>
     */
    protected static function splitLines(string $contents): array
    {
        $contents = rtrim($contents, "\r\n");

        if ($contents === '') {
            return [];
        }

        return preg_split('/\R/u', $contents) ?: [];
    }

    protected static function formatEnvironmentValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        if ($value === '') {
            return '""';
        }

        if (preg_match('/^[A-Za-z0-9_@%:.,\\-\\/]+$/', $value)) {
            return $value;
        }

        $escaped = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\n', '\r'], $value);

        return "\"{$escaped}\"";
    }
}
