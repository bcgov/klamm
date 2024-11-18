<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Models\FormVersion;
use App\Models\FormField;
use App\Models\FieldGroup;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;


class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('form_id')
                    ->relationship('form', 'form_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->default(request()->query('form_id')),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'testing' => 'Testing',
                        'archived' => 'Archived',
                        'published' => 'Published',
                    ])
                    ->required(),
                Fieldset::make('Requester Information')
                    ->schema([
                        TextInput::make('form_requester_name')
                            ->label('Name'),
                        TextInput::make('form_requester_email')
                            ->label('Email')
                            ->email(),
                    ])
                    ->label('Requester Information'),
                Fieldset::make('Approver Information')
                    ->schema([
                        TextInput::make('form_approver_name')
                            ->label('Name'),
                        TextInput::make('form_approver_email')
                            ->label('Email')
                            ->email(),
                    ])
                    ->label('Approver Information'),
                Textarea::make('comments')
                    ->label('Comments')
                    ->maxLength(500),
                Select::make('deployed_to')
                    ->label('Deployed To')
                    ->options([
                        'dev' => 'Development',
                        'test' => 'Testing',
                        'prod' => 'Production',
                    ])
                    ->nullable()
                    ->afterStateUpdated(fn(callable $set) => $set('deployed_at', now())),
                DateTimePicker::make('deployed_at')
                    ->label('Deployment Date'),
                Select::make('form_data_sources')
                    ->multiple()
                    ->preload()
                    ->relationship('formDataSources', 'name'),
                Repeater::make('components')
                    ->label('Form Components')
                    ->reorderable()
                    ->collapsible()
                    ->collapsed(true)
                    ->itemLabel(function ($state) {
                        if ($state['component_type'] === 'form_field') {
                            return 'Form Field - ' . ($state['label'] ?: (FormField::find($state['form_field_id'])->label ?? 'Unnamed Field'));
                        } elseif ($state['component_type'] === 'field_group') {
                            return 'Field Group - ' . ($state['group_label'] ?: (FieldGroup::find($state['field_group_id'])->label ?? 'Unnamed Group'));
                        }
                        return 'Component';
                    })
                    ->schema([
                        Select::make('component_type')
                            ->options([
                                'form_field' => 'Form Field',
                                'field_group' => 'Field Group',
                            ])
                            ->reactive()
                            ->required(),
                        Section::make('Form Field Settings')
                            ->schema([
                                Select::make('form_field_id')
                                    ->label('Form Field')
                                    ->options(FormField::pluck('label', 'id'))
                                    ->searchable()
                                    ->required(),                                                                  
                                TextInput::make('label')
                                    ->label("Custom Label")
                                    ->placeholder(fn($get) => FormField::find($get('form_field_id'))->label ?? null),
                                TextInput::make('custom_id')
                                    ->label("Custom ID")
                                    ->default(fn($get) =>  \App\Helpers\FormTemplateHelper::calculateFieldID($get('../../'))) // Set the sequential default value
                                    ->required()
                                    ->alphanum()                                    
                                    ->reactive()                                    
                                    ->distinct(),  
                                TextInput::make('data_binding')
                                    ->label("Custom Data Binding")
                                    ->placeholder(fn($get) => FormField::find($get('form_field_id'))->data_binding ?? null),
                                Textarea::make('conditional_logic')
                                    ->label("Custom Conditional Logic")
                                    ->placeholder(fn($get) => FormField::find($get('form_field_id'))->conditional_logic ?? null),
                                Textarea::make('styles')
                                    ->label("Custom Styles")
                                    ->placeholder(fn($get) => FormField::find($get('form_field_id'))->styles ?? null),
                                Repeater::make('validations')
                                    ->label('Validations')
                                    ->collapsible()
                                    ->collapsed()
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
                                    ]),
                            ])
                            ->visible(fn($get) => $get('component_type') === 'form_field'),
                        Section::make('Field Group Settings')
                            ->schema([
                                Select::make('field_group_id')
                                    ->label('Field Group')
                                    ->options(FieldGroup::pluck('label', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $fieldGroup = FieldGroup::find($state);                                       
                                        if ($fieldGroup) {
                                            $formFields = $fieldGroup->formFields()->get()->map(function ($field,$index) {
                                                return [
                                                    'form_field_id' => $field->id,
                                                    'label' => $field->label,
                                                    'data_binding' => $field->data_binding,
                                                    'conditional_logic' => $field->conditional_logic,
                                                    'styles' => $field->styles,
                                                    'validations' => [],
                                                    'custom_id' =>'nestedField'.$index+1,
                                                ];
                                            })->toArray();
                                            $set('form_fields', $formFields);
                                        }
                                    }),
                                TextInput::make('group_label')
                                    ->label("Group Label")
                                    ->placeholder(fn($get) => FieldGroup::find($get('field_group_id'))->label ?? null),
                                TextInput::make('custom_id')
                                    ->label("Custom ID")
                                    ->default(fn($get) =>  \App\Helpers\FormTemplateHelper::calculateFieldID($get('../../'))) // Set the sequential default value
                                    ->required()
                                    ->alphanum()                                    
                                    ->reactive()
                                    ->distinct(),  
                                Toggle::make('repeater')
                                    ->label('Repeater'),
                                Repeater::make('form_fields')
                                    ->label('Form Fields in Group')
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(function ($state) {
                                        return 'Form Field - ' . ($state['label'] ?: (FormField::find($state['form_field_id'])->label ?? 'Unnamed Field'));
                                    })
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('form_field_id')
                                            ->label('Form Field')
                                            ->options(FormField::pluck('label', 'id')) 
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('label')
                                            ->label("Custom Label")
                                            ->placeholder(fn($get) => FormField::find($get('form_field_id'))->label ?? null),
                                        TextInput::make('custom_id')
                                            ->label("Custom ID")                                            
                                            ->default(fn($get) =>  \App\Helpers\FormTemplateHelper::calculateFieldInGroupID($get('../../'))) // Set the sequential default value
                                            ->required()
                                            ->alphanum()                                            
                                            ->reactive()
                                            ->distinct(),
                                        TextInput::make('data_binding')
                                            ->label("Custom Data Binding")
                                            ->placeholder(fn($get) => FormField::find($get('form_field_id'))->data_binding ?? null),
                                        Textarea::make('conditional_logic')
                                            ->label("Custom Conditional Logic")
                                            ->placeholder(fn($get) => FormField::find($get('form_field_id'))->conditional_logic ?? null),
                                        Textarea::make('styles')
                                            ->label("Custom Styles")
                                            ->placeholder(fn($get) => FormField::find($get('form_field_id'))->styles ?? null),
                                        Repeater::make('validations')
                                            ->label('Validations')
                                            ->collapsible()
                                            ->collapsed()
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
                                            ]),
                                    ])
                                    ->columns(1),
                            ])
                            ->visible(fn($get) => $get('component_type') === 'field_group'),
                    ])
                    ->addActionLabel('Add Form Field or Field Group')
                    ->columnSpan(2),
                Actions::make([
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = \App\Helpers\FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        })
                        ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                    Action::make('Preview Form Template')
                        ->url(function (Get $get) {
                            $jsonTemplate = $get('generated_text');
                            $encodedJson = base64_encode($jsonTemplate);
                            return route('forms.rendered_forms.preview', ['json' => $encodedJson]);
                        })
                        ->openUrlInNewTab()
                        ->disabled(fn(Get $get) => empty($get('generated_text')))
                        ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                ]),
                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_title')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_to')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('form_requester_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_requester_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_email')
                    ->searchable(),
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
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
                Tables\Actions\Action::make('Create New Version')
                    ->label('Create New Version')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn($record) => (Gate::allows('form-developer') && in_array($record->status, ['published', 'archived'])))
                    ->action(function ($record, $livewire) {
                        $newVersion = $record->replicate();
                        $newVersion->status = 'draft';
                        $newVersion->deployed_to = null;
                        $newVersion->deployed_at = null;
                        $newVersion->save();

                        foreach ($record->formInstanceFields()->whereNull('field_group_instance_id')->get() as $field) {
                            $newField = $field->replicate();
                            $newField->form_version_id = $newVersion->id;
                            $newField->save();

                            foreach ($field->validations as $validation) {
                                $newValidation = $validation->replicate();
                                $newValidation->form_instance_field_id = $newField->id;
                                $newValidation->save();
                            }
                        }
                        foreach ($record->fieldGroupInstances as $groupInstance) {
                            $newGroupInstance = $groupInstance->replicate();
                            $newGroupInstance->form_version_id = $newVersion->id;
                            $newGroupInstance->save();

                            foreach ($groupInstance->formInstanceFields as $field) {
                                $newField = $field->replicate();
                                $newField->form_version_id = $newVersion->id;
                                $newField->field_group_instance_id = $newGroupInstance->id;
                                $newField->save();

                                foreach ($field->validations as $validation) {
                                    $newValidation = $validation->replicate();
                                    $newValidation->form_instance_field_id = $newField->id;
                                    $newValidation->save();
                                }
                            }
                        }
                        $livewire->redirect(FormVersionResource::getUrl('edit', ['record' => $newVersion]));
                    }),
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
        ];
    }
}
