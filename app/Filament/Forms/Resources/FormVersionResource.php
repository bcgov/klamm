<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Components\FormVersionBuilder;
use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Helpers\ImportTemplateHelper;
use App\Helpers\ScanTemplateHelper;
use App\Models\FormVersion;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Session;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Builder;
use App\Helpers\FormVersionHelper;
use App\Helpers\FormTemplateHelper;
use App\Filament\Components\FormVersionMetadata;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = true;

    public static function getElementCounter(): int
    {
        return Session::get('elementCounter', 1);
    }

    public static function incrementElementCounter(): void
    {
        $currentCounter = self::getElementCounter();
        Session::put('elementCounter', $currentCounter + 1);
    }

    public static function form(Form $form): Form
    {
        $formBuilderComponent = Builder::make('components')
            ->label('Form Elements')
            ->addActionLabel('Add to Form Elements')
            ->addBetweenActionLabel('Insert between elements')
            ->columnSpan(2)
            ->collapsible(false)
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->blockNumbers(false)
            ->cloneable()
            ->afterStateHydrated(function (Set $set, Get $get) {
                Session::put('elementCounter', FormVersionHelper::getHighestID($get('components') ?? []) + 1);
            })
            ->blocks([
                FormVersionBuilder::getFormFieldBlock(),
                FormVersionBuilder::getFieldGroupBlock(),
                FormVersionBuilder::getContainerBlock(),
            ]);

        return $form
            ->schema([
                Section::make('Form Metadata')
                    ->description('Basic form information and properties')
                    ->columnSpanFull()
                    ->collapsed(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\CreateRecord))
                    ->collapsible()
                    ->schema(FormVersionMetadata::schema()),

                Section::make('Form Builder')
                    ->description('Design your form structure')
                    ->columnSpanFull()
                    ->schema([
                        $formBuilderComponent,

                        Hidden::make('all_instance_ids')
                            ->default(fn(Get $get) => $get('all_instance_ids') ?? [])
                            ->dehydrated(fn() => true),
                    ])
                    ->visible(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\CreateRecord)),

                Tabs::make('Tabs')
                    ->visible(fn($livewire) => ($livewire instanceof \Filament\Resources\Pages\CreateRecord))
                    ->columnSpanFull()
                    ->activeTab(1)
                    ->reactive()
                    ->tabs([
                        Tab::make('Build')
                            ->schema([$formBuilderComponent]),
                        Tab::make('Import')
                            ->columnSpanFull()
                            ->schema([
                                Split::make([
                                    Textarea::make('json')
                                        ->label('Import JSON')
                                        ->afterStateUpdated(fn(Set $set) => $set('jsonModified', true))
                                        ->rows(15),
                                    MarkdownEditor::make('messages')
                                        ->label('Validation messages')
                                        ->disabled(),
                                ]),
                                Actions::make([
                                    Action::make('Scan JSON')
                                        ->label('Scan JSON')
                                        ->action(function (Set $set, $state) {
                                            $json = $state['json'] ?? '';
                                            $validation = ScanTemplateHelper::validateForm($json);
                                            $set('messages', $validation['messages']);
                                            $set('isValid', $validation['isValid']);
                                            $set('jsonModified', false);
                                        }),
                                    Action::make('Import JSON')
                                        ->label('Import JSON')
                                        ->disabled(fn(Get $get) => !$get('isValid') || $get('jsonModified'))
                                        ->action(function (Set $set, $state, $livewire) {
                                            $json = $state['json'] ?? '';
                                            $formVersion = ImportTemplateHelper::importForm($json);
                                            if ($formVersion && $formVersion->id) {
                                                $set('formVersion', $formVersion);
                                            }
                                        }),
                                    Action::make('View Imported Form')
                                        ->label('View Imported Form')
                                        ->disabled(fn(Get $get) => !$get('formVersion'))
                                        ->action(
                                            fn(Get $get, $livewire) =>
                                            $livewire->redirect(FormVersionResource::getUrl('view', ['record' => $get('formVersion')]))
                                        ),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_id_title')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->getStateUsing(fn($record) => $record->getFormattedStatusName()),
                Tables\Columns\TextColumn::make('deployed_to')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->date('M j, Y')
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
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn($record) => $record->status === 'published')
                    ->action(function ($record) {
                        $record->update(['status' => 'archived']);
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->tooltip('Archive this form version'),
                Tables\Actions\Action::make('Create New Version')
                    ->label('Create New Version')
                    ->icon('heroicon-o-document-plus')
                    ->requiresConfirmation()
                    ->tooltip('Create a new version from this version')
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

                            foreach ($field->conditionals as $conditional) {
                                $newConditional = $conditional->replicate();
                                $newConditional->form_instance_field_id = $newField->id;
                                $newConditional->save();
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

                                foreach ($field->conditionals as $conditional) {
                                    $newConditional = $conditional->replicate();
                                    $newConditional->form_instance_field_id = $newField->id;
                                    $newConditional->save();
                                }
                            }
                        }

                        $livewire->redirect(FormVersionResource::getUrl('edit', ['record' => $newVersion]));
                    }),
            ])
            ->bulkActions([])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
