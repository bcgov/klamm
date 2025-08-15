<?php

namespace App\Filament\Forms\Resources\FormResource\RelationManagers;

use App\Models\FormBuilding\FormVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\Facades\Gate;
use App\Filament\Forms\Resources\FormVersionResource;
use App\Helpers\FormVersionHelper;
use Filament\Forms\Components\DatePicker;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormVersionFormDataSource;
use App\Models\FormBuilding\FormElementDataBinding;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;

class FormVersionRelationManager extends RelationManager
{
    protected static string $relationship = 'formVersions';

    protected static ?string $recordTitleAttribute = 'version_number';

    protected static ?string $title = 'Form Versions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('formatted_status')
                    ->label('Status')
                    ->getStateUsing(fn($record) => $record->getFormattedStatusName()),
                Tables\Columns\TextColumn::make('formDeveloper.name')
                    ->label('Developer')
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
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options(fn() => FormVersion::getStatusOptions()),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    }),
                Tables\Filters\Filter::make('updated_at')
                    ->form([
                        DatePicker::make('updated_from'),
                        DatePicker::make('updated_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['updated_from'], fn($query) => $query->whereDate('updated_at', '>=', $data['updated_from']))
                            ->when($data['updated_until'], fn($query) => $query->whereDate('updated_at', '<=', $data['updated_until']));
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(fn() => FormVersionResource::getUrl('create', ['form_id' => $this->ownerRecord->id])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(FormVersion $record) => FormVersionResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->url(fn(FormVersion $record) => FormVersionResource::getUrl('edit', ['record' => $record]))
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
                                FormElementDataBinding::create([
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
                                $newElementable = $element->elementable_type::create($elementableData);
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
                            FormVersionFormDataSource::create([
                                'form_version_id' => $newVersion->id,
                                'form_data_source_id' => $formDataSource->form_data_source_id,
                                'order' => $formDataSource->order,
                            ]);
                        }

                        // Duplicate form interfaces
                        foreach ($record->formVersionFormInterfaces as $formInterface) {
                            \App\Models\FormBuilding\FormVersionFormInterface::create([
                                'form_version_id' => $newVersion->id,
                                'form_interface_id' => $formInterface->form_interface_id,
                                'order' => $formInterface->order,
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
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn($record) => $record->status === 'published' && Gate::allows('form-developer'))
                    ->action(function ($record) {
                        $record->update(['status' => 'archived']);
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->tooltip('Archive this form version'),
            ])
            ->bulkActions([])
            // ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
