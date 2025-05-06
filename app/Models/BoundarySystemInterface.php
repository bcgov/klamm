<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Helpers\StringHelper;

class BoundarySystemInterface extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_description',
        'description',
        'source_system_id',
        'target_system_id',
        'transaction_frequency',
        'transaction_schedule',
        'complexity',
        'integration_type',
        'mode_of_transfer',
        'protocol',
        'data_format',
        'security',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'data_format' => 'array',
        'security' => 'array',
    ];

    public function sourceSystem(): BelongsTo
    {
        return $this->belongsTo(BoundarySystem::class, 'source_system_id');
    }

    public function targetSystem(): BelongsTo
    {
        return $this->belongsTo(BoundarySystem::class, 'target_system_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            BoundarySystemTag::class,
            'boundary_system_interface_tag',
            'boundary_system_interface_id',
            'boundary_system_tag_id'
        );
    }

    public static function getTransactionFrequencyOptions(): array
    {
        return [
            'on_demand' => 'On Demand',
            'hourly' => 'Hourly',
            'multiple_times_a_day' => 'Multiple Times a Day',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
            'custom' => 'Custom',
        ];
    }

    public static function getComplexityOptions(): array
    {
        return [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ];
    }

    public static function getIntegrationTypeOptions(): array
    {
        return [
            'api_soap' => 'API (SOAP-based)',
            'api_rest' => 'API (RESTful)',
            'file_transfer' => 'File Transfer',
            'database_integration' => 'Database Integration',
            'messaging_queue' => 'Messaging Queue',
            'event_driven' => 'Event-Driven Integration',
            'etl' => 'ETL',
            'unknown' => 'Unknown',
        ];
    }

    public static function getModeOfTransferOptions(): array
    {
        return [
            'batch' => 'Batch',
            'asynchronous' => 'Asynchronous',
            'synchronous' => 'Synchronous',
            'real_time' => 'Real-time',
            'near_real_time' => 'Near Real-time',
            'unknown' => 'Unknown',
        ];
    }

    public static function getProtocolOptions(): array
    {
        return [
            'soap' => 'SOAP',
            'http' => 'HTTP',
            'ftp' => 'FTP',
            'sftp' => 'SFTP',
            'jdbc' => 'JDBC',
            'odbc' => 'ODBC',
            'mq_series' => 'MQ Series',
            'jms' => 'JMS',
            'webservice' => 'Webservice',
            'rest' => 'REST',
            'ssh' => 'SSH',
            'unknown' => 'Unknown',
        ];
    }

    public static function getDataFormatOptions(): array
    {
        return [
            'xml' => 'XML',
            'json' => 'JSON',
            'yaml' => 'YAML',
            'txt' => 'TXT',
            'csv' => 'CSV',
            'tsv' => 'TSV',
            'psv' => 'PSV',
            'doc' => 'DOC',
            'docx' => 'DOCX',
            'pdf' => 'PDF',
            'fixed_width' => 'Fixed-width',
            'edi' => 'EDI',
            'compressed' => 'Compressed (TAR, ZIP, 7z, RAR)',
            'binary' => 'Binary',
            'msg' => 'MSG',
            'lin' => 'LIN',
            'dos_module' => 'DOS MODULE',
            'dat' => 'DAT',
            'sent' => 'SENT',
            'sql' => 'SQL',
            'unknown' => 'Unknown',
        ];
    }

    public static function getSecurityOptions(): array
    {
        return [
            'appgate_sdn' => 'AppGate SDN',
            'oauth' => 'OAuth',
            'basic_auth' => 'Basic Authentication',
            'api_keys' => 'API Keys',
            'mtls' => 'Mutual TLS (mTLS)',
            'saml' => 'SAML (Security Assertion Markup Language)',
            'rbac' => 'RBAC (Role-Based Access Control)',
        ];
    }
}
