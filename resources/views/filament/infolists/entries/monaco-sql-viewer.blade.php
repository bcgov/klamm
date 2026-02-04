@php
$state = $getState();
$value = is_string($state) ? $state : '';

$language = $language ?? 'sql';
$theme = $theme ?? 'vs-dark';
$height = $height ?? '475px';
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry">
    @vite(['resources/js/monaco.js'])

    <div
        wire:ignore
        x-data="{
            editor: null,
            isInitialized: false,

            init() {
                // Monaco loads async; poll until available.
                setTimeout(() => this.initMonaco(), 250);
            },

            initMonaco() {
                if (typeof window.monaco === 'undefined') {
                    setTimeout(() => this.initMonaco(), 200);
                    return;
                }

                const container = this.$refs.element;
                if (!container) {
                    return;
                }

                try {
                    // Prevent duplicate editors if the entry re-renders.
                    if (this.editor) {
                        this.editor.dispose();
                        this.editor = null;
                    }

                    const initialValue = @js($value);
                    const model = window.monaco.editor.createModel(initialValue || '', @js($language));

                    this.editor = window.monaco.editor.create(container, {
                        model,
                        theme: @js($theme),
                        readOnly: true,
                        domReadOnly: true,
                        automaticLayout: true,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false,
                        wordWrap: 'on',
                        lineNumbers: 'on',
                        glyphMargin: false,
                        folding: true,
                        lineDecorationsWidth: 10,
                        lineNumbersMinChars: 3,
                    });

                    this.isInitialized = true;
                } catch (error) {
                    console.error('Error initializing Monaco viewer:', error);
                }
            },
        }">
        <div
            x-ref="element"
            x-bind:style="'height: ' + @js($height) + '; width: 100%; border: 1px solid #e5e7eb; border-radius: 6px;'"
            x-show="isInitialized"></div>

        <div
            x-show="!isInitialized"
            class="flex items-center justify-center bg-gray-50 border border-gray-200 rounded-md"
            x-bind:style="'height: ' + @js($height) + ';'">
            <div class="text-gray-500 text-sm">Loading Monaco Editor...</div>
        </div>
    </div>
</x-dynamic-component>
