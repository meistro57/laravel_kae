<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FindingResource\Pages;
use App\Models\Finding;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FindingResource extends Resource
{
    protected static ?string $model = Finding::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'KAE Data';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Finding')->schema([
                    TextEntry::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->copyable(),
                    TextEntry::make('type')->badge(),
                    TextEntry::make('confidence')->numeric(decimalPlaces: 3),
                    TextEntry::make('reviewed')->badge(),
                    TextEntry::make('run.run_id_go')->label('Run')->fontFamily('mono'),
                    TextEntry::make('batch_id')->label('Batch ID'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
                Section::make('Summary')->schema([
                    TextEntry::make('finding')->prose()->columnSpanFull(),
                ]),
                Section::make('Reasoning Trace')->collapsed()->schema([
                    TextEntry::make('reasoning_trace')->prose()->columnSpanFull(),
                ]),
                Section::make('Correction')->collapsed()->schema([
                    TextEntry::make('correction')->prose()->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->limit(16)->copyable(),
                TextColumn::make('finding')->limit(100)->searchable(),
                TextColumn::make('confidence')->sortable()->numeric(decimalPlaces: 3),
                TextColumn::make('type')->badge()->searchable(),
                IconColumn::make('reviewed')->boolean(),
                TextColumn::make('run.run_id_go')->label('Run')->limit(16),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('confidence', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(fn () => Finding::query()->distinct()->whereNotNull('type')->pluck('type', 'type')),
                Tables\Filters\SelectFilter::make('run')
                    ->relationship('run', 'run_id_go'),
                Tables\Filters\TernaryFilter::make('reviewed'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFindings::route('/'),
        ];
    }
}
