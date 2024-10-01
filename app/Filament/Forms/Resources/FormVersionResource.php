<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Models\FormVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\DateTimePicker;


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
                TextArea::make('comments')
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

                Repeater::make('form_instance_fields')
                    ->label('Form Fields')
                    ->relationship('formInstanceFields')
                    ->columnSpan(2)
                    ->reorderable(true)
                    ->defaultItems(0)
                    ->itemLabel(
                        fn($state) => $state['label'] ?? \App\Models\FormField::find($state['form_field_id'])->label ?? 'Unknown Field'
                    )
                    ->schema([
                        Select::make('form_field_id')
                            ->label('Form Field')
                            ->relationship('formField', 'label')
                            ->required(),
                        TextInput::make('label')
                            ->label("Custom Label")
                            ->placeholder(fn($get) => \App\Models\FormField::find($get('form_field_id'))->label ?? null),
                        TextInput::make('data_binding')
                            ->label("Custom Data Binding")
                            ->placeholder(fn($get) => \App\Models\FormField::find($get('form_field_id'))->data_binding ?? null),
                        Repeater::make('validations')
                            ->label('Validations')
                            ->relationship('validations')
                            ->collapsible()
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
                        TextArea::make('conditional_logic')
                            ->label("Custom Conditional Logic")
                            ->placeholder(fn($get) => \App\Models\FormField::find($get('form_field_id'))->conditional_logic ?? null),
                        TextArea::make('styles')
                            ->label("Custom Styles")
                            ->placeholder(fn($get) => \App\Models\FormField::find($get('form_field_id'))->styles ?? null),
                    ])
                    ->collapsed(),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('Generate Form Template')
                        ->action(function (Forms\Get $get, Forms\Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = \App\Helpers\FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        }),
                    Forms\Components\Actions\Action::make('Preview Form Template')
                        ->url(function (Forms\Get $get) {
                            $jsonTemplate = $get('generated_text');
                            $encodedJson = base64_encode($jsonTemplate);
                            return route('forms.rendered_forms.preview', ['json' => $encodedJson]);
                        })
                        ->openUrlInNewTab()
                        ->disabled(fn(Forms\Get $get) => empty($get('generated_text'))),
                ]),
                TextArea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15),
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

                        foreach ($record->formInstanceFields as $field) {
                            $newField = $field->replicate();
                            $newField->form_version_id = $newVersion->id;
                            $newField->save();
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
