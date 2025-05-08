<?php

namespace App\Filament\Forms\Widgets;

use App\Models\Form;
use App\Models\FormVersion;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use App\Filament\Resources\CustomActivitylogResource;
use Illuminate\Support\Str;
use App\Traits\HasBusinessAreaAccess;

class YourFormsLogsWidget extends TableWidget
{
    use HasBusinessAreaAccess;

    protected static string $name = 'your-forms-logs-widget';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        if (!$this->hasBusinessAreaAccess()) {
            return $table->query(Activity::whereNull('id'));
        }

        // Get forms that the user has access to through their business areas
        $forms = $this->getAccessibleForms();
        if ($forms->isEmpty()) {
            return $table->query(Activity::whereNull('id'));
        }

        $activityIds = collect();

        foreach ($forms as $form) {
            $activityIds = $activityIds->merge(
                $form->activities()
                    ->pluck('activity_log.id')
            );
        }

        $baseQuery = Activity::query()
            ->whereIn('id', $activityIds)
            ->select('activity_log.*')
            ->with(['subject.form', 'causer'])
            ->orderBy('created_at', 'desc');

        CustomActivitylogResource::withColumns([
            'event',
            'description',
            'causer_name',
            'created_at'
        ]);

        CustomActivitylogResource::withFilters([
            'date',
            'event',
            'causer_name'
        ]);

        $configuredTable = CustomActivitylogResource::configureStandardTable($table);
        $configuredTable->searchable(false);

        // Add form-specific columns
        $formColumns = [
            Tables\Columns\TextColumn::make('subject.form.form_id')
                ->label('Form ID')
                ->url(fn($record): string => $record->subject && $record->subject->form ?
                    route('filament.forms.resources.forms.view', ['record' => $record->subject->form->id]) : '#'),
            Tables\Columns\TextColumn::make('subject.form.form_title')
                ->label('Form Title')
                ->limit(30)
                ->tooltip(function ($record): ?string {
                    $title = optional($record->subject?->form)->form_title ?? '';
                    return Str::length($title) > 30
                        ? $title
                        : null;
                }),
            Tables\Columns\TextColumn::make('subject.version_number')
                ->label('Version')
                ->sortable(false),
            ...array_slice(CustomActivitylogResource::getStandardColumns(), 0)
        ];

        // Add form-specific filters
        $formSearchFilter = Tables\Filters\Filter::make('form_search')
            ->form([
                \Filament\Forms\Components\TextInput::make('search')
                    ->label('Search by Form')
                    ->placeholder('Form ID or Title')
            ])
            ->query(function (Builder $query, array $data): Builder {
                if (empty($data['search'])) {
                    return $query;
                }
                $formIds = Form::query()
                    ->where('form_id', 'like', '%' . $data['search'] . '%')
                    ->orWhere('form_title', 'like', '%' . $data['search'] . '%')
                    ->pluck('id');

                $formVersionIds = FormVersion::whereIn('form_id', $formIds)->pluck('id');

                return $query->whereIn('subject_id', $formVersionIds)
                    ->where('subject_type', FormVersion::class);
            });

        return $configuredTable
            ->query($baseQuery)
            ->columns($formColumns)
            ->filters([
                $formSearchFilter,
                ...CustomActivitylogResource::getStandardFilters()
            ])
            ->defaultSort('activity_log.created_at', 'desc')
            ->paginated(10);
    }
}
