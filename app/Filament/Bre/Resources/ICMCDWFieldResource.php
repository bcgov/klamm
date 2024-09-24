<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\ICMCDWFieldResource\Pages;
use App\Filament\Bre\Resources\ICMCDWFieldResource\RelationManagers;
use App\Models\ICMCDWField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ICMCDWFieldResource extends Resource
{
    protected static ?string $model = ICMCDWField::class;

    protected static ?string $navigationLabel = 'ICM CDW Fields';
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'ICM Data';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('field'),
                Forms\Components\TextInput::make('panel_type'),
                Forms\Components\TextInput::make('entity'),
                Forms\Components\TextInput::make('path'),
                Forms\Components\TextInput::make('subject_area'),
                Forms\Components\TextInput::make('applet'),
                Forms\Components\TextInput::make('datatype'),
                Forms\Components\TextInput::make('field_input_max_length'),
                Forms\Components\TextInput::make('ministry'),
                Forms\Components\TextInput::make('cdw_ui_caption'),
                Forms\Components\TextInput::make('cdw_table_name'),
                Forms\Components\TextInput::make('cdw_column_name'),
                Forms\Components\Select::make('breFields')
                    ->label('Related BRE Fields:')
                    ->multiple()
                    ->relationship('breFields', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('field')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('panel_type')
                    ->label('Panel Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('entity')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subject_area')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('applet')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('datatype')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('field_input_max_length')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ministry')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_ui_caption')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_table_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_column_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breFields.name')
                    ->label('Related BRE Fields')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListICMCDWFields::route('/'),
            'create' => Pages\CreateICMCDWField::route('/create'),
            'view' => Pages\ViewICMCDWField::route('/{record}'),
            'edit' => Pages\EditICMCDWField::route('/{record}/edit'),
        ];
    }
}
