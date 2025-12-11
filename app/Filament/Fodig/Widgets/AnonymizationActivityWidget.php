<?php

namespace App\Filament\Fodig\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\Anonymizer\AnonymousUpload;
use App\Models\ChangeTicket;
use App\Filament\Fodig\Resources\ChangeTicketResource;
use Illuminate\Support\Str;

class AnonymizationActivityWidget extends TableWidget
{
    protected static string $name = 'anonymization-activity-widget';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $relatedTypes = [
            AnonymousSiebelColumn::class,
            AnonymousSiebelTable::class,
            AnonymousSiebelSchema::class,
            AnonymousSiebelDatabase::class,
            AnonymousUpload::class,
        ];

        $baseQuery = Activity::query()
            ->whereIn('subject_type', $relatedTypes)
            ->latest();

        $openTickets = ChangeTicket::query()->where('status', 'open')->count();

        $latestUploadId = AnonymousUpload::query()->max('id');

        return $table
            ->heading('Recent Anonymization Activity')
            ->description("Open change tickets: {$openTickets}")
            ->query(fn(): Builder => $baseQuery->select('activity_log.*')->with(['causer']))
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Event')
                    ->formatStateUsing(fn($state) => Str::of($state)->headline())
                    ->wrap(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->formatStateUsing(fn($state) => class_basename((string) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->default('system')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('At')
                    ->dateTime()
                    ->sortable()
            ])
            ->filters([
                Tables\Filters\Filter::make('attention_required')
                    ->label('Attention Required')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        // When enabled, focus on high-signal changes: creations, deletions, and updates to core anonymizer entities.
                        // Spatie Activity commonly sets the 'event' to 'created', 'updated', 'deleted'.
                        $query->whereIn('event', ['created', 'deleted'])
                            ->orWhere(function ($q) {
                                $q->where('event', 'updated')
                                    ->whereIn('subject_type', [
                                        AnonymousSiebelColumn::class,
                                        AnonymousSiebelTable::class,
                                        AnonymousSiebelSchema::class,
                                        AnonymousSiebelDatabase::class,
                                    ]);
                            });
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(collect($relatedTypes)->mapWithKeys(fn($c) => [$c => class_basename($c)])->all()),
                Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['from'])) {
                            $query->whereDate('created_at', '>=', $data['from']);
                        }
                        if (! empty($data['to'])) {
                            $query->whereDate('created_at', '<=', $data['to']);
                        }
                        return $query;
                    }),
            ])
            ->defaultSort('activity_log.created_at', 'desc')
            ->paginated(10)
            ->emptyStateHeading('No recent anonymization activity')
            ->emptyStateDescription('Activity from metadata uploads, schema/table/column changes will appear here.')
            ->headerActions([
                Tables\Actions\Action::make('tickets')
                    ->label('Open Change Tickets')
                    ->url(fn() => ChangeTicketResource::getUrl('index'), true)
                    ->visible(fn() => true),
                Tables\Actions\Action::make('latestTickets')
                    ->label('Latest Upload Tickets')
                    ->url(fn() => ChangeTicketResource::getUrl('index'), true)
                    ->visible(fn() => ! is_null($latestUploadId)),
            ]);
    }
}
