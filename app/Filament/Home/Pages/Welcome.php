<?php

namespace App\Filament\Home\Pages;

use Filament\Pages\Page;

class Welcome extends Page
{
    protected static ?string $navigationGroup = 'Home';
    protected static ?string $navigationLabel = 'Welcome';
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.home.pages.welcome';

    public function mount()
    {
        $this->heading = 'Welcome to Klamm';
        $this->subheading = 'The data capture and classification tool for FODIG, BRE and the Forms Modernization team. Below you can find links to other panels.';
    }
}
