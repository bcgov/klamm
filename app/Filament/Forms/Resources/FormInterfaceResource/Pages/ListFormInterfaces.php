<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\Pages;

use App\Filament\Forms\Resources\FormInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormMetadata\FormInterface;

class ListFormInterfaces extends ListRecords
{
    protected static string $resource = FormInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: false)->wrap(),
                Tables\Columns\TextColumn::make('formVersions')
                    ->label('Active Forms')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->wrap()
                    ->formatStateUsing(function ($record) {
                        $formNames = $record->formVersions
                            ->map(function ($formVersion) {
                                return optional($formVersion->form)->form_id_title ?? optional($formVersion->form)->name;
                            })
                            ->filter()
                            ->unique()
                            ->values();
                        return $formNames->isNotEmpty() ? $formNames->implode(', ') : '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('formVersions.form', function ($q) use ($search) {
                            $q->where('form_id_title', 'like', "%$search%");
                        });
                    }),
                Tables\Columns\TextColumn::make('type')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('style')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(FormInterface::types())
                    ->label('Type')
                    ->multiple()
                    ->searchable()
                    ->placeholder('All Types')
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? $data['value'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }
                        if (!is_array($values)) {
                            $values = [$values];
                        }
                        return $query->whereIn('type', $values);
                    }),
                SelectFilter::make('form_name')
                    ->label('Active Forms')
                    ->options(function () {
                        return FormVersion::with('form')
                            ->get()
                            ->map(function ($formVersion) {
                                return optional($formVersion->form)->form_id_title ?? optional($formVersion->form)->name;
                            })
                            ->filter()
                            ->unique()
                            ->sort()
                            ->mapWithKeys(fn($name) => [$name => $name])
                            ->toArray();
                    })
                    ->placeholder('All Forms')
                    ->multiple()
                    ->searchable()
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? $data['value'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }
                        return $query->whereHas('formVersions.form', function ($q) use ($values) {
                            $q->whereIn('form_id_title', (array) $values);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }
}
