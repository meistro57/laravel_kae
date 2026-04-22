<?php

namespace App\Filament\Resources\RunResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->limit(16),
                TextColumn::make('finding')->limit(80),
                TextColumn::make('confidence')->sortable()->numeric(decimalPlaces: 3),
                TextColumn::make('type')->badge(),
                IconColumn::make('reviewed')->boolean(),
            ])
            ->defaultSort('confidence', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
