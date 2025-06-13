<?php

namespace App\Filament\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\Element;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ElementsTree extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FormVersionResource::class;

    protected static string $view = 'filament.pages.elements-tree';

    protected static ?string $title = 'Elements Tree';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->model(Element::class)
                ->mutateFormDataUsing(function (array $data) {
                    $data['form_version_id'] = $this->record->id;
                    return $data;
                }),
        ];
    }
}
