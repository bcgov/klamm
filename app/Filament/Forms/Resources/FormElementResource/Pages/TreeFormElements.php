<?php

namespace App\Filament\Forms\Resources\FormElementResource\Pages;

use App\Filament\Forms\Resources\FormElementResource;
use Filament\Pages\Actions\CreateAction;
use SolutionForest\FilamentTree\Actions;
use SolutionForest\FilamentTree\Concern;
use SolutionForest\FilamentTree\Resources\Pages\TreePage as BasePage;
use SolutionForest\FilamentTree\Support\Utils;

class TreeFormElements extends BasePage
{
    protected static string $resource = FormElementResource::class;

    protected static int $maxDepth = 5;

    protected function getActions(): array
    {
        return [
            $this->getCreateAction(),
        ];
    }

    protected function hasDeleteAction(): bool
    {
        return true;
    }

    protected function hasEditAction(): bool
    {
        return true;
    }

    protected function hasViewAction(): bool
    {
        return true;
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getTreeRecordIcon(?\Illuminate\Database\Eloquent\Model $record = null): ?string
    {
        if (!$record) {
            return 'heroicon-o-squares-2x2';
        }

        // You can customize icons based on the element type
        return match ($record->elementable_type) {
            'App\Models\ContainerFormElement' => 'heroicon-o-rectangle-stack',
            'App\Models\TextInputFormElement' => 'heroicon-o-pencil',
            'App\Models\CheckboxInputFormElement' => 'heroicon-o-check-circle',
            'App\Models\SelectInputFormElement' => 'heroicon-o-chevron-down',
            'App\Models\RadioInputFormElement' => 'heroicon-o-radio',
            'App\Models\TextareaInputFormElement' => 'heroicon-o-document-text',
            'App\Models\NumberInputFormElement' => 'heroicon-o-hashtag',
            'App\Models\DateSelectInputFormElement' => 'heroicon-o-calendar',
            'App\Models\ButtonInputFormElement' => 'heroicon-o-cursor-arrow-rays',
            'App\Models\HTMLFormElement' => 'heroicon-o-code-bracket',
            default => 'heroicon-o-squares-2x2',
        };
    }
}
