<?php

namespace App\Filament\Forms\Resources\FormTagResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FormsRelationManager extends RelationManager
{
    protected static string $relationship = 'forms';

    protected static ?string $recordTitleAttribute = 'form_title';

    protected static ?string $title = 'Forms with this Tag';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('form_title')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->select('forms.*');
            })
            ->columns([
                Tables\Columns\TextColumn::make('form_id')
                    ->label('Form ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_title')
                    ->label('Title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ministry.short_name')
                    ->label('Ministry')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('decommissioned')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordSelectSearchColumns(['form_id', 'form_title'])
                    ->recordTitle(fn($record) => "{$record->form_id} - {$record->form_title}")
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ])
            ->defaultSort('forms.form_id');
    }
}
