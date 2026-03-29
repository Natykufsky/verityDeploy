<?php

namespace App\Filament\Resources\Servers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->searchable(),
                TextColumn::make('ssh_port')
                    ->label('SSH port')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ssh_user')
                    ->label('SSH user')
                    ->searchable(),
                TextColumn::make('connection_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ssh_key' => 'success',
                        'password' => 'warning',
                        'local' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'gray',
                        'error' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('last_connected_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'error' => 'Error',
                        'pending' => 'Pending',
                    ]),
                SelectFilter::make('connection_type')
                    ->options([
                        'ssh_key' => 'SSH key',
                        'password' => 'Password',
                        'local' => 'Local',
                        'cpanel' => 'cPanel',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
