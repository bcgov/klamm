<?php

namespace App\Filament\Forms\Widgets;

use App\Models\Form;
use App\Models\BusinessArea;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class FormMigrationWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'IE 11 Compatibility Forms';
    protected ?string $description = null;

    protected function getStats(): array
    {
        $businessAreaId = $this->filters['businessAreaId'] ?? null;

        $query = Form::whereHas('formTags', function ($q) {
            $q->where('name', 'migration2025');
        });

        if ($businessAreaId) {
            $query->whereHas('businessAreas', function ($q) use ($businessAreaId) {
                $q->where('business_areas.id', $businessAreaId);
            });
        }

        $forms = $query->with(['formVersions'])->get();

        $total = $forms->count();

        $completed = 0;
        $inProgress = 0;
        $toBeDone = 0;

        foreach ($forms as $form) {
            $versions = $form->formVersions;
            $hasPublished = $versions->contains(fn($v) => $v->status === 'published');
            $hasDraftOrReview = $versions->contains(fn($v) => in_array($v->status, ['draft', 'under_review']));

            if ($hasPublished) {
                $completed++;
            } elseif ($hasDraftOrReview) {
                $inProgress++;
            } else {
                $toBeDone++;
            }
        }

        $percent = fn($count) => $total > 0 ? round(($count / $total) * 100) : 0;

        $progressBar = function ($percent, $color) {
            return <<<HTML
<div style="width:100%;background:#f1f5f9;border-radius:4px;height:12px;overflow:hidden;margin-top:4px;">
  <div style="width:{$percent}%;background:{$color};height:100%;transition:width .3s;"></div>
</div>
<span style="font-size:12px;color:#64748b;">{$percent}% of total</span>
HTML;
        };

        $businessAreasUrl = route('filament.forms.resources.business-areas.index', ['showAllColumns' => 1]);
        $businessAreasLink = new HtmlString('<a href="' . $businessAreasUrl . '" style="display:inline-block;margin-top:16px;font-weight:500;color:#2563eb;" class="text-primary underline hover:text-primary-dark">Form status by business areas</a>');

        return [
            Stat::make('Total Forms', $total)
                ->description($businessAreasLink)
                ->color('primary')
                ->icon('heroicon-o-document-text'),
            Stat::make('Forms To Be Done', $toBeDone)
                ->description(new HtmlString($progressBar($percent($toBeDone), '#FBDA9D'))),
            Stat::make('Forms In Progress', $inProgress)
                ->description(new HtmlString($progressBar($percent($inProgress), '#91C4FA'))),
            Stat::make('Forms Completed', $completed)
                ->description(new HtmlString($progressBar($percent($completed), '#52BC7C'))),
        ];
    }

    public static function canView(): bool
    {
        return Gate::allows('admin');
    }
}
