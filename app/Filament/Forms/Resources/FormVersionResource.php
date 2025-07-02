<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Filament\Forms\Resources\FormVersionResource\Pages\BuildFormVersion;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\StyleSheet;
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
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
                                Select::make('form_data_sources')
                                    ->multiple()
                                    ->preload()
                                    ->columnSpan(2)
                                    ->relationship('formDataSources', 'name'),
                                TextInput::make('footer')
                                    ->columnSpanFull(),
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
                            $newElement = $element->replicate(['id', 'uuid', 'form_version_id', 'parent_id', 'created_at', 'updated_at']);

                            // Generate new UUID
                            $newElement->uuid = (string) Str::uuid();
                            $newElement->form_version_id = $newVersion->id;

                            // Store the old parent_id temporarily
                            $oldParentId = $element->parent_id;
                            $newElement->parent_id = null; // Will be updated later

                            $newElement->save();

                            // Map old element ID to new element for parent relationship updates
                            $oldToNewElementMap[$element->id] = [
                                'new_element' => $newElement,
                                'old_parent_id' => $oldParentId
                            ];

                            // Attach tags
                            foreach ($element->tags as $tag) {
                                $newElement->tags()->attach($tag->id);
                            }

                            // Duplicate polymorphic elementable
                            $elementableType = $newElement->elementable_type;
                            $elementableData = $elementableType::find($newElement->elementable_id)->getData();
                            $newElementable = $elementableType::create($elementableData);
                        }

                        // Update parent_id relationships for nested elements
                        foreach ($oldToNewElementMap as $oldElementId => $data) {
                            $newElement = $data['new_element'];
                            $oldParentId = $data['old_parent_id'];

                            if ($oldParentId && isset($oldToNewElementMap[$oldParentId])) {
                                $newElement->parent_id = $oldToNewElementMap[$oldParentId]['new_element']->id;
                                $newElement->save();
                            }
                        }

                        // Duplicate style_sheets
                        $styleSheets = StyleSheet::where('form_version_id', $record->id)->get();
                        foreach ($styleSheets as $styleSheet) {
                            $newStyleSheet = $styleSheet->replicate(['id', 'form_version_id', 'created_at', 'updated_at']);
                            $newStyleSheet->form_version_id = $newVersion->id;
                            $newStyleSheet->save();
                        }

                        // Duplicate form_scripts
                        $formScripts = FormScript::where('form_version_id', $record->id)->get();
                        foreach ($formScripts as $formScript) {
                            $newFormScript = $formScript->replicate(['id', 'form_version_id', 'created_at', 'updated_at']);
                            $newFormScript->form_version_id = $newVersion->id;
                            $newFormScript->save();
                        }

                        // Redirect to build the new version
                        return redirect()->to('/forms/form-versions/' . $newVersion->id . '/build');
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
