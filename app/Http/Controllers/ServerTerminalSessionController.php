<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Terminal\TerminalSessionManager;
use App\Services\Terminal\TerminalBridgeUrl;
use App\Services\Terminal\TerminalTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerTerminalSessionController extends Controller
{
    public function open(Request $request, Server $record, TerminalTransport $transport, TerminalBridgeUrl $bridgeUrl): JsonResponse
    {
        $session = $transport->open(
            $record->fresh(),
            auth()->id(),
            [
                'opened_via' => 'http',
                'user_agent' => $request->userAgent(),
            ],
        );

        return response()->json([
            'session' => app(TerminalSessionManager::class)->payload($session),
            'bridge' => $bridgeUrl->make($session),
        ]);
    }

    public function heartbeat(Server $record, TerminalTransport $transport): JsonResponse
    {
        $session = app(TerminalSessionManager::class)->latestOpenForServer($record->fresh());

        if ($session) {
            $transport->heartbeat($session);
        }

        return response()->json([
            'session' => app(TerminalSessionManager::class)->payload($session),
        ]);
    }

    public function close(Server $record, TerminalTransport $transport): JsonResponse
    {
        $session = app(TerminalSessionManager::class)->latestOpenForServer($record->fresh());

        if ($session) {
            $transport->close($session);
        }

        return response()->json([
            'session' => app(TerminalSessionManager::class)->payload($session),
        ]);
    }
}
