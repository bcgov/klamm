<?php

namespace App\Filament\Forms\Resources\FormResource\RelationManagers;

use App\Models\FormVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\Facades\Gate;
use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Forms\Components\DatePicker;

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
                Tables\Columns\TextColumn::make('deployed_to'),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->date('M j, Y')
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
                Tables\Filters\SelectFilter::make('deployed_to')
                    ->options(
                        fn() => FormVersion::query()
                            ->whereNotNull('deployed_to')
                            ->distinct()
                            ->pluck('deployed_to', 'deployed_to')
                            ->toArray()
                    ),
                Tables\Filters\Filter::make('deployed_at')
                    ->form([
                        DatePicker::make('deployed_from'),
                        DatePicker::make('deployed_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['deployed_from'], fn($query) => $query->whereDate('deployed_at', '>=', $data['deployed_from']))
                            ->when($data['deployed_until'], fn($query) => $query->whereDate('deployed_at', '<=', $data['deployed_until']));
                    }),
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
                    ->url(fn() => FormVersionResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(FormVersion $record) => FormVersionResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->url(fn(FormVersion $record) => FormVersionResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
            ])
            ->bulkActions([])
            ->deferLoading()

            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
