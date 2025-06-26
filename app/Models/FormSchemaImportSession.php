<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FormSchemaImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_name',
        'description',
        'status',
        'schema_content',
        'parsed_schema_summary',
        'parsed_schema_data',
        'target_form_id',
        'target_form_title',
        'target_ministry_id',
        'target_form_record_id',
        'create_new_form',
        'create_new_version',
        'field_mappings',
        'import_progress',
        'total_fields',
        'mapped_fields',
        'current_step',
        'result_form_id',
        'result_form_version_id',
        'import_result',
        'completed_at',
        'error_message',
        'user_id',
        'session_token',
        'last_activity_at',
        'browser_session_data',
    ];

    protected $casts = [
        'parsed_schema_summary' => 'array',
        'field_mappings' => 'array',
        'import_progress' => 'array',
        'import_result' => 'array',
        'browser_session_data' => 'array',
        'create_new_form' => 'boolean',
        'create_new_version' => 'boolean',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected $dates = [
        'completed_at',
        'last_activity_at',
    ];

    /**
     * Boot the model to automatically generate session token and set user
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->session_token) {
                $session->session_token = Str::random(40);
            }
            if (!$session->user_id && Auth::check()) {
                $session->user_id = Auth::id();
            }
            $session->last_activity_at = now();
        });

        static::updating(function ($session) {
            $session->last_activity_at = now();
        });
    }

    // Relationships

    /**
     * Get the user that owns the import session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the target ministry for the import
     */
    public function targetMinistry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class, 'target_ministry_id');
    }

    /**
     * Get the target form record for the import
     */
    public function targetForm(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'target_form_record_id');
    }

    /**
     * Get the resulting form after successful import
     */
    public function resultForm(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'result_form_id');
    }

    /**
     * Get the resulting form version after successful import
     */
    public function resultFormVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'result_form_version_id');
    }

    // Scopes

    /**
     * Scope to get sessions for the current user
     */
    public function scopeForCurrentUser($query)
    {
        return $query->where('user_id', Auth::id());
    }

    /**
     * Scope to get sessions by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get active sessions (not completed, failed, or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'in_progress']);
    }

    /**
     * Scope to get recent sessions (within last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('last_activity_at', '>=', now()->subDays(30));
    }

    // Helper Methods

    /**
     * Generate a unique session token
     */
    public static function generateSessionToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('session_token', $token)->exists());

        return $token;
    }

    /**
     * Create a new import session from current component state
     */
    public static function createFromImportState(array $data, ?array $parsedSchema = null, array $fieldMappings = []): self
    {
        // Generate a default session name based on schema content
        $sessionName = 'Schema Import';
        if (isset($data['target_form_id'])) {
            $sessionName = "Import: {$data['target_form_id']}";
        } elseif ($parsedSchema && isset($parsedSchema['form_id'])) {
            $sessionName = "Import: {$parsedSchema['form_id']}";
        }
        $sessionName .= ' - ' . now()->format('M j, Y g:i A');

        return static::create([
            'session_name' => $sessionName,
            'status' => 'draft',
            'schema_content' => $data['schema_content'] ?? null,
            'parsed_schema_summary' => $data['parsed_content'] ?? null,
            'parsed_schema_data' => $parsedSchema ? json_encode($parsedSchema) : null,
            'target_form_id' => $data['form_id'] ?? null,
            'target_form_title' => $data['form_title'] ?? null,
            'target_ministry_id' => $data['ministry_id'] ?? null,
            'target_form_record_id' => $data['form'] ?? null,
            'create_new_form' => $data['create_new_form'] ?? false,
            'create_new_version' => $data['create_new_version'] ?? true,
            'field_mappings' => $fieldMappings,
            'total_fields' => count($fieldMappings),
            'current_step' => 1,
            'browser_session_data' => [
                'current_page' => $data['current_page'] ?? 1,
                'per_page' => $data['pagination_per_page'] ?? 10,
                'schema_version' => $data['schema_version'] ?? 1,
            ]
        ]);
    }

    /**
     * Update the session with current import state
     */
    public function updateFromImportState(array $data, ?array $parsedSchema = null, array $fieldMappings = [], ?int $currentStep = null): self
    {
        $updateData = [
            'target_form_id' => $data['form_id'] ?? $this->target_form_id,
            'target_form_title' => $data['form_title'] ?? $this->target_form_title,
            'target_ministry_id' => $data['ministry_id'] ?? $this->target_ministry_id,
            'target_form_record_id' => $data['form'] ?? $this->target_form_record_id,
            'create_new_form' => $data['create_new_form'] ?? $this->create_new_form,
            'create_new_version' => $data['create_new_version'] ?? $this->create_new_version,
        ];

        if (!empty($fieldMappings)) {
            $updateData['field_mappings'] = $fieldMappings;
            $updateData['total_fields'] = count($fieldMappings);
            $updateData['mapped_fields'] = count(array_filter($fieldMappings, fn($mapping) => $mapping !== 'skip' && $mapping !== 'new'));
        }

        if ($currentStep !== null) {
            $updateData['current_step'] = $currentStep;
        }

        if ($parsedSchema) {
            $updateData['parsed_schema_data'] = json_encode($parsedSchema);
        }

        // Update browser session data
        $browserData = $this->browser_session_data ?? [];
        $browserData['current_page'] = $data['current_page'] ?? $browserData['current_page'] ?? 1;
        $browserData['per_page'] = $data['pagination_per_page'] ?? $browserData['per_page'] ?? 10;
        $browserData['schema_version'] = $data['schema_version'] ?? $browserData['schema_version'] ?? 1;
        $updateData['browser_session_data'] = $browserData;

        $this->update($updateData);
        return $this;
    }

    /**
     * Mark the session as completed with results
     */
    public function markCompleted(array $result): self
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'import_result' => $result,
            'result_form_id' => $result['form']->id ?? null,
            'result_form_version_id' => $result['formVersion']->id ?? null,
        ]);

        return $this;
    }

    /**
     * Mark the session as failed with error message
     */
    public function markFailed(string $errorMessage): self
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    /**
     * Mark the session as in progress
     */
    public function markInProgress(): self
    {
        $this->update(['status' => 'in_progress']);
        return $this;
    }

    /**
     * Cancel the session
     */
    public function cancel(): self
    {
        $this->update(['status' => 'cancelled']);
        return $this;
    }

    /**
     * Restore session data to import component state format
     */
    public function toImportState(): array
    {
        $data = [
            'schema_content' => $this->schema_content,
            'parsed_content' => $this->parsed_schema_summary,
            'form_id' => $this->target_form_id,
            'form_title' => $this->target_form_title,
            'ministry_id' => $this->target_ministry_id,
            'form' => $this->target_form_record_id,
            'create_new_form' => $this->create_new_form,
            'create_new_version' => $this->create_new_version,
        ];

        // Add field mappings
        if ($this->field_mappings) {
            foreach ($this->field_mappings as $fieldId => $mapping) {
                $data["field_mapping_{$fieldId}"] = $mapping;
            }
        }

        // Add browser session data
        if ($this->browser_session_data) {
            $data = array_merge($data, $this->browser_session_data);
        }

        return $data;
    }

    /**
     * Get the parsed schema data as array
     */
    public function getParsedSchemaAttribute(): ?array
    {
        if (!$this->parsed_schema_data) {
            return null;
        }

        return json_decode($this->parsed_schema_data, true);
    }

    /**
     * Check if session has been recently active
     */
    public function isRecentlyActive(int $hours = 24): bool
    {
        return $this->last_activity_at && $this->last_activity_at->gt(now()->subHours($hours));
    }

    /**
     * Get completion percentage based on mapped fields
     */
    public function getCompletionPercentageAttribute(): int
    {
        if ($this->total_fields === 0) {
            return 0;
        }

        return (int) round(($this->mapped_fields / $this->total_fields) * 100);
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'in_progress' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'cancelled' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get the time since last activity in human readable format
     */
    public function getLastActivityHumanAttribute(): string
    {
        if (!$this->last_activity_at) {
            return 'Never';
        }

        return $this->last_activity_at->diffForHumans();
    }

    /**
     * Check if the session can be resumed
     */
    public function canBeResumed(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']) && $this->schema_content;
    }

    /**
     * Check if the session can be deleted
     */
    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['draft', 'failed', 'cancelled']) ||
            ($this->status === 'completed' && $this->completed_at->lt(now()->subDays(30)));
    }
}
