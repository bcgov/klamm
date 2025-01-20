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
        $this->heading = 'Welcome to KLAMM';
        $this->subheading = 'KLAMM is a tool used to collect and organize data by classifying it, making the information easier to understand and analyze.';
        $this->getStarted = 'Select a panel below to get started.';
    }
}
