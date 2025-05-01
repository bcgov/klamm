<?php

namespace App\Filament\Forms\Widgets;

use Filament\Widgets\Widget;

class FormsDescriptionWidget extends Widget
{
    protected static string $view = 'filament.forms.widgets.forms-description';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return true;
    }
}
