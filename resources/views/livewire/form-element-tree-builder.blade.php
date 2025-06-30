<div>
    @if($formVersionId)
    @livewire(\App\Filament\Resources\FormVersionResource\Widgets\FormElementWidget::class, ['formVersionId' => $formVersionId])
    @else
    <div class="text-center py-8">
        <p class="text-gray-500">Please save the form version first to manage form elements.</p>
    </div>
    @endif
</div>