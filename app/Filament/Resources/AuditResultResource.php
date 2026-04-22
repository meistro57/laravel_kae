<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResultResource\Pages;
use App\Models\AuditResult;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditResultResource extends Resource
{
    protected static ?string $model = AuditResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'KAE Config';

    protected static ?int $navigationSort = 11;

    protected static ?string $label = 'Audit Result';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Audit Result')->schema([
                    TextEntry::make('run_timestamp')->label('Run At')->dateTime(),
                    TextEntry::make('issues_found')->label('Issues Found'),
                    TextEntry::make('issues_repaired')->label('Repaired'),
                ]),
                Section::make('Summary')->schema([
                    TextEntry::make('summary_json')
                        ->label('')
                        ->getStateUsing(fn (AuditResult $record): string => json_encode($record->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),
                Section::make('Details')->collapsed()->schema([
                    TextEntry::make('details_json')
                        ->label('')
                        ->getStateUsing(fn (AuditResult $record): string => json_encode($record->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('run_timestamp')->label('Run At')->dateTime()->sortable(),
                TextColumn::make('issues_found')->label('Issues')->sortable(),
                TextColumn::make('issues_repaired')->label('Repaired')->sortable(),
            ])
            ->defaultSort('run_timestamp', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditResults::route('/'),
            'view'  => Pages\ViewAuditResult::route('/{record}'),
        ];
    }
}
