<?php

namespace App\Filament\Forms\Helpers;

use App\Models\FormSchemaImportSession;
use App\Models\Form as FormModel;
use App\Http\Middleware\CheckRole;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Import Session Manager
 *
 * Handles all session-related operations for the schema import process,
 * including session creation, restoration, and state management.
 */
class ImportSessionManager
{
    /**
     * Load an existing import session by token
     *
     * @param string $sessionToken The session token to load
     * @return FormSchemaImportSession|null The loaded session or null if not found
     */
    public function loadSession(string $sessionToken): ?FormSchemaImportSession
    {
        $query = FormSchemaImportSession::where('session_token', $sessionToken);

        // Allow admins to access any session, others only their own
        if (!CheckRole::hasRole(request(), 'admin')) {
            $query->forCurrentUser();
        }

        return $query->first();
    }

    /**
     * Restore session data to component state format
     *
     * @param FormSchemaImportSession $session The session to restore
     * @return array The restored component state
     */
    public function restoreSessionData(FormSchemaImportSession $session): array
    {
        $sessionData = $session->toImportState();

        // Convert field mappings to form data format
        $fieldMappings = $session->field_mappings ?? [];
        foreach ($fieldMappings as $fieldId => $mappingValue) {
            $sessionData["field_mapping_{$fieldId}"] = $mappingValue;
        }

        return $sessionData;
    }

    /**
     * Handle form_id parameter from CreateFormVersion
     *
     * @param string|null $formId The form ID from query string
     * @return array|null Form data array or null if form not found
     */
    public function handleFormIdParameter(?string $formId): ?array
    {
        if (!$formId) {
            return null;
        }

        $form = FormModel::find($formId);
        if (!$form) {
            return null;
        }

        return [
            'form_id' => $form->form_id,
            'form_title' => $form->form_title,
            'ministry_id' => $form->ministry_id,
            'form' => $form->id,
            'create_new_form' => false,
            'create_new_version' => true,
        ];
    }

    /**
     * Create or update an import session
     *
     * @param array $data Current component data
     * @param array|null $parsedSchema Parsed schema data
     * @param array $fieldMappings Current field mappings
     * @param FormSchemaImportSession|null $existingSession Existing session to update
     * @return FormSchemaImportSession The created or updated session
     */
    public function saveSession(
        array $data,
        ?array $parsedSchema = null,
        array $fieldMappings = [],
        ?FormSchemaImportSession $existingSession = null
    ): FormSchemaImportSession {
        if ($existingSession) {
            return $this->updateSession($existingSession, $data, $parsedSchema, $fieldMappings);
        }

        return FormSchemaImportSession::createFromImportState($data, $parsedSchema, $fieldMappings);
    }

    /**
     * Update an existing session with current state
     *
     * @param FormSchemaImportSession $session The session to update
     * @param array $data Current component data
     * @param array|null $parsedSchema Parsed schema data
     * @param array $fieldMappings Current field mappings
     * @return FormSchemaImportSession The updated session
     */
    public function updateSession(
        FormSchemaImportSession $session,
        array $data,
        ?array $parsedSchema = null,
        array $fieldMappings = []
    ): FormSchemaImportSession {
        return $session->updateFromImportState($data, $parsedSchema, $fieldMappings);
    }

    /**
     * Auto-save session progress
     *
     * @param FormSchemaImportSession|null $session The session to save to
     * @param array $data Current component data
     * @param array|null $parsedSchema Parsed schema data
     * @return void
     */
    public function autoSaveProgress(
        ?FormSchemaImportSession $session,
        array $data,
        ?array $parsedSchema = null
    ): void {
        if (!$session) {
            return; // No session to save to
        }

        try {
            // Extract current field mappings from form data
            $currentFieldMappings = $this->extractFieldMappingsFromData($data);

            // Update session with current state
            $session->updateFromImportState($data, $parsedSchema, $currentFieldMappings);

            Log::debug('Auto-saved session progress', [
                'session_id' => $session->id,
                'mappings_count' => count($currentFieldMappings)
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to auto-save session progress: ' . $e->getMessage());
            // Don't throw error for auto-save failures to avoid disrupting user workflow
        }
    }

    /**
     * Extract field mappings from component data
     *
     * @param array $data Component data array
     * @return array Extracted field mappings
     */
    public function extractFieldMappingsFromData(array $data): array
    {
        $fieldMappings = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'field_mapping_')) {
                $fieldId = str_replace('field_mapping_', '', $key);
                $fieldMappings[$fieldId] = $value;
            }
        }
        return $fieldMappings;
    }

    /**
     * Generate a default session name based on current data
     *
     * @param array $data Component data
     * @param array|null $parsedSchema Parsed schema data
     * @return string Generated session name
     */
    public function generateDefaultSessionName(array $data, ?array $parsedSchema = null): string
    {
        $name = 'Schema Import';

        if (!empty($data['form_id'])) {
            $name = "Import: {$data['form_id']}";
        } elseif ($parsedSchema && isset($parsedSchema['form_id'])) {
            $name = "Import: {$parsedSchema['form_id']}";
        }

        $name .= ' - ' . now()->format('M j, Y g:i A');

        return $name;
    }

    /**
     * Cancel the current session
     *
     * @param FormSchemaImportSession|null $session The session to cancel
     * @return void
     */
    public function cancelSession(?FormSchemaImportSession $session): void
    {
        if ($session) {
            $session->cancel();
        }
    }

    /**
     * Mark session as completed with results
     *
     * @param FormSchemaImportSession|null $session The session to mark complete
     * @param array $result Import results
     * @return void
     */
    public function markSessionCompleted(?FormSchemaImportSession $session, array $result): void
    {
        if ($session) {
            $session->markCompleted($result);
        }
    }

    /**
     * Mark session as failed with error message
     *
     * @param FormSchemaImportSession|null $session The session to mark failed
     * @param string $errorMessage Error message
     * @return void
     */
    public function markSessionFailed(?FormSchemaImportSession $session, string $errorMessage): void
    {
        if ($session) {
            $session->markFailed($errorMessage);
        }
    }

    /**
     * Send session restoration notification
     *
     * @param FormSchemaImportSession $session The restored session
     * @return void
     */
    public function sendRestorationNotification(FormSchemaImportSession $session): void
    {
        Notification::make()
            ->title('Session Resumed')
            ->body("Resumed import session: {$session->session_name}")
            ->success()
            ->send();
    }

    /**
     * Send session not found notification
     *
     * @return void
     */
    public function sendSessionNotFoundNotification(): void
    {
        Notification::make()
            ->title('Session Not Found')
            ->body('The requested import session could not be found or you do not have access to it.')
            ->warning()
            ->send();
    }
}
