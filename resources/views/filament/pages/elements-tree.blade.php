<x-filament-panels::page>
    @livewire(App\Filament\Resources\FormVersionResource\Widgets\ElementsTreeWidget::class, [
    'formVersionId' => $record->id
    ])
</x-filament-panels::page>