<?php

namespace App\Filament\Reports\Resources;

use App\Filament\Reports\Resources\ReportLabelSourceResource\Pages;
use App\Filament\Reports\Resources\ReportLabelSourceResource\RelationManagers;
use App\Models\ReportLabelSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReportLabelSourceResource extends Resource
{
    protected static ?string $model = ReportLabelSource::class;

    protected static ?string $label = 'Label Sources';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Report Metadata';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportLabelSources::route('/'),
            'create' => Pages\CreateReportLabelSource::route('/create'),
            'view' => Pages\ViewReportLabelSource::route('/{record}'),
            'edit' => Pages\EditReportLabelSource::route('/{record}/edit'),
        ];
    }
}
