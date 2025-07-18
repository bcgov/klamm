<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Filament\Forms\Resources\FormVersionResource\Pages\BuildFormVersion;
use App\Helpers\FormVersionHelper;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormMetadata\FormDataSource;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Fieldset;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(3)
                    ->schema([
                        Select::make('form_id')
                            ->relationship('form', 'form_id_title')
                            ->required()
                            ->reactive()
                            ->preload()
                            ->searchable()
                            ->columnSpan(2)
                            ->default(request()->query('form_id_title')),
                        Select::make('status')
                            ->options(function () {
                                return FormVersion::getStatusOptions();
                            })
                            ->default('draft')
                            ->disabled()
                            ->columnSpan(1)
                            ->required(),
                        Section::make('Form Properties')
                            ->collapsible()
                            ->columns(1)
                            ->compact()
                            ->columnSpanFull()
                            ->schema([
                                Select::make('form_developer_id')
                                    ->label('Form Developer')
                                    ->relationship(
                                        'formDeveloper',
                                        'name',
                                        fn($query) => $query->whereHas('roles', fn($q) => $q->where('name', 'form-developer'))
                                    )
                                    ->default(Auth::id())
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),
                                Repeater::make('formVersionFormDataSources')
                                    ->label('Form Data Sources')
                                    ->relationship()
                                    ->schema([
                                        Select::make('form_data_source_id')
                                            ->label('Data Source')
                                            ->options(function () {
                                                return FormDataSource::pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpanFull()
                                            ->live(onBlur: true)
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    ])
                                    ->orderColumn('order')
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        isset($state['form_data_source_id'])
                                            ? FormDataSource::find($state['form_data_source_id'])?->name ?? 'New Data Source'
                                            : 'New Data Source'
                                    )
                                    ->addActionLabel('Add Data Source')
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->defaultItems(0),
                                Fieldset::make('PETS template')
                                    ->columns(4)
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('uses_pets_template')
                                            ->label('Use PETS Template')
                                            ->columnSpanFull()
                                            ->live()
                                            ->default(false),
                                        TextInput::make('pdf_template_name')
                                            ->label('Name')
                                            ->columnSpan(3)
                                            ->visible(fn(callable $get) => $get('uses_pets_template')),
                                        TextInput::make('pdf_template_version')
                                            ->label('Version')
                                            ->columnSpan(1)
                                            ->visible(fn(callable $get) => $get('uses_pets_template')),
                                        Textarea::make('pdf_template_parameters')
                                            ->label('Parameters')
                                            ->columnSpanFull()
                                            ->visible(fn(callable $get) => $get('uses_pets_template')),
                                    ]),
                                Textarea::make('comments')
                                    ->columnSpanFull()
                                    ->maxLength(500),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form.form_id_title')
                    ->label('Form')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('version_number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn($state) => FormVersion::getStatusColour($state))
                    ->getStateUsing(fn($record) => $record->getFormattedStatusName()),
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
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer'))
                    ->action(function ($record) {
                        // Create a new version with incremented version number
                        $newVersion = $record->replicate(['version_number', 'status', 'created_at', 'updated_at']);
                        $newVersion->version_number = FormVersion::where('form_id', $record->form_id)->max('version_number') + 1;
                        $newVersion->status = 'draft';
                        $newVersion->form_developer_id = Auth::id();
                        $newVersion->comments = 'Duplicated from version ' . $record->version_number;
                        $newVersion->save();

                        // Duplicate all FormElements and map new to old
                        $oldToNewElementMap = [];
                        foreach ($record->formElements()->orderBy('order')->get() as $element) {
                            $newElement = $element->replicate(['id', 'form_version_id', 'parent_id', 'created_at', 'updated_at']);
                            $newElement->form_version_id = $newVersion->id;
                            $newElement->parent_id = null;
                            $newElement->save();

                            // Map old element ID to new element for parent relationship updates
                            $oldToNewElementMap[$element->id] = [
                                'new_element' => $newElement,
                                'old_parent_id' => $element->parent_id
                            ];

                            // Attach tags
                            $newElement->tags()->attach($element->tags->pluck('id'));

                            // Duplicate data bindings
                            foreach ($element->dataBindings as $dataBinding) {
                                \App\Models\FormBuilding\FormElementDataBinding::create([
                                    'form_element_id' => $newElement->id,
                                    'form_data_source_id' => $dataBinding->form_data_source_id,
                                    'path' => $dataBinding->path,
                                    'condition' => $dataBinding->condition,
                                    'order' => $dataBinding->order,
                                ]);
                            }

                            // Duplicate polymorphic elementable and link to new element
                            if ($element->elementable) {
                                $elementableData = $element->elementable->getData();

                                // Filter out null and empty string values to let model defaults apply
                                $filteredData = array_filter($elementableData, function ($value) {
                                    return $value !== null && $value !== '';
                                });

                                $newElementable = $element->elementable_type::create($filteredData);
                                $newElement->update(['elementable_id' => $newElementable->id]);
                            }
                        }

                        // Update parent_id relationships for nested elements
                        foreach ($oldToNewElementMap as $data) {
                            if ($data['old_parent_id'] && isset($oldToNewElementMap[$data['old_parent_id']])) {
                                $data['new_element']->update([
                                    'parent_id' => $oldToNewElementMap[$data['old_parent_id']]['new_element']->id
                                ]);
                            }
                        }

                        // Duplicate related models using a helper method
                        FormVersionHelper::duplicateRelatedModels($record->id, $newVersion->id, StyleSheet::class);
                        FormVersionHelper::duplicateRelatedModels($record->id, $newVersion->id, FormScript::class);

                        // Duplicate form data sources with their order
                        foreach ($record->formVersionFormDataSources as $formDataSource) {
                            \App\Models\FormBuilding\FormVersionFormDataSource::create([
                                'form_version_id' => $newVersion->id,
                                'form_data_source_id' => $formDataSource->form_data_source_id,
                                'order' => $formDataSource->order,
                            ]);
                        }

                        // Redirect to build the new version
                        if (Gate::allows('form-developer')) {
                            return redirect()->to('/forms/form-versions/' . $newVersion->id . '/build');
                        } else {
                            return redirect()->to(FormVersionResource::getUrl('view', ['record' => $newVersion]));
                        }
                    })
                    ->requiresConfirmation()
                    ->modalDescription('This will create a new draft version based on this form version, including all form elements.'),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn($record) => $record->status === 'published')
                    ->action(function ($record) {
                        $record->update(['status' => 'archived']);
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->tooltip('Archive this form version'),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'view' => Pages\ViewFormVersion::route('/{record}'),
            'build' => BuildFormVersion::route('/{record}/build'),
        ];
    }
}
