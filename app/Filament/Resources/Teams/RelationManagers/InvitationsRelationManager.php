<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Models\TeamInvitation;
use App\Services\Teams\TeamInvitationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->pending()->latest())
            ->columns([
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('invitedBy.name')
                    ->label('Invited by')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->since()
                    ->label('Invited'),
                TextColumn::make('expires_at')
                    ->since()
                    ->label('Expires'),
            ])
            ->recordActions([
                Action::make('resendInvite')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Resend this invitation?')
                    ->modalDescription('This generates a fresh join link, extends the expiry, and emails the teammate again.')
                    ->modalSubmitActionLabel('Resend invite')
                    ->action(function (TeamInvitation $record): void {
                        try {
                            app(TeamInvitationService::class)->resend($record, auth()->user());

                            Notification::make()
                                ->title('Invitation resent')
                                ->body('A fresh join link was emailed to the teammate.')
                                ->success()
                                ->send();
                        } catch (Throwable $throwable) {
                            Notification::make()
                                ->title('Unable to resend invitation')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                DeleteAction::make()
                    ->label('Cancel invite')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this pending invitation?')
                    ->modalDescription('This removes the invitation and prevents the teammate from using the current join link.')
                    ->modalSubmitActionLabel('Cancel invite'),
            ]);
    }
}
