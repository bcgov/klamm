<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\SelectableValueResource\Pages;
use App\Filament\Imports\SelectableValueImporter;
use App\Filament\Forms\Resources\FormFieldsRelationManagerResource\RelationManagers\FormFieldsRelationManager;
use App\Filament\Forms\Resources\SelectableValueResource\RelationManagers\FormInstanceFieldsRelationManager;
use App\Models\SelectableValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Http\Middleware\CheckRole;

class SelectableValueResource extends Resource
{
    protected static ?string $model = SelectableValue::class;

    protected static ?string $navigationIcon = 'icon-square-mouse-pointer';
    protected static ?string $navigationGroup = 'Form Building';


    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin', 'form-developer');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('label')
                    ->required(),
                TextInput::make('value')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('value')
                    ->sortable()
                    ->searchable(),
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
            ->headerActions([
                ImportAction::make('Import CSV')
                    ->importer(SelectableValueImporter::class)
            ])
            ->bulkActions([
                //
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
            FormFieldsRelationManager::class,
            FormInstanceFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSelectableValues::route('/'),
            'create' => Pages\CreateSelectableValue::route('/create'),
            'view' => Pages\ViewSelectableValue::route('/{record}'),
            'edit' => Pages\EditSelectableValue::route('/{record}/edit'),
        ];
    }
}
