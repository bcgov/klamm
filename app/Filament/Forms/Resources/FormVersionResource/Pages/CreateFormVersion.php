<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFormVersion extends CreateRecord
{
    protected static string $resource = FormVersionResource::class;

    public function mount(): void
    {
        parent::mount();

        $formId = request()->query('form_id');
        if ($formId) {
            $this->form->fill([
                'form_id' => $formId,
            ]);
        }
    }
}
