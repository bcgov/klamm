<x-filament-panels::page class="fi-resource-page-build">
    <!-- Sticky Add Form Element Button -->
    @if($this->isEditable())
    <div id="sticky-add-element-btn" class="sticky-add-element-button" style="display: none;">
        <div class="sticky-buttons-container">
            <button type="button"
                class="fi-btn fi-btn-color-primary fi-btn-outlined fi-btn-size-sm"
                onclick="openPreviewForm()">
                @svg('heroicon-o-tv', 'fi-btn-icon')
                <span class="fi-btn-label">Preview</span>
            </button>
            <button type="button"
                class="fi-btn fi-btn-color-success fi-btn-outlined fi-btn-size-sm"
                onclick="openAddElementModal()">
                @svg('heroicon-o-plus-circle', 'fi-btn-icon')
                <span class="fi-btn-label">Add Element</span>
            </button>
            <button type="button"
                class="fi-btn fi-btn-color-warning fi-btn-outlined fi-btn-size-sm"
                onclick="saveFormVersion()">
                @svg('heroicon-o-check', 'fi-btn-icon')
                <span class="fi-btn-label">Save</span>
            </button>
        </div>
    </div>
    @endif

    <div class="fi-section-content-ctn form-element-tree">
        {{ $this->form }}
    </div>

    @push('styles')
    <style>
        .sticky-add-element-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 5;
            transition: all 0.3s ease-in-out;
            opacity: 0;
            transform: translateY(10px);
        }

        .sticky-add-element-button.show {
            opacity: 1;
            transform: translateY(0);
        }

        .sticky-buttons-container {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .sticky-add-element-button button {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            background: white;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .sticky-add-element-button button.fi-btn-color-primary {
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }

        .sticky-add-element-button button.fi-btn-color-success {
            border: 1px solid #10b981;
            color: #10b981;
        }

        .sticky-add-element-button button.fi-btn-color-warning {
            border: 1px solid #f59e0b;
            color: #f59e0b;
        }

        .sticky-add-element-button button svg {
            width: 1rem;
            height: 1rem;
        }

        .sticky-add-element-button button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .sticky-add-element-button button.fi-btn-color-primary:hover {
            background: #3b82f6;
            color: white;
        }

        .sticky-add-element-button button.fi-btn-color-success:hover {
            background: #10b981;
            color: white;
        }

        .sticky-add-element-button button.fi-btn-color-warning:hover {
            background: #f59e0b;
            color: white;
        }

        .sticky-add-element-button button:active {
            transform: translateY(0);
        }

        /* Ensure the button doesn't interfere with other UI elements */
        @media (max-width: 768px) {
            .sticky-add-element-button {
                right: 10px;
                bottom: 10px;
            }

            .sticky-add-element-button button {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .sticky-add-element-button button svg {
                width: 0.875rem;
                height: 0.875rem;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        // Set editable state from PHP
        const isFormEditable = @json($this->isEditable());

        // Real-time form version update handling
        document.addEventListener('DOMContentLoaded', function() {
            let updateTimeout;
            const DEBOUNCE_DELAY = 1000;

            // Function to trigger draft update
            function triggerDraftUpdate() {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(() => {
                    window.Livewire.find('{{ $this->getId() }}').call('onFormDataUpdated');
                }, DEBOUNCE_DELAY);
            }

            // Monitor form input changes
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('input', triggerDraftUpdate);
                form.addEventListener('change', triggerDraftUpdate);
            }

            // Monitor Monaco editor changes if they exist
            let monacoCheckInterval = setInterval(() => {
                if (window.monaco && window.monaco.editor) {
                    const editors = window.monaco.editor.getModels();

                    editors.forEach(model => {
                        model.onDidChangeContent(() => {
                            clearTimeout(updateTimeout);
                            updateTimeout = setTimeout(() => {
                                window.Livewire.find('{{ $this->getId() }}').call('onFormDataUpdated');
                            }, 2000); // Longer debounce for code editors
                        });
                    });

                    if (editors.length > 0) {
                        clearInterval(monacoCheckInterval);
                    }
                }
            }, 500);

            // Clear interval after 10 seconds if Monaco isn't found
            setTimeout(() => {
                clearInterval(monacoCheckInterval);
            }, 10000);

            // Add keyboard shortcut for saving (Ctrl/Cmd + S)
            document.addEventListener('keydown', function(event) {
                // Check if Ctrl+S (Windows/Linux) or Cmd+S (Mac) is pressed
                if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                    event.preventDefault(); // Prevent browser's default save dialog

                    // Only allow saving if the form is editable
                    if (isFormEditable) {
                        saveFormVersion();
                    }
                }
            });

            // Sticky button functionality
            const stickyButton = document.getElementById('sticky-add-element-btn');
            if (stickyButton) {
                const SCROLL_THRESHOLD = 200; // Show button after scrolling 200px

                function updateStickyButton() {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    const shouldShow = scrollTop > SCROLL_THRESHOLD;

                    if (shouldShow) {
                        if (stickyButton.style.display === 'none' || !stickyButton.classList.contains('show')) {
                            stickyButton.style.display = 'block';
                            stickyButton.classList.add('show');
                        }
                    } else {
                        if (stickyButton.classList.contains('show')) {
                            stickyButton.classList.remove('show');
                            setTimeout(() => {
                                if (!stickyButton.classList.contains('show')) {
                                    stickyButton.style.display = 'none';
                                }
                            }, 300);
                        }
                    }
                }

                // Listen to scroll events
                window.addEventListener('scroll', updateStickyButton);

                // Check button state regularly (every 1 second)
                setInterval(updateStickyButton, 1000);

                // Initial check
                updateStickyButton();

                // Force check after any click on the page (catches modal interactions)
                document.addEventListener('click', function() {
                    setTimeout(updateStickyButton, 100);
                });

                // Force check on any key press (catches ESC to close modal)
                document.addEventListener('keydown', function() {
                    setTimeout(updateStickyButton, 100);
                });
            }
        });

        // Function to open the add element modal (triggered by sticky button)
        function openAddElementModal() {
            // Find the original "Add Form Element" button in the header and click it
            const originalButton = document.querySelector('[data-action="add_form_element"]');
            if (originalButton) {
                originalButton.click();
            } else {
                // Fallback: try to find button by text content
                const buttons = document.querySelectorAll('button');
                for (let button of buttons) {
                    if (button.textContent.includes('Add Form Element')) {
                        button.click();
                        break;
                    }
                }
            }
        }

        // Function to open the preview form (triggered by sticky button)
        function openPreviewForm() {
            const formVersionId = '{{ $this->record->id }}';
            const previewBaseUrl = '{{ env("FORM_PREVIEW_URL", "") }}';
            const previewUrl = previewBaseUrl.replace(/\/$/, '') + '/preview/' + formVersionId;
            window.open(previewUrl, '_blank');
        }

        // Function to save the form version (triggered by sticky button)
        function saveFormVersion() {
            // Find the tree widget's save button and click it
            const treeSaveButton = document.querySelector('button[data-action="save"]');
            if (treeSaveButton) {
                treeSaveButton.click();
            } else {
                // Fallback: try to find any save button
                const saveButtons = document.querySelectorAll('button');
                for (let button of saveButtons) {
                    if (button.textContent.includes('Save') && button.hasAttribute('wire:loading')) {
                        button.click();
                        break;
                    }
                }
            }
        }
    </script>
    @endpush
</x-filament-panels::page>