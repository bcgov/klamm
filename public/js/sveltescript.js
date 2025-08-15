// Required to dispatch Svelte updates from external scripts
// This script listens for input changes, attribute mutations, and overrides property setters
// to ensure Svelte components receive updates when external scripts modify the DOM.
// It handles inputs, selects, textareas, and options, dispatching 'external-update'
// events with the current value, and also fires bubbling 'input' events for inputs/selects/textareas.
(function () {
    // Track which elements are currently dispatching updates (to prevent recursion)
    const dispatchingElements = new WeakSet();

    /**
     * Dispatch Svelte update events on an element.
     * Fires 'external-update' with current value and
     * for inputs/selects/textareas, also fires bubbling 'input' event.
     * Skips if this element is already dispatching to prevent recursion.
     * @param {HTMLElement} target
     */
    function dispatchSvelteUpdate(target) {
        if (!target || dispatchingElements.has(target)) return;

        dispatchingElements.add(target);
        try {
            const tag = target.tagName;

            let value;
            if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") {
                // Special-case checkboxes/radios: use boolean checked
                if (
                    tag === "INPUT" &&
                    (target.type === "checkbox" || target.type === "radio")
                ) {
                    target.dispatchEvent(
                        new CustomEvent("external-update", {
                            detail: {
                                checked: target.checked,
                                value: target.value,
                            },
                        })
                    );
                    // Prefer change for toggles
                    target.dispatchEvent(
                        new Event("change", { bubbles: true })
                    );
                } else {
                    value = target.value;
                    target.dispatchEvent(
                        new CustomEvent("external-update", {
                            detail: { value },
                        })
                    );
                    target.dispatchEvent(new Event("input", { bubbles: true }));
                }
            } else {
                value = target.textContent;
                target.dispatchEvent(
                    new CustomEvent("external-update", { detail: { value } })
                );
            }
        } finally {
            dispatchingElements.delete(target);
        }
    }

    // --- Listen globally for user input and change events ---
    document.addEventListener(
        "input",
        (e) => {
            dispatchSvelteUpdate(e.target);
        },
        true
    );

    document.addEventListener(
        "change",
        (e) => {
            dispatchSvelteUpdate(e.target);
        },
        true
    );

    // --- MutationObserver for attribute changes ---
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type !== "attributes") continue;

            const { attributeName, target } = mutation;

            // For inputs/selects/textareas, watch 'value' and 'checked'
            if (
                (attributeName === "value" || attributeName === "checked") &&
                target instanceof HTMLElement &&
                ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)
            ) {
                dispatchSvelteUpdate(target);
            }
            // For options, watch 'selected' attribute and dispatch on parent select
            else if (
                attributeName === "selected" &&
                target instanceof HTMLOptionElement
            ) {
                const select = target.parentElement;
                if (select && select.tagName === "SELECT") {
                    dispatchSvelteUpdate(select);
                }
            }
        }
    });

    /**
     * Observe attribute changes on elements relevant for Svelte updates.
     * @param {HTMLElement} el
     */
    function observeElement(el) {
        try {
            const attrs =
                el.tagName === "OPTION" ? ["selected"] : ["value", "checked"];
            observer.observe(el, { attributes: true, attributeFilter: attrs });
        } catch (_) {
            // Fail silently for detached or restricted elements
        }
    }

    // Observe all existing relevant elements initially
    document
        .querySelectorAll("input, textarea, select, option")
        .forEach(observeElement);

    // --- Watch for dynamically added elements ---
    const bodyObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== 1) continue; // element only

                if (
                    node.matches &&
                    node.matches("input, textarea, select, option")
                ) {
                    observeElement(node);
                }

                if (node.querySelectorAll) {
                    node.querySelectorAll(
                        "input, textarea, select, option"
                    ).forEach(observeElement);
                }
            }
        }
    });
    bodyObserver.observe(document.body, { childList: true, subtree: true });

    // --- Override property setters for direct programmatic assignments ---
    function overrideProperty(proto, propName) {
        const descriptor = Object.getOwnPropertyDescriptor(proto, propName);
        if (!descriptor || !descriptor.set) return;

        Object.defineProperty(proto, propName, {
            get: descriptor.get,
            set(value) {
                const oldValue = this[propName];
                if (value === oldValue) return;
                descriptor.set.call(this, value);

                // For option.selected, dispatch on parent select instead
                if (
                    this instanceof HTMLOptionElement &&
                    propName === "selected"
                ) {
                    const select = this.parentElement;
                    if (select && select.tagName === "SELECT") {
                        dispatchSvelteUpdate(select);
                        return;
                    }
                }

                dispatchSvelteUpdate(this);
            },
            configurable: true,
            enumerable: descriptor.enumerable,
        });
    }

    overrideProperty(HTMLInputElement.prototype, "value");
    overrideProperty(HTMLInputElement.prototype, "checked");
    overrideProperty(HTMLTextAreaElement.prototype, "value");
    overrideProperty(HTMLSelectElement.prototype, "value");
    overrideProperty(HTMLOptionElement.prototype, "selected");
})();
