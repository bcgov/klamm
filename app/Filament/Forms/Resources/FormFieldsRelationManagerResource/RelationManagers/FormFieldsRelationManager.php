<?php

namespace App\Filament\Forms\Resources\FormFieldsRelationManagerResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use App\Models\FormField;

class FormFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'selectOptionInstances'; // A placeholder; we override query() anyway
    protected static ?string $title = 'Fields using this SelectOption';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('selectOptionInstances');
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(function () {
                return FormField::whereHas('selectOptionInstances', function (Builder $query) {
                    $query->where('select_option_id', $this->ownerRecord->id);
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
