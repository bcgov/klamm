/**
 * Value Calculation Utilities
 * ------------------------------------
 * This file contains reusable JavaScript functions for setting the value
 * of form elements based on user input (radio, date, number, text, dropdown, checkbox).
 *
 * Usage:
 * - Copy the "called" function blocks (with templating) into your initialization script.
 * - The base functions only need to be imported once and can be reused for multiple fields.
 */

// Initialization script
// This function is intended for custom initialization logic.
// You can copy the "called" blocks below into this function.
function initializeCalculateScript() {}

// Run once DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeCalculateScript);
} else {
    initializeCalculateScript();
}

/**
 * Value calculation for radio button group
 * ---------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueRadio) needs to be imported once.
 */
calculateValueRadio({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    valueMap: {
        "radio-value-1": "Target Value 1", // example: set text input/info value
        "radio-value-2": "Target Value 2",
        "radio-value-3": true, // example: set checkbox to true
        // Add more mappings as needed",
    },
});

/**
 * Base function: calculateValueRadio
 * ----------------------------------
 * Sets the value of a target element based on the selected value of a radio button group.
 * @param {string} source_id - The ID of the radio group container.
 * @param {string} target_id - The ID of the target element to set.
 * @param {Object} valueMap - Map of radio values to target values.
 */
