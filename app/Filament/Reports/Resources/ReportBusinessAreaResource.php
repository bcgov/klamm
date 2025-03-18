<?php

namespace App\Filament\Reports\Resources;

use App\Filament\Reports\Resources\ReportBusinessAreaResource\Pages;
use App\Filament\Reports\Resources\ReportBusinessAreaResource\RelationManagers;
use App\Models\ReportBusinessArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReportBusinessAreaResource extends Resource
{
    protected static ?string $model = ReportBusinessArea::class;

    protected static ?string $label = 'Business Area';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

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
            'index' => Pages\ListReportBusinessAreas::route('/'),
            'create' => Pages\CreateReportBusinessArea::route('/create'),
            'view' => Pages\ViewReportBusinessArea::route('/{record}'),
            'edit' => Pages\EditReportBusinessArea::route('/{record}/edit'),
        ];
    }
}
