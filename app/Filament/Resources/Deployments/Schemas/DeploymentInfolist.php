<?php

namespace App\Filament\Resources\Deployments\Schemas;

use App\Livewire\DeploymentCommandToolbar;
use App\Livewire\DeploymentProgressPanel;
use App\Livewire\DeploymentTerminal;
use App\Models\Deployment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class DeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Deployment tabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Overview')
                            ->badge(fn (Deployment $record): string => ucfirst((string) $record->status))
                            ->badgeColor(fn (Deployment $record): string => match ($record->status) {
                                'successful' => 'success',
                                'running' => 'warning',
                                'failed' => 'danger',
                                'pending' => 'info',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Deployment snapshot')
                                    ->schema([
                                        View::make('filament.deployments.deployment-hero')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Deployment actions')
                                    ->schema([
                                        View::make('filament.deployments.deployment-actions')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Deployment summary')
                                    ->schema([
                                        TextEntry::make('site.name')
                                            ->label('Site'),
                                        TextEntry::make('triggeredBy.name')
                                            ->label('Triggered by')
                                            ->default('System'),
                                        TextEntry::make('source')
                                            ->badge(),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'successful' => 'success',
                                                'running' => 'warning',
                                                'failed' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('branch'),
                                        TextEntry::make('commit_hash')
                                            ->label('Commit')
                                            ->copyable(),
                                        TextEntry::make('release_path')
                                            ->label('Release path')
                                            ->copyable()
                                            ->columnSpanFull(),
                                        TextEntry::make('started_at')
                                            ->dateTime(),
                                        TextEntry::make('finished_at')
                                            ->dateTime(),
                                        TextEntry::make('exit_code')
                                            ->label('Exit code'),
                                        TextEntry::make('error_message')
                                            ->columnSpanFull(),
                                        TextEntry::make('recovery_hint')
                                            ->label('Recovery hint')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Progress')
                            ->badge(fn (Deployment $record): string => $record->step_progress['summary'])
                            ->badgeColor(fn (Deployment $record): string => $record->step_progress['failed'] > 0
                                ? 'danger'
                                : ($record->step_progress['running'] > 0 ? 'warning' : ($record->step_progress['completed'] > 0 ? 'success' : 'gray')))
                            ->schema([
                                Section::make('Deployment progress')
                                    ->schema([
                                        Livewire::make(DeploymentProgressPanel::class)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Logs')
                            ->badge(fn (Deployment $record): string => $record->status === 'running' ? 'Live' : 'Idle')
                            ->badgeColor(fn (Deployment $record): string => match ($record->status) {
                                'running' => 'warning',
                                'failed' => 'danger',
                                'successful' => 'success',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Live deployment logs')
                                    ->schema([
                                        Livewire::make(DeploymentTerminal::class)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Recovery')
                            ->badge(fn (Deployment $record): string => $record->isResumable() ? 'Resume' : ($record->status === 'failed' ? 'Fix' : 'Ready'))
                            ->badgeColor(fn (Deployment $record): string => match (true) {
                                $record->isResumable() => 'success',
                                $record->status === 'failed' => 'danger',
                                default => 'gray',
                            })
                            ->schema([
                                Section::make('Recovery guide')
                                    ->schema([
                                        View::make('filament.deployments.deployment-recovery')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Commands')
                            ->badge(fn (Deployment $record): string => (string) count($record->command_guide_snippets))
                            ->badgeColor('primary')
                            ->schema([
                                Section::make('Command copy toolbar')
                                    ->schema([
                                        Livewire::make(DeploymentCommandToolbar::class)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Useful deployment commands')
                                    ->schema([
                                        View::make('filament.deployments.command-guide')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                    ]),
            ]);
    }
}
