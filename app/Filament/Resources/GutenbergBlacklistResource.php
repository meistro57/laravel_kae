<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GutenbergBlacklistResource\Pages;
use App\Models\GutenbergBlacklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GutenbergBlacklistResource extends Resource
{
    protected static ?string $model = GutenbergBlacklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'KAE Config';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Gutenberg Blacklist';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('gutenberg_id')
                ->label('Gutenberg ID')
                ->numeric()
                ->nullable(),
            Forms\Components\TextInput::make('reason')
                ->maxLength(255)
                ->nullable(),
            Forms\Components\Toggle::make('active')
                ->default(true),
            Forms\Components\DatePicker::make('detection_date')
                ->label('Detection Date')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(60),
                TextColumn::make('gutenberg_id')->label('ID')->sortable(),
                TextColumn::make('reason')->limit(40)->searchable(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('detection_date')->label('Detected')->date()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGutenbergBlacklists::route('/'),
            'create' => Pages\CreateGutenbergBlacklist::route('/create'),
            'edit'   => Pages\EditGutenbergBlacklist::route('/{record}/edit'),
        ];
    }
}
