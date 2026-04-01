<?php

namespace App\Filament\Resources\Servers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->accessibleTo())
            ->columns([
                TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('Personal workspace')
                    ->searchable(),
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
                TextColumn::make('provider_type')
                    ->label('Provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'digitalocean' => 'info',
                        'aws' => 'warning',
                        'hetzner' => 'danger',
                        'vultr', 'linode' => 'primary',
                        'cpanel' => 'success',
                        'local' => 'gray',
                        default => 'slate',
                    }),
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
                SelectFilter::make('provider_type')
                    ->label('Provider')
                    ->options([
                        'manual' => 'Manual / Custom',
                        'digitalocean' => 'DigitalOcean',
                        'aws' => 'AWS',
                        'hetzner' => 'Hetzner',
                        'vultr' => 'Vultr',
                        'linode' => 'Linode',
                        'cpanel' => 'cPanel',
                        'local' => 'Local',
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
