<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Models\Anonymizer\AnonymizationJobs;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobsRelationManager extends RelationManager
{
    protected static string $relationship = 'jobs';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Jobs using this method';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Jobs using this method')
            ->description('Jobs that include this anonymization method in their column scope.')
            ->modifyQueryUsing(function (Builder $query) {
                $methodId = $this->getOwnerRecord()?->getKey();
                if (! $methodId) {
                    return $query;
                }

                return $query
                    ->select([
                        'anonymization_jobs.id',
                        'anonymization_jobs.name',
                        'anonymization_jobs.job_type',
                        'anonymization_jobs.output_format',
                        'anonymization_jobs.status',
                        'anonymization_jobs.last_run_at',
                        'anonymization_jobs.updated_at',
                    ])
                    ->distinct()
                    ->selectSub(
                        DB::table('anonymization_job_columns')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('anonymization_job_columns.job_id', 'anonymization_jobs.id')
                            ->where('anonymization_job_columns.anonymization_method_id', $methodId),
                        'columns_affected_count'
                    )
                    ->orderBy('anonymization_jobs.name');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Job')
                    ->sortable()
                    ->searchable()
                    ->url(fn(AnonymizationJobs $record) => AnonymizationJobResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab()
                    ->wrap(),
                Tables\Columns\TextColumn::make('job_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => AnonymizationJobResource::jobTypeOptions()[$state] ?? Str::headline($state))
                    ->color(fn(string $state) => $state === AnonymizationJobs::TYPE_FULL ? 'primary' : 'info'),
                Tables\Columns\TextColumn::make('output_format')
                    ->label('Output')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => AnonymizationJobResource::outputFormatOptions()[$state] ?? Str::upper($state))
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Str::headline($state)),
                Tables\Columns\TextColumn::make('columns_affected_count')
                    ->label('Columns')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last run')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(AnonymizationJobs $record) => AnonymizationJobResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn(AnonymizationJobs $record) => AnonymizationJobResource::getUrl('edit', ['record' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}
