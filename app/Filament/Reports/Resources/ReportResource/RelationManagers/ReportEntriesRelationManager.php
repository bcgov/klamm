<?php

namespace App\Filament\Reports\Resources\ReportResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;


class ReportEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'reportEntries';

    protected static ?string $label = 'Report Label';

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
            ->columns([
                Tables\Columns\TextColumn::make('data_matching_rate')
                    ->badge()
                    ->colors([
                        'success' => static fn($state): bool => $state === 'Low',
                        'warning' => static fn($state): bool => $state === 'medium',
                        'danger' => static fn($state): bool => $state === 'High',
                    ]),
                Tables\Columns\TextColumn::make('reportBusinessArea.name'),
                Tables\Columns\TextColumn::make('existing_label'),
                Tables\Columns\TextColumn::make('labelSource.name'),
                Tables\Columns\TextColumn::make('data_field'),
                Tables\Columns\TextColumn::make('icm_data_field_path')
                    ->label('ICM Data Field Path'),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Action::make('view')
                    ->label('')
                    ->url(fn($record) => route('filament.reports.resources.report-entries.view', ['record' => $record->id]))
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([
                //
            ]);
    }
}
