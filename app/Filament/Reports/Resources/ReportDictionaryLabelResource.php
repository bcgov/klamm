<?php

namespace App\Filament\Reports\Resources;

use App\Filament\Reports\Resources\ReportDictionaryLabelResource\Pages;
use App\Filament\Reports\Resources\ReportDictionaryLabelResource\RelationManagers;
use App\Models\ReportDictionaryLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReportDictionaryLabelResource extends Resource
{
    protected static ?string $model = ReportDictionaryLabel::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?string $navigationGroup = 'Report Metadata';

    protected static ?string $navigationLabel = 'Dictionary Labels';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReportEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportDictionaryLabels::route('/'),
            'create' => Pages\CreateReportDictionaryLabel::route('/create'),
            'view' => Pages\ViewReportDictionaryLabel::route('/{record}'),
            'edit' => Pages\EditReportDictionaryLabel::route('/{record}/edit'),
        ];
    }
}
