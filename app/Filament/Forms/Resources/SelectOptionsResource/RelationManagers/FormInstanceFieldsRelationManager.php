<?php

namespace App\Filament\Resources\SelectOptionsResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use App\Models\FormInstanceField;

class FormInstanceFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'selectOptionInstances';
    protected static ?string $title = 'Forms using this SelectOption';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(function () {
                return FormInstanceField::whereHas('selectOptionInstances', function (Builder $query) {
                    $query->where('select_option_id', $this->ownerRecord->id);
                });
            })
            ->columns([
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
                TextColumn::make('formField.label')
                    ->label('Field Label')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('formField.name')
                    ->label('Field Name')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('formField.datatype.name')
                    ->sortable()
                    ->searchable(),
            ]);
    }
}
