<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field">

    @vite(['resources/js/monaco.js'])

    <div
        wire:ignore
        x-data="{
            editor: null,
            completionProviderRegistered: false,
            isInitialized: false,
            suggestions: @js($autocompleteSuggestions ?? []),

            init() {
                // Use a longer delay to ensure Monaco is loaded by Filament's asset system
                setTimeout(() => this.initMonaco(), 500);
            },

            initMonaco() {
                if (typeof window.monaco === 'undefined') {
                    setTimeout(() => this.initMonaco(), 200);
                    return;
                }

                const container = this.$refs.element;
                if (!container) {
                    console.error('Monaco container not found');
                    return;
                }

                try {
                    const initialValue = $wire.{{ $getStatePath() }} || '';

                    const model = window.monaco.editor.createModel(
                        initialValue,
                        '{{ $getLanguage() }}'
                    );

                    this.editor = window.monaco.editor.create(container, {
                        model,
                        theme: '{{ $getTheme() }}',
                        automaticLayout: true,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false,
                        wordWrap: 'on',
                        lineNumbers: 'on',
                        glyphMargin: false,
                        folding: true,
                        lineDecorationsWidth: 10,
                        lineNumbersMinChars: 3
                    });

                    // Update wire state on content change
                    model.onDidChangeContent(() => {
                        $wire.set('{{ $getStatePath() }}', model.getValue());
                    });

                    // Listen for external state changes from Livewire and update editor
                    $wire.$watch('{{ $getStatePath() }}', (value) => {
                        if (value !== model.getValue()) {
                            model.setValue(value || '');
                        }
                    });

                    // Register autocomplete provider only once per language globally
                    if (!window.__monacoProviderRegistered) {
                        window.__monacoProviderRegistered = {};
                    }
                    const lang = '{{ $getLanguage() }}';
                    if (!window.__monacoProviderRegistered[lang] && this.suggestions.length > 0) {
                        window.monaco.languages.registerCompletionItemProvider(lang, {
                            triggerCharacters: ['.', '@', ' '],
                            provideCompletionItems: (model, position) => {
                                const word = model.getWordUntilPosition(position);
                                const range = {
                                    startLineNumber: position.lineNumber,
                                    endLineNumber: position.lineNumber,
                                    startColumn: word.startColumn,
                                    endColumn: word.endColumn
                                };

                                return {
                                    suggestions: this.suggestions.map(item => ({
                                        label: item.label,
                                        kind: window.monaco.languages.CompletionItemKind.Snippet,
                                        insertText: item.insertText || item.label,
                                        detail: item.detail || '',
                                        documentation: item.documentation || '',
                                        range
                                    }))
                                };
                            }
                        });
                        window.__monacoProviderRegistered[lang] = true;
                    }
                    this.isInitialized = true;
                } catch (error) {
                    console.error('Error initializing Monaco Editor:', error);
                }
            }
        }">

        <div
            x-ref="element"
            style="height: {{ $getHeight() }}; width: 100%; border: 1px solid #e5e7eb; border-radius: 6px;"
            x-show="isInitialized">
        </div>

        <!-- Loading indicator -->
        <div x-show="!isInitialized" class="flex items-center justify-center bg-gray-50 border border-gray-200 rounded-md" style="height: {{ $getHeight() }};">
            <div class="text-gray-500 text-sm">Loading Monaco Editor...</div>
        </div>
    </div>
</x-dynamic-component>
