/**
 * Conditional Display Utilities
 * --------------------------------------
 * This file contains reusable JavaScript functions for conditionally showing/hiding
 * form elements based on user input (radio, date, number, text, dropdown, checkbox).
 *
 * Usage:
 * - Copy the "called" function blocks (with templating) into your initialization script.
 * - The base functions only need to be imported once and can be reused for multiple fields.
 */

// Initialization script
// This function is intended for custom initialization logic.
// You can copy the "called" blocks below into this function.
function initializeScript() {}

// Run once DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeScript);
} else {
    initializeScript();
}

/**
 * Conditional display for radio button group
 * ------------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayRadio) needs to be imported once.
 */
conditionalDisplayRadio({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    visibleValues: ["radio-value-1", "radio-value-2"], // specify the values that should show the target
});

/**
 * Base function: conditionalDisplayRadio
 * --------------------------------------
 * Shows/hides a target element based on the selected value of a radio button group.
 * @param {string} source_id - The ID of the radio group container.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {Array<string>} visibleValues - Array of radio values that should show the target.
 */
function conditionalDisplayRadio({ source_id, target_id, visibleValues }) {
    const radioGroup = document.getElementById(source_id);
    const target = document.getElementById(target_id);

    if (!radioGroup || !target) {
        console.error("Radio or target not found", source_id, target_id);
        return;
    }

    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) {
            wrapper = target.parentNode;
        }
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);

    function toggleTargetVisibility() {
        const checkedRadio = radioGroup.querySelector(
            'input[type="radio"]:checked'
        );
        const value = checkedRadio ? checkedRadio.value : null;

        if (visibleValues.includes(value)) {
            targetWrapper.style.display = "";
        } else {
            targetWrapper.style.display = "none";
        }
    }

    toggleTargetVisibility();

    radioGroup.addEventListener("change", () => {
        toggleTargetVisibility();
    });
}

/**
 * Conditional display for date input
 * ----------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayDate) needs to be imported once.
 */
conditionalDisplayDate({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    minDate: new Date(), // enter a valid date here
    maxDate: new Date(), // specify a valid date here
    //optionally specific specific dates here for strict matching
    // allowedDates: ['2025-07-17', '2025-07-19']
});

/**
 * Base function: conditionalDisplayDate
 * -------------------------------------
 * Shows/hides a target element based on the value of a date input.
 * Supports min/max dates and strict allowed dates.
 * @param {string} source_id - The ID of the date input.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {Date} [minDate] - Minimum allowed date.
 * @param {Date} [maxDate] - Maximum allowed date.
 * @param {Array<string>} [allowedDates] - Array of allowed date strings.
 */
function conditionalDisplayDate({
    source_id,
    target_id,
    minDate,
    maxDate,
    allowedDates = [],
}) {
    const dateInput = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!dateInput || !target) {
        console.error("Required elements not found");
        return;
    }

    // Wrapper helper
    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) wrapper = target.parentNode;
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);

    function parseDate(val) {
        if (!val) return null;
        const mmmMatch = val.match(/^(\d{4})-([A-Za-z]{3})-(\d{2})$/);
        if (mmmMatch) {
            const [, year, mmm, day] = mmmMatch;
            const month = {
                Jan: 0,
                Feb: 1,
                Mar: 2,
                Apr: 3,
                May: 4,
                Jun: 5,
                Jul: 6,
                Aug: 7,
                Sep: 8,
                Oct: 9,
                Nov: 10,
                Dec: 11,
            }[mmm];
            if (month === undefined) return null;
            return new Date(year, month, day);
        }

        // Try standard parsing
        const cleaned = val.replace(/[\.\-]/g, "/");
        const parsed = new Date(cleaned);
        return isNaN(parsed) ? null : parsed;
    }

    function isValid(dateStr) {
        const parsed = parseDate(dateStr);
        if (!parsed) return false;

        if (allowedDates.length > 0 && !allowedDates.includes(dateStr))
            return false;
        if (minDate && parsed < minDate) return false;
        if (maxDate && parsed > maxDate) return false;

        return true;
    }

    function toggleTargetVisibility() {
        const value = dateInput.value.trim();
        if (isValid(value)) {
            targetWrapper.style.display = "";
        } else {
            targetWrapper.style.display = "none";
        }
    }

    toggleTargetVisibility();
    dateInput.addEventListener("input", toggleTargetVisibility);
}

/**
 * Conditional display for number input
 * ------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayNumber) needs to be imported once.
 */
conditionalDisplayNumber({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    min: 1,
    max: 100,
    //optionally specific specific numbers here for strict matching
    // numbers: [6, 8]
});

/**
 * Base function: conditionalDisplayNumber
 * ---------------------------------------
 * Shows/hides a target element based on the value of a number input.
 * Supports optional min/max and strict allowed numbers.
 * @param {string} source_id - The ID of the number input.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {number} [min] - Minimum allowed value.
 * @param {number} [max] - Maximum allowed value.
 * @param {Array<number>} [numbers] - Array of allowed numbers.
 */
