<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Resources\FormVersionResource\Pages;
use App\Models\Element;
use App\Models\Container;
use App\Models\Field;
use App\Models\FieldTemplate;
use App\Models\Form;
use App\Models\FormVersion;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Tabs;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Forms';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Form Version Details')
                    ->schema([
                        Forms\Components\Select::make('form_id')
                            ->label('Form')
                            ->relationship('form', 'form_title')
                            ->required(),

                        Forms\Components\TextInput::make('version_number')
                            ->label('Version Number')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn($record) => $record !== null),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(FormVersion::getStatusOptions())
                            ->required(),

                        Forms\Components\Select::make('form_developer_id')
                            ->label('Form Developer')
                            ->relationship('formDeveloper', 'name')
                            ->required(),

                        Forms\Components\TextInput::make('footer')
                            ->label('Footer')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Form Elements')
                    ->schema([
                        Forms\Components\Repeater::make('elements')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Element Type')
                                    ->options([
                                        'container' => 'Container',
                                        'field' => 'Field',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn($set) => $set('parent_element_id', null)),

                                Forms\Components\Select::make('parent_element_id')
                                    ->label('Parent Element')
                                    ->options(function (callable $get, ?FormVersion $record) {
                                        if (!$record) return [];

                                        return $record->elements()
                                            ->where('type', 'container')
                                            ->pluck('custom_label', 'id')
                                            ->toArray();
                                    })
                                    ->nullable(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Order')
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('custom_label')
                                    ->label('Label')
                                    ->required(),

                                Forms\Components\Toggle::make('hide_label')
                                    ->label('Hide Label'),

                                Forms\Components\TextInput::make('custom_data_binding_path')
                                    ->label('Data Binding Path'),

                                Forms\Components\TextInput::make('custom_data_binding')
                                    ->label('Data Binding'),

                                Forms\Components\TextInput::make('custom_help_text')
                                    ->label('Help Text'),

                                Forms\Components\Toggle::make('visible_web')
                                    ->label('Visible on Web')
                                    ->default(true),

                                Forms\Components\Toggle::make('visible_pdf')
                                    ->label('Visible on PDF')
                                    ->default(true),

                                // Container-specific fields
                                Forms\Components\Toggle::make('has_repeater')
                                    ->label('Has Repeater')
                                    ->visible(fn($get) => $get('type') === 'container'),

                                Forms\Components\Toggle::make('has_clear_button')
                                    ->label('Has Clear Button')
                                    ->visible(fn($get) => $get('type') === 'container'),

                                Forms\Components\TextInput::make('repeater_item_label')
                                    ->label('Repeater Item Label')
                                    ->visible(fn($get) => $get('type') === 'container' && $get('has_repeater')),

                                // Field-specific fields
                                Forms\Components\Select::make('field_template_id')
                                    ->label('Field Template')
                                    ->relationship('fieldTemplate', 'name')
                                    ->visible(fn($get) => $get('type') === 'field'),

                                Forms\Components\TextInput::make('custom_mask')
                                    ->label('Custom Mask')
                                    ->visible(fn($get) => $get('type') === 'field'),
                            ])
                            ->itemLabel(fn(array $state): ?string => $state['custom_label'] ?? null)
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_title')
                    ->label('Form')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn(string $state): string => FormVersion::getStatusOptions()[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('formDeveloper.name')
                    ->label('Developer')
                    ->sortable(),

                Tables\Columns\TextColumn::make('elements_count')
                    ->label('Elements')
                    ->counts('elements'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FormVersion::getStatusOptions()),

                Tables\Filters\SelectFilter::make('form_id')
                    ->label('Form')
                    ->relationship('form', 'form_title'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListFormVersions::route('/'),
            'create' => Pages\CreateFormVersion::route('/create'),
            'edit' => Pages\EditFormVersion::route('/{record}/edit'),
        ];
    }
}
