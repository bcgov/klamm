<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFieldResource\Pages;
use App\Models\FormField;
use App\Models\FormDataSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use App\Models\DataType;

class FormFieldResource extends Resource
{
    protected static ?string $model = FormField::class;
    protected static ?string $navigationLabel = 'Fields';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Form Building';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->unique()
                    ->required(),
                Forms\Components\TextInput::make('label')
                    ->required(),
                Forms\Components\Select::make('data_binding_path')
                    ->label('Field data source')
                    ->options(FormDataSource::pluck('name', 'name')),
                Forms\Components\Textarea::make('data_binding'),
                Forms\Components\Textarea::make('conditional_logic'),
                Forms\Components\Textarea::make('styles'),
                Forms\Components\TextInput::make('mask'),
                Repeater::make('validations')
                    ->label('Validations')
                    ->relationship('validations')
                    ->defaultItems(0)
                    ->schema([
                        Select::make('type')
                            ->label('Validation Type')
                            ->options([
                                'minValue' => 'Minimum Value',
                                'maxValue' => 'Maximum Value',
                                'minLength' => 'Minimum Length',
                                'maxLength' => 'Maximum Length',
                                'required' => 'Required',
                                'email' => 'Email',
                                'phone' => 'Phone Number',
                                'javascript' => 'JavaScript',
                            ])
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Value'),
                        TextInput::make('error_message')
                            ->label('Error Message'),
                    ])
                    ->collapsed(),
                Forms\Components\Textarea::make('help_text')
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_type_id')
                    ->relationship('dataType', 'name')
                    ->required()
                    ->live(),
                Forms\Components\Textarea::make('value')
                    ->label('Field Value')
                    ->visible(function (callable $get) {
                        $dataType = DataType::find($get('data_type_id'));
                        return $dataType && $dataType->name === 'text-info';
                    })
                    ->live()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('field_group_id')
                    ->multiple()
                    ->preload()
                    ->relationship('fieldGroups', 'name'),
                Forms\Components\Repeater::make('selectOptions')
                    ->label('Select Options')
                    ->relationship('selectOptions')
                    ->columnSpanFull()
                    ->visible(function ($get) {
                        $dataTypeId = $get('data_type_id');
                        $dataType = \App\Models\DataType::find($dataTypeId);
                        return $dataType && in_array($dataType->name, ['radio', 'dropdown']);
                    })
                    ->schema([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('label'),
                        Forms\Components\TextInput::make('value'),
                        Forms\Components\Textarea::make('description'),
                    ]),
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
                Tables\Columns\TextColumn::make('dataType.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fieldGroups.name')
                    ->sortable(),
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
            // Relations here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormFields::route('/'),
            'create' => Pages\CreateFormField::route('/create'),
            'view' => Pages\ViewFormField::route('/{record}'),
            'edit' => Pages\EditFormField::route('/{record}/edit'),
        ];
    }
}
