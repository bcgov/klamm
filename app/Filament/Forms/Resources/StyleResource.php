<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\StyleResource\Pages;
use App\Models\Style;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StyleResource extends Resource
{
    protected static ?string $model = Style::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Styles';

    protected static ?string $navigationGroup = 'Form Building';
    protected static ?int $navigationSort = 15;


    public static function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('name')
                    ->unique(ignoreRecord: true)
                    ->validationAttribute('style name')
                    ->columnSpan(2)
                    ->required(),
                TextInput::make('property')
                    ->label('CSS property')
                    ->columnSpan(2)
                    ->required(),
                TextInput::make('value')
                    ->label('CSS value')
                    ->columnSpan(2)
                    ->required(),
                Select::make('webFormFields')
                    ->relationship('webFormFields', 'name')
                    ->label('Web fields')
                    ->multiple()
                    ->searchable()
                    ->columnSpan(3)
                    ->preload(),
                Select::make('webFieldGroups')
                    ->relationship('webFieldGroups', 'name')
                    ->label('Web groups')
                    ->multiple()
                    ->searchable()
                    ->columnSpan(3)
                    ->preload(),
                Select::make('pdfFormFields')
                    ->label('PDF fields')
                    ->relationship('pdfFormFields', 'name')
                    ->multiple()
                    ->searchable()
                    ->columnSpan(3)
                    ->preload(),
                Select::make('pdfFieldGroups')
                    ->label('PDF groups')
                    ->relationship('pdfFieldGroups', 'name')
                    ->multiple()
                    ->searchable()
                    ->columnSpan(3)
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('webFormFields.name')
                    ->label('Web Fields')
                    ->sortable(),
                TextColumn::make('pdfFormFields.name')
                    ->label('PDF Fields')
                    ->sortable(),
                TextColumn::make('webFieldGroups.name')
                    ->label('Web Groups')
                    ->sortable(),
                TextColumn::make('pdfFieldGroups.name')
                    ->label('PDF Groups')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            'index' => Pages\ListStyles::route('/'),
            'create' => Pages\CreateStyle::route('/create'),
            'view' => Pages\ViewStyle::route('/{record}'),
            'edit' => Pages\EditStyle::route('/{record}/edit'),
        ];
    }
}
