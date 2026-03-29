<?php

namespace App\Support;

use App\Models\Deployment;

class DeploymentCommandGuide
{
    /**
     * @return array<int, array{title: string, description: string, command: string, usage: string}>
     */
    public function snippetsFor(Deployment $deployment): array
    {
        $site = $deployment->site;
        $releasePath = rtrim((string) ($deployment->release_path ?: $site->deploy_path), '/');
        $currentPath = $releasePath.'/current';
        $source = $site->deploy_source;

        $intro = match ($source) {
            'local' => 'These snippets are tailored for local-source releases and help you inspect the extracted release, shared runtime, and worker state.',
            default => 'These snippets help you verify a release after deploy, confirm the expected branch or files are present, and safely restart the app runtime.',
        };

        $snippets = [
            [
                'title' => 'Inspect the release tree',
                'description' => 'Check that the active release is present and that you are looking at the right directory.',
                'command' => sprintf('cd %s && ls -la', escapeshellarg($releasePath)),
                'usage' => 'Use this when you want a quick sanity check on the release folder before running other commands.',
            ],
            [
                'title' => 'Install PHP dependencies',
                'description' => 'Reinstall vendor packages after a deploy or when Composer cache issues show up.',
                'command' => sprintf('cd %s && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader', escapeshellarg($releasePath)),
                'usage' => 'Use this if deployment logs show missing vendor packages or an interrupted install.',
            ],
            [
                'title' => 'Run migrations',
                'description' => 'Apply pending database changes without waiting for the next deploy.',
                'command' => sprintf('cd %s && php artisan migrate --force', escapeshellarg($releasePath)),
                'usage' => 'Use this after confirming the release is ready and you want schema changes to land safely.',
            ],
            [
                'title' => 'Restart queue workers',
                'description' => 'Bounce background workers so they pick up the new code release.',
                'command' => sprintf('cd %s && php artisan queue:restart', escapeshellarg($currentPath)),
                'usage' => 'Use this after a successful deploy or whenever workers need to reload the application container.',
            ],
        ];

        if ($source === 'git') {
            array_unshift($snippets, [
                'title' => 'Check the deployed branch',
                'description' => 'Confirm the checked-out branch and whether the working tree is clean.',
                'command' => sprintf('cd %s && git status --short --branch', escapeshellarg($releasePath)),
                'usage' => 'Use this when you want to verify the release matches the branch selected in the deploy UI.',
            ]);
        }

        if ($source === 'local') {
            $snippets[0] = [
                'title' => 'Inspect the extracted archive',
                'description' => 'Review the uploaded source bundle and make sure the release directory contains the expected files.',
                'command' => sprintf('cd %s && find . -maxdepth 2 -type f | head', escapeshellarg($releasePath)),
                'usage' => 'Use this when a local-source deploy needs a quick file-level check after extraction.',
            ];
        }

        return array_values(array_map(static fn (array $snippet) => [
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'command' => $snippet['command'],
            'usage' => $snippet['usage'],
            'intro' => $intro,
        ], $snippets));
    }

    public function introFor(Deployment $deployment): string
    {
        return $this->snippetsFor($deployment)[0]['intro'] ?? 'These commands are useful deployment building blocks.';
    }
}
