<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFileFieldResource\RelationManagers;
use App\Models\BoundarySystemFileField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFileFieldResource extends Resource
{
    protected static ?string $model = BoundarySystemFileField::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('field_name')
                    ->required(),
                Forms\Components\Select::make('boundary_system_file_field_type_id')
                    ->relationship('boundarySystemFileFieldType', 'name')
                    ->label('Field Type'),
                Forms\Components\TextInput::make('field_length')
                    ->numeric(),
                Forms\Components\Textarea::make('field_description')
                    ->required(),
                Forms\Components\Textarea::make('validations'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('field_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('boundarySystemFileFieldType.name')->searchable()->sortable()
                    ->label('Field Type'),
                Tables\Columns\TextColumn::make('field_length')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('field_description')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('validations')->searchable()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListBoundarySystemFileFields::route('/'),
            'create' => Pages\CreateBoundarySystemFileField::route('/create'),
            'view' => Pages\ViewBoundarySystemFileField::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFileField::route('/{record}/edit'),
        ];
    }
}
