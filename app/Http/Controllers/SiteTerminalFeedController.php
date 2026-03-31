<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\JsonResponse;

class SiteTerminalFeedController extends Controller
{
    public function __invoke(Site $record): JsonResponse
    {
        $record->load([
            'terminalRuns' => fn ($query) => $query->latest('started_at')->latest()->limit(8),
        ]);

        return response()->json([
            'prompt' => $record->terminal_prompt,
            'suggestions' => $record->terminalAutocompleteSuggestions(),
            'runs' => $record->terminalRuns->map(fn ($run): array => [
                'id' => $run->id,
                'command' => $run->command,
                'status' => $run->status,
                'output' => $run->output,
                'exit_code' => $run->exit_code,
                'error_message' => $run->error_message,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
