<?php

namespace App\Filament\Resources\Deployments\Schemas;

use App\Livewire\DeploymentCommandToolbar;
use App\Livewire\DeploymentTerminal;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DeploymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deployment Summary')
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
                Section::make('Terminal Output')
                    ->schema([
                        Livewire::make(DeploymentTerminal::class)
                            ->columnSpanFull(),
                    ]),
                Section::make('Command Copy Toolbar')
                    ->schema([
                        Livewire::make(DeploymentCommandToolbar::class)
                            ->columnSpanFull(),
                    ]),
                Section::make('Deployment Steps')
                    ->schema([
                        RepeatableEntry::make('steps')
                            ->schema([
                                TextEntry::make('sequence')
                                    ->label('#'),
                                TextEntry::make('label')
                                    ->weight('bold'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'successful' => 'success',
                                        'running' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('command')
                                    ->copyable()
                                    ->columnSpanFull(),
                                TextEntry::make('started_at')
                                    ->dateTime(),
                                TextEntry::make('finished_at')
                                    ->dateTime(),
                                TextEntry::make('output')
                                    ->label('Output')
                                    ->html()
                                    ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->contained(),
                    ]),
                Section::make('Useful deployment commands')
                    ->schema([
                        TextEntry::make('command_guide_intro')
                            ->label('Guide')
                            ->columnSpanFull(),
                        RepeatableEntry::make('command_guide_snippets')
                            ->state(fn ($record): array => $record->command_guide_snippets)
                            ->schema([
                                TextEntry::make('title')
                                    ->weight('bold'),
                                TextEntry::make('description')
                                    ->columnSpanFull(),
                                TextEntry::make('command')
                                    ->label('Command')
                                    ->copyable()
                                    ->columnSpanFull(),
                                TextEntry::make('usage')
                                    ->label('When to use')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->contained(),
                    ])
                    ->columns(1),
            ]);
    }

    protected static function renderTerminalBlock(mixed $state): HtmlString
    {
        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">%s</pre>',
            e((string) $state),
        ));
    }
}
