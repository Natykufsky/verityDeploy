<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Models\User;
use App\Services\Teams\TeamInvitationService;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->formatStateUsing(fn (?string $state): string => $this->getOwnerRecord()->memberRoleLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => $this->getOwnerRecord()->memberRoleBadgeColor($state)),
            ])
            ->searchPlaceholder('Search members by name or email')
            ->filters([
                SelectFilter::make('pivot.role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                        'viewer' => 'Viewer',
                        'owner' => 'Owner',
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn ($query) => $query->where('team_user.role', $data['value']),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('editRole')
                    ->label('Edit role')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (User $record): bool => $record->id !== $this->getOwnerRecord()->owner_id)
                    ->requiresConfirmation()
                    ->modalHeading('Change member role?')
                    ->modalDescription('This updates the team role for the selected member without removing them from the team.')
                    ->modalWidth('lg')
                    ->schema([
                        Select::make('role')
                            ->options([
                                'admin' => 'Admin',
                                'member' => 'Member',
                                'viewer' => 'Viewer',
                            ])
                            ->default(fn (User $record): string => (string) $record->pivot?->role)
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Save role')
                    ->action(function (User $record, array $data): void {
                        try {
                            app(TeamInvitationService::class)->updateMemberRole(
                                $this->getOwnerRecord(),
                                auth()->user(),
                                $record,
                                $data['role'],
                            );

                            Notification::make()
                                ->title('Member role updated')
                                ->body('The selected member now has the new team role.')
                                ->success()
                                ->send();
                        } catch (Throwable $throwable) {
                            Notification::make()
                                ->title('Unable to update role')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                DetachAction::make()
                    ->label('Remove member')
                    ->requiresConfirmation()
                    ->modalHeading('Remove this member from the team?')
                    ->modalDescription('This removes the teammate from the team immediately and revokes access to shared servers and sites.')
                    ->modalSubmitActionLabel('Remove member'),
            ]);
    }
}
