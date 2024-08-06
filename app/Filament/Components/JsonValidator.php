<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Component;

class JsonValidator extends Component
{
    protected string $view = 'components.json-validator';

    public static function make(): static
    {
        return new static();
    }

    public function jsonToValidate(string $jsonField): static
    {
        $this->statePath($jsonField);
        return $this;
    }
}
