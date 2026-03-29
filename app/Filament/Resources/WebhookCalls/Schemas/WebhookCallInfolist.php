<?php

namespace App\Filament\Resources\WebhookCalls\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class WebhookCallInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Webhook Details')
                    ->schema([
                        TextEntry::make('name')
                            ->badge(),
                        TextEntry::make('url')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Payload')
                    ->schema([
                        TextEntry::make('headers')
                            ->label('Headers')
                            ->html()
                            ->formatStateUsing(fn ($state): HtmlString => static::renderJsonBlock($state))
                            ->columnSpanFull(),
                        TextEntry::make('payload')
                            ->label('Payload')
                            ->html()
                            ->formatStateUsing(fn ($state): HtmlString => static::renderJsonBlock($state))
                            ->columnSpanFull(),
                        TextEntry::make('exception')
                            ->label('Exception')
                            ->html()
                            ->formatStateUsing(fn ($state): HtmlString => static::renderTerminalBlock($state))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function renderJsonBlock(mixed $state): HtmlString
    {
        $content = $state;

        if (is_string($content)) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded;
            }
        }

        if (is_array($content) || is_object($content)) {
            $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">%s</pre>',
            e((string) $content),
        ));
    }

    protected static function renderTerminalBlock(mixed $state): HtmlString
    {
        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">%s</pre>',
            e((string) $state),
        ));
    }
}
