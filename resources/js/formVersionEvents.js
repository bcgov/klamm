// Form Version Update Events Listener
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

// Initialize Laravel Echo
window.Echo = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

/**
 * Subscribe to form version updates for a specific form version
 *
 * @param {number} formVersionId The form version ID to listen for updates
 * @param {function} callback Function to call when an update is received
 * @returns {object} The subscription object that can be used to unsubscribe
 */
export function subscribeToFormVersionUpdates(formVersionId, callback) {
    if (!formVersionId) {
        console.error("FormVersionID is required to subscribe to updates");
        return null;
    }

    const channel = window.Echo.private(`form-version.${formVersionId}`);

    channel.listen(".App\\Events\\FormVersionUpdateEvent", (event) => {
        console.log("Form version update received:", event);

        if (typeof callback === "function") {
            callback(event);
        }
    });

    return {
        unsubscribe: () => {
            window.Echo.leave(`form-version.${formVersionId}`);
        },
    };
}

/**
 * Subscribe to all updates for a specific form (all versions)
 *
 * @param {number} formId The form ID to listen for updates
 * @param {function} callback Function to call when an update is received
 * @returns {object} The subscription object that can be used to unsubscribe
 */
export function subscribeToFormUpdates(formId, callback) {
    if (!formId) {
        console.error("FormID is required to subscribe to updates");
        return null;
    }

    const channel = window.Echo.private(`form.${formId}`);

    channel.listen(".App\\Events\\FormVersionUpdateEvent", (event) => {
        console.log("Form update received:", event);

        if (typeof callback === "function") {
            callback(event);
        }
    });

    return {
        unsubscribe: () => {
            window.Echo.leave(`form.${formId}`);
        },
    };
}
