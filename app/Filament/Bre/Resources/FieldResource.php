<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\FieldResource\Pages;
use App\Models\BREField;
use App\Models\ICMCDWField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FieldResource extends Resource
{
    protected static ?string $model = BREField::class;
    protected static ?string $navigationLabel = 'BRE Rule Fields';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // protected static ?string $navigationGroup = 'Rule Building';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('label'),
                Forms\Components\Textarea::make('help_text')
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_type_id')
                    ->relationship('breDataType', 'name')
                    ->required(),
                Forms\Components\Select::make('field_group_id')
                    ->multiple()
                    ->relationship('breFieldGroups', 'name'),
                Forms\Components\Select::make('data_validation_id')
                    ->relationship('breDataValidation', 'name'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('input_rules')
                    ->label('Used as Inputs by Rules:')
                    ->multiple()
                    ->relationship('breInputs', 'name'),
                Forms\Components\Select::make('output_fields')
                    ->label('Returned as Outputs by Rules:')
                    ->multiple()
                    ->relationship('breOutputs', 'name'),
                Forms\Components\Select::make('icmcdwFieldsTest')
                    ->label('Related ICM CDW Fields:')
                    ->multiple()
                    ->relationship(
                        name: 'icmcdwFields',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')->orderBy('field')->orderBy('panel_type')->orderBy('entity')->orderBy('subject_area')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name} - {$record->field} - {$record->panel_type} - {$record->entity} - {$record->subject_area}")
                    ->searchable(['name', 'field', 'panel_type', 'entity', 'subject_area'])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('breDataType.name')
                    ->label('Data Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breDataValidation.name')
                    ->label('Data Validations')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fieldGroupNames')
                    ->label('Field Groups')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('input_output_type')
                    ->label('Input/Output?')
                    ->default(function ($record) {
                        return $record->getInputOutputType();
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breInputs.name')
                    ->label('Rules: Inputs')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Rules: Outputs')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('icmcdwFields.name')
                    ->label('Related ICM CDW Fields')
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
            'index' => Pages\ListFields::route('/'),
            'create' => Pages\CreateField::route('/create'),
            'view' => Pages\ViewField::route('/{record}'),
            'edit' => Pages\EditField::route('/{record}/edit'),
        ];
    }
}
