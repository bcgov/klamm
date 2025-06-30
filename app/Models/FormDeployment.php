<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FormDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'environment',
        'deployed_at',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public static function getEnvironments(): array
    {
        return [
            'test' => 'Test',
            'dev' => 'Development',
            'prod' => 'Production',
        ];
    }

    public static function getEnvironmentColor(string $environment): string
    {
        return match ($environment) {
            'test' => 'warning',
            'dev' => 'info',
            'prod' => 'success',
            default => 'gray',
        };
    }

    public function getFormattedEnvironmentName(): string
    {
        return self::getEnvironments()[$this->environment] ?? ucfirst($this->environment);
    }

    public static function deployToEnvironment(int $formVersionId, string $environment, Carbon $deployedAt): self
    {
        // Get the form_id from the form version
        $formVersion = FormVersion::find($formVersionId);
        if (!$formVersion) {
            throw new \Exception("Form version not found");
        }

        // Remove any existing deployment for this form in this environment
        self::where('environment', $environment)
            ->whereHas('formVersion', function ($query) use ($formVersion) {
                $query->where('form_id', $formVersion->form_id);
            })
            ->delete();

        // Create new deployment
        $deployment = self::create([
            'form_version_id' => $formVersionId,
            'environment' => $environment,
            'deployed_at' => $deployedAt,
        ]);

        // Update form version status to published once deployed to any environment
        if ($formVersion->status === 'approved') {
            $formVersion->update(['status' => 'published']);
        }

        return $deployment;
    }

    public static function getDeploymentForFormAndEnvironment(int $formId, string $environment): ?self
    {
        return self::where('environment', $environment)
            ->whereHas('formVersion', function ($query) use ($formId) {
                $query->where('form_id', $formId);
            })
            ->first();
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }
}
