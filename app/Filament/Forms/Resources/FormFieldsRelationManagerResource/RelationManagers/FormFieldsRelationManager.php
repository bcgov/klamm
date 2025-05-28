<?php

namespace App\Filament\Forms\Resources\FormFieldsRelationManagerResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use App\Models\FormField;

class FormFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'selectableValueInstances'; // A placeholder; we override query() anyway
    protected static ?string $title = 'Fields using this Selectable Value';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('selectableValueInstances');
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(function () {
                return FormField::whereHas('selectableValueInstances', function (Builder $query) {
                    $query->where('selectable_value_id', $this->ownerRecord->id);
                });
            })
            ->columns([
                TextColumn::make('label')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('dataType.name')
                    ->label('Data Type')
                    ->sortable(),
            ]);
    }
}
