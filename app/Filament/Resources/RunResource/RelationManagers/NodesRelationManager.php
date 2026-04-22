<?php

namespace App\Filament\Resources\RunResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('domain')->badge(),
                TextColumn::make('weight')->sortable()->numeric(decimalPlaces: 4),
                TextColumn::make('cycle'),
                IconColumn::make('anomaly')->boolean(),
            ])
            ->defaultSort('weight', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
