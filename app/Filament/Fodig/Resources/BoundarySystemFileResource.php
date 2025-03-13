<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFileResource\RelationManagers;
use App\Models\BoundarySystemFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFileResource extends Resource
{
    protected static ?string $model = BoundarySystemFile::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('file_name')
                    ->required(),
                Forms\Components\Textarea::make('file_description')
                    ->required(),
                Forms\Components\Select::make('boundary_system_file_separator_id')
                    ->relationship('separator', 'separator')
                    ->label('Separator'),
                Forms\Components\Select::make('boundary_system_file_row_separator_id')
                    ->relationship('rowSeparator', 'separator')
                    ->label('Row Separator'),
                Forms\Components\Textarea::make('comments'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file_description')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('separator.description')->searchable()->sortable()
                    ->label('Separator'),
                Tables\Columns\TextColumn::make('rowSeparator.description')->searchable()->sortable()
                    ->label('Row Separator'),
                Tables\Columns\TextColumn::make('comments')->searchable()->sortable()
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
            'index' => Pages\ListBoundarySystemFiles::route('/'),
            'create' => Pages\CreateBoundarySystemFile::route('/create'),
            'view' => Pages\ViewBoundarySystemFile::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFile::route('/{record}/edit'),
        ];
    }
}
