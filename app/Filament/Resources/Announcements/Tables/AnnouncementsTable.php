<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Enum\AnnouncementType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => $state->color()),
                TextColumn::make('title')
                    ->placeholder('—')
                    ->description(fn ($record): string => self::excerpt($record->message))
                    ->tooltip(fn ($record): ?string => filled($record->message)
                        ? self::excerpt($record->message, 400)
                        : null)
                    ->wrap()
                    ->searchable(),
                IconColumn::make('is_urgent')
                    ->label('Urgent')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('departments.name')
                    ->label('Audience')
                    ->badge()
                    ->placeholder('Everyone')
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('From')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Immediately')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label('Until')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('No expiry')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(AnnouncementType::toArray()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_urgent')
                    ->label('Urgent'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * A short, single-line plain-text excerpt of the rich-text message, so a
     * long announcement doesn't blow up the row height.
     *
     * Block-level tags become spaces first (otherwise stripping glues words
     * together, e.g. "LeaveEmployees"), entities are decoded so the row shows
     * real quotes and apostrophes rather than "&quot;", and whitespace is
     * collapsed.
     */
    public static function excerpt(?string $message, int $limit = 120): string
    {
        $text = preg_replace(
            '/<(br|\/p|\/div|\/li|\/h[1-6]|\/tr)\b[^>]*>/i',
            ' ',
            (string) $message,
        );

        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $limit);
    }
}
