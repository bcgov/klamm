<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFieldResource\Pages;
use App\Models\FormField;
use App\Models\FormDataSource;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\DataType;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Filters\SelectFilter;

class FormFieldResource extends Resource
{
    protected static ?string $model = FormField::class;
    protected static ?string $navigationLabel = 'Fields';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Form Building';


    public static function form(Form $form): Form
    {
        $validationOptions = [
            'minValue' => 'Minimum Value',
            'maxValue' => 'Maximum Value',
            'minLength' => 'Minimum Length',
            'maxLength' => 'Maximum Length',
            'required' => 'Required',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'javascript' => 'JavaScript',
        ];
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('name')
                    ->unique(ignoreRecord: true)
                    ->columnSpan(2)
                    ->required(),
                TextInput::make('label')
                    ->columnSpan(2)
                    ->required(),
                Select::make('data_type_id')
                    ->relationship('dataType', 'name')
                    ->columnSpan(2)
                    ->required()
                    ->live(),
                Select::make('selectOptions')
                    ->label('Select Options')
                    ->relationship('selectOptions', 'label')
                    ->columnSpan(3)
                    ->multiple()
                    ->preload()
                    ->live()
                    ->visible(function ($get) {
                        $dataTypeId = $get('data_type_id');
                        $dataType = \App\Models\DataType::find($dataTypeId);
                        return $dataType && in_array($dataType->name, ['radio', 'dropdown']);
                    }),
                Select::make('data_binding_path')
                    ->label('Field data source')
                    ->options(FormDataSource::pluck('name', 'name'))
                    ->columnSpan(3),
                Textarea::make('data_binding')
                    ->columnSpan(3),
                TextInput::make('mask')
                    ->columnSpan(3),
                Select::make('field_group_id')
                    ->columnSpan(3)
                    ->multiple()
                    ->preload()
                    ->relationship('fieldGroups', 'name'),
                Select::make('webStyles')
                    ->relationship('webStyles', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpan(3)
                    ->live()
                    ->reactive(),
                Select::make('pdfStyles')
                    ->relationship('pdfStyles', 'name')
                    ->label('PDF styles')
                    ->multiple()
                    ->preload()
                    ->columnSpan(3)
                    ->live()
                    ->reactive(),
                Repeater::make('validations')
                    ->label('Validations')
                    ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                    ->relationship('validations')
                    ->defaultItems(0)
                    ->columnSpanFull()
                    ->addActionAlignment(Alignment::Start)
                    ->schema([
                        Select::make('type')
                            ->label('Validation Type')
                            ->options($validationOptions)
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Value'),
                        TextInput::make('error_message')
                            ->label('Error Message'),
                    ])
                    ->collapsed(),
                Textarea::make('help_text')
                    ->columnSpanFull(),
                Textarea::make('value')
                    ->label('Field Value')
                    ->visible(function (callable $get) {
                        $dataType = DataType::find($get('data_type_id'));
                        return $dataType && $dataType->name === 'text-info';
                    })
                    ->live()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('data_binding')
                    ->searchable()
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
                SelectFilter::make('data_binding')
                    ->label('Data Binding')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(function () {  // Fetch unique values from the 'data_binding' column            
                        return \App\Models\FormField::query()
                            ->distinct()
                            ->pluck('data_binding', 'data_binding')
                            ->filter()
                            ->toArray();
                    }),
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
