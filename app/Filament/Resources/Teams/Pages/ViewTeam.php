<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Resources\Teams\TeamResource;
use App\Services\Teams\TeamInvitationService;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteMember')
                ->label('Invite member')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Invite a teammate')
                ->modalDescription('Invite a teammate by email. If the email already belongs to an existing account, they are added to the team immediately. Otherwise, they receive an invitation email with a join link.')
                ->modalWidth('4xl')
                ->schema([
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->label('Email address')
                        ->helperText('Use the email address the teammate will use to sign in.'),
                    TextInput::make('name')
                        ->label('Display name')
                        ->helperText('Optional. Used to personalize the invitation email.'),
                    Select::make('role')
                        ->options([
                            'admin' => 'Admin',
                            'member' => 'Member',
                            'viewer' => 'Viewer',
                        ])
                        ->default('member')
                        ->required(),
                    Textarea::make('message')
                        ->label('Message')
                        ->rows(4)
                        ->helperText('Optional. Add a short message to the invite email.'),
                ])
                ->modalSubmitActionLabel('Send invite')
                ->action(function (array $data): void {
                    try {
                        $result = app(TeamInvitationService::class)->invite(
                            $this->record->fresh(),
                            auth()->user(),
                            $data,
                        );

                        if (($result['status'] ?? null) === 'attached') {
                            Notification::make()
                                ->title('Member added')
                                ->body('The teammate already had an account and was added to the team.')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Invitation sent')
                            ->body('A join link was emailed to the teammate.')
                            ->success()
                            ->send();
                    } catch (Throwable $throwable) {
                        Notification::make()
                            ->title('Unable to invite member')
                            ->body($throwable->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            EditAction::make(),
        ];
    }
}
