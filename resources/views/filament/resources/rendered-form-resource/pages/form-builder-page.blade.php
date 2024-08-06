<x-filament::page>
    <form wire:submit.prevent="submit">
        <div class="space-y-6">
            {{ $this->form }}
        </div>
    </form>
</x-filament::page>