function calculateValueRadio({ source_id, target_id, valueMap }) {
    const radioGroup = document.getElementById(source_id);
    const target = document.getElementById(target_id);

    if (!radioGroup || !target) {
        console.error("Radio or target not found", source_id, target_id);
        return;
    }

    function setTargetValue() {
        const checkedRadio = radioGroup.querySelector(
            'input[type="radio"]:checked'
        );
        const value = checkedRadio ? checkedRadio.value : null;

        const mappedValue = valueMap.hasOwnProperty(value)
            ? valueMap[value]
            : "";

        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";

            if (type === "checkbox") {
                // If value is truthy, check the box
                target.checked = !!mappedValue;
            } else {
                // Set value for text input or other input types
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            // Set text for display element
            target.textContent = mappedValue;
        } else {
            // Default fallback
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    radioGroup.addEventListener("change", setTargetValue);
}

/**
 * Value calculation for date input
 * --------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueDate) needs to be imported once.
 */
calculateValueDate({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    dateTransform: (dateStr) => dateStr, // identity, or custom formatting
});

/**
 * Base function: calculateValueDate
 * ---------------------------------
 * Sets the value of a target element based on the value of a date input.
 * @param {string} source_id - The ID of the date input.
 * @param {string} target_id - The ID of the target element to set.
 * @param {function} dateTransform - Function to transform the date string.
 */
function calculateValueDate({ source_id, target_id, dateTransform }) {
    const dateInput = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!dateInput || !target) {
        console.error("Required elements not found");
        return;
    }

    function setTargetValue() {
        const value = dateInput.value.trim();
        const mappedValue = dateTransform ? dateTransform(value) : value;
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    dateInput.addEventListener("input", setTargetValue);
}

/**
 * Value calculation for number input
 * ----------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueNumber) needs to be imported once.
 */
calculateValueNumber({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    numberTransform: (num) => num * 2, // example: double the input
});

/**
 * Base function: calculateValueNumber
 * -----------------------------------
 * Sets the value of a target element based on the value of a number input.
 * @param {string} source_id - The ID of the number input.
 * @param {string} target_id - The ID of the target element to set.
 * @param {function} numberTransform - Function to transform the number value.
 */
function calculateValueNumber({ source_id, target_id, numberTransform }) {
    const numberInput = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!numberInput || !target) {
        console.error("Required elements not found");
        return;
    }

    function setTargetValue() {
        const value = parseFloat(numberInput.value);
        let mappedValue = "";
        if (!isNaN(value)) {
            mappedValue = numberTransform ? numberTransform(value) : value;
        }
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    numberInput.addEventListener("input", setTargetValue);
}

/**
 * Value calculation for text input
 * --------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueText) needs to be imported once.
 */
calculateValueText({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    textTransform: (val) => val.toUpperCase(),
});

/**
 * Base function: calculateValueText
 * ---------------------------------
 * Sets the value of a target element based on the value of a text input.
 * @param {string} source_id - The ID of the text input.
 * @param {string} target_id - The ID of the target element to set.
 * @param {function} textTransform - Function to transform the text value.
 */
function calculateValueText({ source_id, target_id, textTransform }) {
    const textInput = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!textInput || !target) {
        console.error("Required elements not found");
        return;
    }

    function setTargetValue() {
        const value = textInput.value.trim();
        const mappedValue = textTransform ? textTransform(value) : value;
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    textInput.addEventListener("input", setTargetValue);
}

/**
 * Value calculation for text input
 * --------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueText) needs to be imported once.
 */
calculateValueText({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    textTransform: (val) => val.toUpperCase(),
});

/**
 * Base function: calculateValueText
 * ---------------------------------
 * Sets the value of a target element based on the value of a text input.
 * @param {string} source_id - The ID of the text input.
 * @param {string} target_id - The ID of the target element to set.
 * @param {function} textTransform - Function to transform the text value.
 */
function calculateValueText({ source_id, target_id, textTransform }) {
    const textInput = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!textInput || !target) {
        console.error("Required elements not found");
        return;
    }

    function setTargetValue() {
        const value = textInput.value.trim();
        const mappedValue = textTransform ? textTransform(value) : value;
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    textInput.addEventListener("input", setTargetValue);
}

/**
 * Value calculation for dropdown/select input
 * ------------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueDropdown) needs to be imported once.
 */
calculateValueDropdown({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    optionMap: {
        "Selected Option 1": "Target Value 1",
        "Selected Option 2": "Target Value 2",
    },
});

/**
 * Base function: calculateValueDropdown
 * -------------------------------------
 * Sets the value of a target element based on the selected option in a dropdown.
 * @param {string} source_id - The ID of the dropdown container.
 * @param {string} target_id - The ID of the target element to set.
 * @param {Object} optionMap - Map of option labels to target values.
 */
function calculateValueDropdown({ source_id, target_id, optionMap }) {
    const dropDownSelector = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!dropDownSelector || !target) {
        console.error("Required elements not found");
        return;
    }

    function getSelectedDropdownText() {
        const labelEl = dropDownSelector.querySelector(".cds--list-box__label");
        return labelEl ? labelEl.textContent.trim() : "";
    }

    function setTargetValue() {
        const selectedText = getSelectedDropdownText();
        const mappedValue = optionMap.hasOwnProperty(selectedText)
            ? optionMap[selectedText]
            : "";
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    dropDownSelector.addEventListener("click", () => {
        setTimeout(setTargetValue, 100);
    });
}

/**
 * Value calculation for checkbox input
 * ------------------------------------
 * Copy this block into your initialization script to use.
 * Only the base function (calculateValueCheckbox) needs to be imported once.
 */
calculateValueCheckbox({
    source_id: "#{source_id}",
    target_id: "#{target_id}",
    checkedValue: "Yes", // example: set text input/info value when checked
    uncheckedValue: "No",
});

/**
 * Base function: calculateValueCheckbox
 * -------------------------------------
 * Sets the value of a target element based on the checked state of a checkbox.
 * @param {string} source_id - The ID of the checkbox input.
 * @param {string} target_id - The ID of the target element to set.
 * @param {string} checkedValue - Value to set when checked.
 * @param {string} uncheckedValue - Value to set when unchecked.
 */
function calculateValueCheckbox({
    source_id,
    target_id,
    checkedValue,
    uncheckedValue,
}) {
    const checkbox = document.getElementById(source_id);
    const target = document.getElementById(target_id);
    if (!checkbox || !target) {
        console.error("Required elements not found");
        return;
    }

    function setTargetValue() {
        const mappedValue = checkbox.checked ? checkedValue : uncheckedValue;
        if (target.tagName === "INPUT") {
            const type = target.getAttribute("type") || "text";
            if (type === "checkbox") {
                target.checked = !!mappedValue;
            } else {
                target.value = mappedValue;
            }
        } else if (target.tagName === "DIV" || target.tagName === "SPAN") {
            target.textContent = mappedValue;
        } else {
            console.warn(`Unsupported target type: ${target.tagName}`);
        }
    }

    setTargetValue();
    checkbox.addEventListener("change", setTargetValue);
}
