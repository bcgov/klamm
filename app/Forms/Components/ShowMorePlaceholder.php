<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Closure;

class ShowMorePlaceholder extends Placeholder
{
    protected string $view = 'forms.components.show-more-placeholder';

    protected string | HtmlString | Closure | null $customContent = null;
    protected int | Closure | null $maxLength = 300;

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function showMoreContent(string | HtmlString | Closure | null $content): static
    {
        $this->customContent = $content;

        return $this;
    }

    public function maxLength(int | Closure | null $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function getShowMoreContent(): string | HtmlString | null
    {
        return $this->evaluate($this->customContent);
    }

    public function getMaxLength(): ?int
    {
        return $this->evaluate($this->maxLength);
    }
}
