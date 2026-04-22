<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RunResource\Pages;
use App\Filament\Resources\RunResource\RelationManagers;
use App\Models\Run;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunResource extends Resource
{
    protected static ?string $model = Run::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'KAE Data';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('run_id_go')
                    ->label('Run ID (Go)')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->limit(20),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running'   => 'warning',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('chunks_count')
                    ->label('Chunks')
                    ->counts('chunks')
                    ->sortable(),
                TextColumn::make('nodes_count')
                    ->label('Nodes')
                    ->counts('nodes')
                    ->sortable(),
                TextColumn::make('findings_count')
                    ->label('Findings')
                    ->counts('findings')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'running'   => 'Running',
                        'completed' => 'Completed',
                        'failed'    => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Run Details')->schema([
                    TextEntry::make('id')->label('Laravel ID'),
                    TextEntry::make('run_id_go')->label('Go Run ID')->copyable()->fontFamily('mono'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('synced_at')->dateTime(),
                ]),
                Section::make('Settings')->schema([
                    TextEntry::make('settings_json')
                        ->label('')
                        ->getStateUsing(fn (Run $record): string => json_encode($record->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\ChunksRelationManager::class,
            RelationManagers\NodesRelationManager::class,
            RelationManagers\FindingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRuns::route('/'),
            'view'  => Pages\ViewRun::route('/{record}'),
        ];
    }
}
