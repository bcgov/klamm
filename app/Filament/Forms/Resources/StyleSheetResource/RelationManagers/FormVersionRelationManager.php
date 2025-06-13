<?php

namespace App\Filament\Forms\Resources\StyleSheetResource\RelationManagers;

use App\Models\FormVersion;
use App\Models\StyleSheet;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FormVersionRelationManager extends RelationManager
{
    protected static string $relationship = 'formVersions';

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
            ->recordTitleAttribute('form_id')
            ->columns([
                ColumnGroup::make('Form', [
                    TextColumn::make('form.form_id')
                        ->label('Form ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('form.form_title')
                        ->label('Form Title')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('form.ministry.short_name')
                        ->label('Ministry')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                ]),
                ColumnGroup::make('Form Version', [
                    TextColumn::make('version_number')
                        ->toggleable()
                        ->searchable(),
                    IconColumn::make('type')
                        ->label('Type')
                        ->icon(fn($state) => match ($state) {
                            'web' => 'heroicon-o-globe-alt',
                            'pdf' => 'heroicon-o-document-text',
                            default => 'heroicon-o-question-mark-circle',
                        })
                        ->color('primary')
                        ->tooltip(fn($record) => StyleSheet::formatType($record->pivot?->type))
                        ->sortable(),
                    TextColumn::make('status')
                        ->toggleable()
                        ->badge()
                        ->color(fn($state) => FormVersion::getStatusColour($state))
                        ->getStateUsing(fn($record) => $record->getFormattedStatusName())
                        ->sortable(),
                    TextColumn::make('deployed_to')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('order')
                        ->label('Style sheet order')
                        ->toggleable()
                        ->sortable(),

                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(FormVersion::getStatusOptions())
                    ->attribute('form_versions.status'),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('view_form_version')
                    ->label('View Form Version')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => route(
                        'filament.forms.resources.form-versions.view',
                        ['record' => $record->id]
                    ))
                    ->openUrlInNewTab()
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
