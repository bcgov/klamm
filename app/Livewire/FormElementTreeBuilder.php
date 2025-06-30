<?php

namespace App\Livewire;

use App\Filament\Resources\FormVersionResource\Widgets\FormElementWidget;
use Livewire\Component;

class FormElementTreeBuilder extends Component
{
    public $formVersionId;

    public function mount($formVersionId = null)
    {
        $this->formVersionId = $formVersionId;
    }

    public function render()
    {
        return view('livewire.form-element-tree-builder');
    }

    protected function getWidgets(): array
    {
        return [
            FormElementWidget::class => [
                'formVersionId' => $this->formVersionId,
            ],
        ];
    }
}
