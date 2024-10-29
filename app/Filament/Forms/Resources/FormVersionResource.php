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
                Repeater::make('components')
                    ->label('Form Components')
                    ->schema([
                        Select::make('component_type')
                            ->options([
                                'form_field' => 'Form Field',
                                'field_group' => 'Field Group',
                            ])
                            ->reactive()
                            ->required(),
                        Select::make('form_field_id')
                            ->label('Form Field')
                            ->options(FormField::all()->pluck('name', 'id'))
                            ->visible(fn($get) => $get('component_type') === 'form_field')
                            ->required(fn($get) => $get('component_type') === 'form_field'),
                        Select::make('field_group_id')
                            ->label('Field Group')
                            ->options(FieldGroup::all()->pluck('name', 'id'))
                            ->visible(fn($get) => $get('component_type') === 'field_group')
                            ->required(fn($get) => $get('component_type') === 'field_group'),
                    ])
                    ->createItemButtonLabel('Add Form Field or Field Group')
                    ->columnSpan(2)
                    ->reorderable(),
                Actions::make([
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = \App\Helpers\FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        })
                        ->hidden(fn() => request()->routeIs('filament.forms.resources.form-versions.create') || request()->routeIs('filament.forms.resources.form-versions.edit')),
                    Action::make('Preview Form Template')
                        ->url(function (Get $get) {
                            $jsonTemplate = $get('generated_text');
                            $encodedJson = base64_encode($jsonTemplate);
                            return route('forms.rendered_forms.preview', ['json' => $encodedJson]);
                        })
                        ->openUrlInNewTab()
                        ->disabled(fn(Get $get) => empty($get('generated_text')))
                        ->hidden(fn() => request()->routeIs('filament.forms.resources.form-versions.create') || request()->routeIs('filament.forms.resources.form-versions.edit')),
                ]),
                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn() => request()->routeIs('filament.forms.resources.form-versions.create') || request()->routeIs('filament.forms.resources.form-versions.edit')),
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
