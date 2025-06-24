<?php

namespace App\Filament\Forms\Resources\FormFieldResource\RelationManagers;

use App\Models\FormVersion;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FormInstanceFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'formInstanceFields';
    protected static ?string $title = 'Field Instances';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('form_field_id')
            ->modifyQueryUsing(function ($query) {
                $query->with([
                    'container',
                    'fieldGroupInstance.container',
                    'formVersion.form',
                ])
                    ->leftJoin('form_versions', 'form_instance_fields.form_version_id', '=', 'form_versions.id')
                    ->leftJoin('forms', 'form_versions.form_id', '=', 'forms.id')
                    ->select('form_instance_fields.*'); // Prevents column conflicts
            })
            ->columns([
                ColumnGroup::make('In Form Version', [
                    TextColumn::make('formVersion.form.form_id')
                        ->label('Form ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.form.form_title')
                        ->label('Form title')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.version_number')
                        ->label('Version')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.status')
                        ->label('Status')
                        ->badge()
                        ->color(fn($state) => FormVersion::getStatusColour($state))
                        ->getStateUsing(fn($record) => $record->formVersion->getFormattedStatusName())
                        ->toggleable()
                        ->sortable()
                        ->searchable()
                ]),
                ColumnGroup::make('Field Instance', [
                    TextColumn::make('custom_label')
                        ->label('Custom Field Label')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('instance_id')
                        ->label('Instance ID')
                        ->sortable()
                        ->searchable()
                        ->state(fn($record) => $record->instance_id . ($record->custom_instance_id ? ' | ' . $record->custom_instance_id : null))
                        ->tooltip(fn($record) => $record->custom_instance_id ? 'default | custom' : 'default'),
                    IconColumn::make('in_group')
                        ->label('In Group')
                        ->sortable()
                        ->boolean()
                        ->alignment(Alignment::Center)
                        ->state(fn($record) => $record->fieldGroupInstance?->instance_id ? true : false)
                        ->tooltip(function ($record) {
                            return $record->fieldGroupInstance?->instance_id
                                ? collect([
                                    $record->fieldGroupInstance->instance_id,
                                    $record->fieldGroupInstance->custom_instance_id,
                                ])->filter()->join(' | ')
                                : 'Not in a Group';
                        }),
                    IconColumn::make('in_container')
                        ->label('In Container')
                        ->sortable()
                        ->boolean()
                        ->alignment(Alignment::Center)
                        ->state(fn($record) =>  $record->container?->instance_id || $record->fieldGroupInstance?->container_id ? true : false)
                        ->tooltip(function ($record) {
                            $container = $record->container ?? $record->fieldGroupInstance?->container;
                            return $container
                                ? collect([
                                    $container?->instance_id,
                                    $container->custom_instance_id
                                ])->filter()->join(' | ')
                                : 'Not in a Container';
                        }),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(FormVersion::getStatusOptions())
                    ->attribute('form_versions.status'),
                Filter::make('in_group')
                    ->label('In Group')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNotNull('field_group_instance_id')
                    ),
                Filter::make('not_in_group')
                    ->label('Not in Group')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNull('field_group_instance_id')
                    ),
                Filter::make('in_container')
                    ->label('In Container')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where(function ($query) {
                            $query->whereNotNull('container_id')
                                ->orWhereHas('fieldGroupInstance', function ($query) {
                                    $query->whereNotNull('container_id');
                                });
                        })
                    ),
                Filter::make('not_in_container')
                    ->label('Not in Container')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNull('container_id')
                            ->whereDoesntHave('fieldGroupInstance', function ($query) {
                                $query->whereNotNull('container_id');
                            })
                    ),
            ])
            ->persistFiltersInSession()
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('view_form_version')
                    ->label('View Form Version')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => route(
                        'filament.forms.resources.form-versions.view',
                        ['record' => $record->formVersion?->id]
                    ))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->formVersion !== null),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
