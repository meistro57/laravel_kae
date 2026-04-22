<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NodeResource\Pages;
use App\Models\Node;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'KAE Data';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Node')->schema([
                    TextEntry::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono'),
                    TextEntry::make('label'),
                    TextEntry::make('domain')->badge(),
                    TextEntry::make('weight'),
                    TextEntry::make('cycle'),
                    TextEntry::make('anomaly')->badge(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('domain')->badge()->searchable(),
                TextColumn::make('weight')->sortable()->numeric(decimalPlaces: 4),
                TextColumn::make('cycle')->sortable(),
                IconColumn::make('anomaly')->boolean(),
                TextColumn::make('run.run_id_go')->label('Run')->limit(16),
                TextColumn::make('synced_at')->dateTime()->sortable(),
            ])
            ->defaultSort('weight', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('anomaly')->label('Anomalous Only'),
                Tables\Filters\SelectFilter::make('domain')
                    ->options(fn () => Node::query()->distinct()->pluck('domain', 'domain')->filter()),
                Tables\Filters\SelectFilter::make('run')
                    ->relationship('run', 'run_id_go'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNodes::route('/'),
        ];
    }
}
