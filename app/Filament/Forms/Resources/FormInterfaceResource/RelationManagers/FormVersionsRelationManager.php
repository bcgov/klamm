<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use App\Models\FormBuilding\FormVersion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class FormVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'formVersions';
    protected static ?string $title = 'Form Versions Using This Interface';

    public function table(Table $table): Table
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
                SelectFilter::make('status')
                    ->options(FormVersion::getStatusOptions())
                    ->label('Status')
                    ->placeholder('All Statuses'),
            ])
            ->recordUrl(function ($record) {
                return route('filament.forms.resources.form-versions.view', ['record' => $record->id]);
            });
    }
}
