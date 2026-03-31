<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Teams\TeamResource;
use App\Services\Teams\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamInvitationController extends Controller
{
    public function __construct(
        protected TeamInvitationService $invitationService,
    ) {
    }

    public function show(string $token): View
    {
        $invitation = $this->invitationService->findByToken($token);

        abort_unless($invitation, 404);

        return view('team-invitations.show', [
            'invitation' => $invitation,
            'isAccepted' => filled($invitation->accepted_at),
            'isExpired' => $invitation->isExpired(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationService->findByToken($token);

        abort_unless($invitation, 404);

        $user = $this->invitationService->accept($token, $request->only([
            'name',
            'password',
            'password_confirmation',
        ]));

        Auth::login($user);

        return redirect()
            ->to(TeamResource::getUrl('view', ['record' => $invitation->team]))
            ->with('status', 'Invitation accepted. You now have access to the team.');
    }
}
