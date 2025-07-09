<?php

namespace App\Filament\Plugins\MonacoEditor;

use WeStacks\FilamentMonacoEditor\MonacoEditor;

// Extend the MonacoEditor plugin to enable the automcomplete feature for custom uuids and fields
class CustomMonacoEditor extends MonacoEditor
{
    /**
     * Custom autocomplete suggestions for the Monaco editor.
     * Can be an array or a closure for dynamic suggestions.
     */
    protected $autocompleteSuggestions = [];

    /**
     * Set custom autocomplete suggestions.
     *
     * @param array|callable $suggestions
     * @return static
     */
    public function autocomplete($suggestions): static
    {
        $this->autocompleteSuggestions = $suggestions;
        return $this;
    }

    /**
     * Get the autocomplete suggestions for the view.
     *
     * @return array
     */
    public function getAutocompleteSuggestions(): array
    {
        if (is_callable($this->autocompleteSuggestions)) {
            $get = function ($key) {
                return $this->getState()[$key] ?? null;
            };
            $livewire = $this->getLivewire();
            return call_user_func($this->autocompleteSuggestions, $get, $livewire);
        }
        return $this->autocompleteSuggestions;
    }

    /**
     * Pass autocomplete data to the view and use a custom view.
     */
    protected string $view = 'vendor.filament-monaco-editor.custom-monaco-editor';

    public function getViewData(): array
    {
        // Merge parent data with custom autocomplete suggestions
        return array_merge(parent::getViewData(), [
            'autocompleteSuggestions' => $this->getAutocompleteSuggestions(),
        ]);
    }
}
