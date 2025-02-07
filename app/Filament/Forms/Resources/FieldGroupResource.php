<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FieldGroupResource\Pages;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class FieldGroupResource extends Resource
{
    protected static ?string $model = FieldGroup::class;

    protected static ?string $navigationGroup = 'Form Building';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('label'),
                Select::make('form_field_ids')
                    ->label('Form Fields')
                    ->multiple()
                    ->relationship('formFields', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('styles')
                    ->relationship('styles', 'name')
                    ->multiple()
                    ->preload()
                    ->live()
                    ->reactive(),
                Select::make('data_binding_path')
                    ->label('Field data source')
                    ->options(FormDataSource::pluck('name', 'name')),
                Textarea::make('data_binding'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('internal_description')
                    ->columnSpanFull(),
                Toggle::make('repeater')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) {
                            $set('repeater_item_label', null);
                        }
                    }),
                TextInput::make('repeater_item_label')
                    ->live()
                    ->visible(fn($get) => $get('repeater')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('label')->sortable()->searchable(),
                Tables\Columns\IconColumn::make('repeater')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFieldGroups::route('/'),
            'create' => Pages\CreateFieldGroup::route('/create'),
            'view' => Pages\ViewFieldGroup::route('/{record}'),
            'edit' => Pages\EditFieldGroup::route('/{record}/edit'),
        ];
    }
}
