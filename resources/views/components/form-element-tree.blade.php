<div class="form-element-tree-container">
    @if($formVersionId)
    @livewire(\App\Livewire\FormElementTreeBuilder::class, ['formVersionId' => $formVersionId, 'editable' => $editable ?? true], key('form-element-tree-' . $formVersionId))
    @else
    <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
        <div class="text-gray-400 mb-2">
            <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Form Version</h3>
        <p class="text-gray-500">Please save the form version first to manage form elements.</p>
    </div>
    @endif
</div>