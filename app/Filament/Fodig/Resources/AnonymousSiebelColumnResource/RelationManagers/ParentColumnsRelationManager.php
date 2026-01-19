<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Relation manager showing parent (source) columns that this column references via foreign keys.
class ParentColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'parentColumns';

    protected static ?string $title = 'Parent Columns (Foreign Key Sources)';

    protected static ?string $recordTitleAttribute = 'column_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('parent_column_info')
                    ->label('Parent Column Details')
                    ->content(function (?AnonymousSiebelColumn $record): string {
                        if (! $record) {
                            return 'Select a parent column to view details.';
                        }

                        $table = $record->table;
                        $schema = $table?->schema;
                        $database = $schema?->database;
                        $path = collect([
                            $database?->database_name,
                            $schema?->schema_name,
                            $table?->table_name,
                            $record->column_name,
                        ])->filter()->implode('.');

                        return '<strong>Full Path:</strong> ' . $path;
                    })
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('column_name')
            ->heading('Parent Columns (Foreign Key Sources)')
            ->description('Columns that this column references via foreign keys.')
            ->columns([
                Tables\Columns\TextColumn::make('column_name')
                    ->label('Column')
                    ->url(fn($record) => \App\Filament\Fodig\Resources\AnonymousSiebelColumnResource::getUrl('view', ['record' => $record->id]))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('table.table_name')
                    ->label('Table')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('table.schema.schema_name')
                    ->label('Schema')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search): array {
                                return AnonymousSiebelColumn::query()
                                    ->where('column_name', 'like', "%{$search}%")
                                    ->orWhereHas('table', function (Builder $query) use ($search) {
                                        $query->where('table_name', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (AnonymousSiebelColumn $column) {
                                        $table = $column->table;
                                        $schema = $table?->schema;
                                        $path = collect([
                                            $schema?->schema_name,
                                            $table?->table_name,
                                            $column->column_name,
                                        ])->filter()->implode('.');
                                        return [$column->id => $path];
                                    })
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): string {
                                $column = AnonymousSiebelColumn::find($value);
                                if (! $column) {
                                    return 'Unknown column';
                                }
                                $table = $column->table;
                                $schema = $table?->schema;
                                return collect([
                                    $schema?->schema_name,
                                    $table?->table_name,
                                    $column->column_name,
                                ])->filter()->implode('.');
                            }),
                    ])
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(fn(Tables\Actions\EditAction $action): array => [
                        Forms\Components\Placeholder::make('parent_column_info')
                            ->label('Parent Column')
                            ->content(function ($record): string {
                                $table = $record->table;
                                $schema = $table?->schema;
                                $database = $schema?->database;
                                $path = collect([
                                    $database?->database_name,
                                    $schema?->schema_name,
                                    $table?->table_name,
                                    $record->column_name,
                                ])->filter()->implode('.');

                                return $path;
                            }),
                    ]),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
