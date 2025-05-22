<?php

namespace App\Filament\Forms\Resources\FieldGroupResource\RelationManagers;

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

class FieldGroupInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldGroupInstances';
    protected static ?string $title = 'Group Instances';

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
            ->recordTitleAttribute('field_group_id')
            ->modifyQueryUsing(function ($query) {
                $query->with([
                    'fieldGroup',
                    'container',
                    'formVersion.form',
                ])
                    ->leftJoin('form_versions', 'field_group_instances.form_version_id', '=', 'form_versions.id')
                    ->leftJoin('forms', 'form_versions.form_id', '=', 'forms.id')
                    ->select('field_group_instances.*'); // Avoids column conflicts
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
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                ]),
                ColumnGroup::make('Group Instance', [
                    TextColumn::make('custom_group_label')
                        ->label('Custom Group Label')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('instance_id')
                        ->label('Instance ID')
                        ->sortable()
                        ->searchable()
                        ->state(fn($record) => $record->instance_id . ($record->custom_instance_id ? ' | ' . $record->custom_instance_id : null))
                        ->tooltip(fn($record) => $record->custom_instance_id ? 'default | custom' : 'default'),
                    IconColumn::make('repeater')
                        ->label('Repeater')
                        ->sortable()
                        ->boolean()
                        ->alignment(Alignment::Center)
                        ->tooltip(fn($record) => $record->repeater === $record->fieldGroup?->repeater ? 'default' : 'custom'),
                    IconColumn::make('clear_button')
                        ->label('Clear Button')
                        ->sortable()
                        ->boolean()
                        ->alignment(Alignment::Center)
                        ->tooltip(fn($record) => $record->clear_button === $record->fieldGroup?->clear_button ? 'default' : 'custom'),
                    IconColumn::make('in_container')
                        ->label('In Container')
                        ->sortable()
                        ->boolean()
                        ->alignment(Alignment::Center)
                        ->state(fn($record) => $record->container?->instance_id ? true : false)
                        ->tooltip(function ($record) {
                            $container = $record->container;
                            return $container
                                ? collect([
                                    $container->instance_id,
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
                SelectFilter::make('repeater')
                    ->label('Repeater')
                    ->options([
                        '1' => 'True',
                        '0' => 'False',
                    ])
                    ->attribute('repeater'),
                SelectFilter::make('clear_button')
                    ->label('Clear Button')
                    ->options([
                        '1' => 'True',
                        '0' => 'False',
                    ])
                    ->attribute('clear_button'),
                Filter::make('in_container')
                    ->label('In Container')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNotNull('container_id')
                    ),
                Filter::make('not_in_container')
                    ->label('Not in Container')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->whereNull('container_id')
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
