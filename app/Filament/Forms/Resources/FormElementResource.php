<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormElementResource\Pages;
use App\Models\FormElement;
use App\Models\ContainerFormElement;
use App\Models\TextInputFormElement;
use App\Models\CheckboxInputFormElement;
use App\Models\SelectInputFormElement;
use App\Models\RadioInputFormElement;
use App\Models\TextareaInputFormElement;
use App\Models\NumberInputFormElement;
use App\Models\DateSelectInputFormElement;
use App\Models\ButtonInputFormElement;
use App\Models\HTMLFormElement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormElementResource extends Resource
{
    protected static ?string $model = FormElement::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Form Builder';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('form_versions_id')
                            ->relationship('formVersion', 'id')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('elementable_type')
                            ->label('Element Type')
                            ->options([
                                ContainerFormElement::class => 'Container',
                                TextInputFormElement::class => 'Text Input',
                                CheckboxInputFormElement::class => 'Checkbox',
                                SelectInputFormElement::class => 'Select',
                                RadioInputFormElement::class => 'Radio',
                                TextareaInputFormElement::class => 'Textarea',
                                NumberInputFormElement::class => 'Number Input',
                                DateSelectInputFormElement::class => 'Date Select',
                                ButtonInputFormElement::class => 'Button',
                                HTMLFormElement::class => 'HTML',
                            ])
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Content & Behavior')
                    ->schema([
                        Forms\Components\Textarea::make('help_text')
                            ->maxLength(65535),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535),
                        Forms\Components\TextInput::make('repeater_item_label')
                            ->maxLength(255),
                    ])->columns(1),

                Forms\Components\Section::make('Options')
                    ->schema([
                        Forms\Components\Toggle::make('is_repeatable')
                            ->label('Repeatable'),
                        Forms\Components\Toggle::make('is_resetable')
                            ->label('Resetable'),
                        Forms\Components\Toggle::make('visible_web')
                            ->label('Visible on Web')
                            ->default(true),
                        Forms\Components\Toggle::make('visible_pdf')
                            ->label('Visible on PDF')
                            ->default(true),
                        Forms\Components\Toggle::make('is_template')
                            ->label('Is Template'),
                    ])->columns(3),

                Forms\Components\Section::make('Data Binding')
                    ->schema([
                        Forms\Components\Select::make('form_field_data_bindings_id')
                            ->relationship('dataBinding', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('formVersion.id')
                    ->label('Form Version')
                    ->sortable(),
                Tables\Columns\TextColumn::make('elementable_type')
                    ->label('Type')
                    ->formatStateUsing(fn(string $state): string => class_basename($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_repeatable')
                    ->boolean(),
                Tables\Columns\IconColumn::make('visible_web')
                    ->boolean(),
                Tables\Columns\IconColumn::make('visible_pdf')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('elementable_type')
                    ->label('Element Type')
                    ->options([
                        ContainerFormElement::class => 'Container',
                        TextInputFormElement::class => 'Text Input',
                        CheckboxInputFormElement::class => 'Checkbox',
                        SelectInputFormElement::class => 'Select',
                        RadioInputFormElement::class => 'Radio',
                        TextareaInputFormElement::class => 'Textarea',
                        NumberInputFormElement::class => 'Number Input',
                        DateSelectInputFormElement::class => 'Date Select',
                        ButtonInputFormElement::class => 'Button',
                        HTMLFormElement::class => 'HTML',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
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
            'index' => Pages\ListFormElements::route('/'),
            'create' => Pages\CreateFormElement::route('/create'),
            'edit' => Pages\EditFormElement::route('/{record}/edit'),
            'tree' => Pages\TreeFormElements::route('/tree'),
        ];
    }

    // Tree-specific methods
    public static function getTreeRecordTitleAttribute(): string
    {
        return 'name';
    }

    public static function getTreeRecordChildrenKeyName(): string
    {
        return 'parent_id';
    }

    public static function getParentKeyName(): string
    {
        return 'parent_id';
    }
}
