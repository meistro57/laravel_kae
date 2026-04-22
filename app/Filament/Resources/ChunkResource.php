<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChunkResource\Pages;
use App\Models\Chunk;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChunkResource extends Resource
{
    protected static ?string $model = Chunk::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'KAE Data';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Chunk')->schema([
                    TextEntry::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono'),
                    TextEntry::make('source'),
                    TextEntry::make('run_topic')->label('Run Topic'),
                    TextEntry::make('semantic_domain')->label('Domain'),
                    TextEntry::make('domain_confidence')->label('Domain Confidence'),
                    TextEntry::make('lens_processed')->label('Lens Processed')->badge(),
                    TextEntry::make('lens_correction')->label('Correction Chunk')->badge(),
                ]),
                Section::make('Text')->schema([
                    TextEntry::make('text')->prose()->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->sortable(),
                TextColumn::make('run.run_id_go')->label('Run')->limit(16)->searchable(),
                TextColumn::make('text')->limit(80)->searchable(),
                TextColumn::make('source')->limit(30)->searchable(),
                TextColumn::make('semantic_domain')->label('Domain')->badge(),
                IconColumn::make('lens_processed')->label('Processed')->boolean(),
                IconColumn::make('lens_correction')->label('Correction')->boolean(),
                TextColumn::make('synced_at')->dateTime()->sortable(),
            ])
            ->defaultSort('qdrant_point_id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('run')
                    ->relationship('run', 'run_id_go'),
                Tables\Filters\SelectFilter::make('semantic_domain')
                    ->label('Domain')
                    ->options(fn () => Chunk::query()->distinct()->pluck('semantic_domain', 'semantic_domain')->filter()),
                Tables\Filters\TernaryFilter::make('lens_processed')->label('Lens Processed'),
                Tables\Filters\TernaryFilter::make('lens_correction')->label('Correction Chunk'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChunks::route('/'),
        ];
    }
}