function conditionalDisplayNumber({ source_id, target_id, min, max, numbers }) {
    const numberInput = document.getElementById(`${source_id}`);
    const target = document.getElementById(`${target_id}`);
    if (!numberInput || !target) {
        console.error("Required elements not found");
        return;
    }

    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) {
            wrapper = target.parentNode;
        }
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);

    function toggleTargetVisibility() {
        const value = parseFloat(numberInput.value);
        let show = false;
        if (!isNaN(value)) {
            if (Array.isArray(numbers) && numbers.length > 0) {
                show = numbers.includes(value);
            } else {
                if (typeof min === "number" && typeof max === "number") {
                    show = value >= min && value <= max;
                } else if (typeof min === "number") {
                    show = value >= min;
                } else if (typeof max === "number") {
                    show = value <= max;
                } else {
                    show = true; // No min/max/numbers, always show if number
                }
            }
        }
        targetWrapper.style.display = show ? "" : "none";
    }
    // Initial state
    toggleTargetVisibility();
    numberInput.addEventListener("input", toggleTargetVisibility);
}

/**
 * Conditional display for text input
 * ----------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayText) needs to be imported once.
 */
conditionalDisplayText({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    matchText: (val) => val.toLowerCase() === "show",
});

/**
 * Base function: conditionalDisplayText
 * -------------------------------------
 * Shows/hides a target element based on the value of a text input.
 * Uses a custom matchText function for matching logic.
 * @param {string} source_id - The ID of the text input.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {function} matchText - Function to determine if the target should be shown.
 */
function conditionalDisplayText({ source_id, target_id, matchText }) {
    const textInput = document.getElementById(`${source_id}`);
    const target = document.getElementById(`${target_id}`);
    if (!textInput || !target) {
        console.error("Required elements not found");
        return;
    }
    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) {
            wrapper = target.parentNode;
        }
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);
    function toggleTargetVisibility() {
        const value = textInput.value.trim();
        if (matchText(value)) {
            targetWrapper.style.display = "";
        } else {
            targetWrapper.style.display = "none";
        }
    }
    // Initial state
    toggleTargetVisibility();
    textInput.addEventListener("input", toggleTargetVisibility);
}

/**
 * Conditional display for dropdown/select input
 * ---------------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayDropdown) needs to be imported once.
 */
conditionalDisplayDropdown({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    visibleOptions: ["Selected Option Text 1", "Selected Option Text 2"], // specify the option labels that should show the target
});

/**
 * Base function: conditionalDisplayDropdown
 * -----------------------------------------
 * Shows/hides a target element based on the selected option in a dropdown.
 * @param {string} source_id - The ID of the dropdown container.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {Array<string>} visibleOptions - Array of option labels that should show the target.
 */
function conditionalDisplayDropdown({ source_id, target_id, visibleOptions }) {
    const dropDownSelector = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!dropDownSelector || !target) {
        console.error("Required elements not found");
        return;
    }

    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) {
            wrapper = target.parentNode;
        }
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);
    function getSelectedDropdownText() {
        const labelEl = dropDownSelector.querySelector(".cds--list-box__label");
        return labelEl ? labelEl.textContent.trim() : "";
    }
    function toggleTargetVisibility() {
        const selectedText = getSelectedDropdownText();
        if (visibleOptions.includes(selectedText)) {
            targetWrapper.style.display = "";
        } else {
            targetWrapper.style.display = "none";
        }
    }
    toggleTargetVisibility();
    dropDownSelector.addEventListener("click", () => {
        setTimeout(toggleTargetVisibility, 100);
    });
}

/**
 * Conditional display for checkbox input
 * --------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (conditionalDisplayCheckbox) needs to be imported once.
 */
conditionalDisplayCheckbox({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    shouldShow: (checked) => checked === true,
});

/**
 * Base function: conditionalDisplayCheckbox
 * -----------------------------------------
 * Shows/hides a target element based on the checked state of a checkbox.
 * Uses a custom shouldShow function for matching logic.
 * @param {string} source_id - The ID of the checkbox input.
 * @param {string} target_id - The ID of the target element to show/hide.
 * @param {function} shouldShow - Function to determine if the target should be shown.
 */
function conditionalDisplayCheckbox({ source_id, target_id, shouldShow }) {
    const checkbox = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!checkbox || !target) {
        console.error("Required elements not found");
        return;
    }

    function getTargetWrapper(target) {
        if (!target) return null;
        let wrapper = target.closest(".cds--form-item, .field-container");
        if (!wrapper && target.parentNode) {
            wrapper = target.parentNode;
        }
        return wrapper || target;
    }

    const targetWrapper = getTargetWrapper(target);
    function toggleTargetVisibility() {
        if (shouldShow(checkbox.checked)) {
            targetWrapper.style.display = "";
        } else {
            targetWrapper.style.display = "none";
        }
    }
    // Initial state
    toggleTargetVisibility();
    checkbox.addEventListener("change", toggleTargetVisibility);
}
