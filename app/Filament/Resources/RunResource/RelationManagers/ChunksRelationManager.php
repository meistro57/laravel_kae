<?php

namespace App\Filament\Resources\RunResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChunksRelationManager extends RelationManager
{
    protected static string $relationship = 'chunks';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qdrant_point_id')->label('Qdrant ID')->fontFamily('mono')->sortable(),
                TextColumn::make('text')->limit(80),
                TextColumn::make('source')->limit(30),
                TextColumn::make('semantic_domain')->label('Domain')->badge(),
                IconColumn::make('lens_processed')->label('Processed')->boolean(),
                IconColumn::make('lens_correction')->label('Correction')->boolean(),
            ])
            ->defaultSort('qdrant_point_id', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
