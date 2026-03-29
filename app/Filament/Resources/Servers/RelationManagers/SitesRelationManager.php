<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitesRelationManager extends RelationManager
{
    protected static string $relationship = 'sites';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('repository_url')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('deploy_path')
                    ->copyable(),
                TextColumn::make('deploy_source')
                    ->badge(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('last_deployed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
