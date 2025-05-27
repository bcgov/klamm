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
            'daily' => 'Daily',
            'monthly' => 'Monthly',
            'annually' => 'Annually',
            'hourly' => 'Hourly',
            'on_demand' => 'On Demand',
            'weekly' => 'Weekly',
            'multiple_times_a_day' => 'Multiple times a day',
            'quarterly' => 'Quarterly',
            'custom' => 'Custom',
            'other' => 'Other',
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
            'file_transfer' => 'File Transfer',
            'appgate' => 'AppGate',
            'real_time' => 'Real Time',
            'vbc_real_time_sync' => 'VBC (Real-time sync)',
            'email' => 'Email',
            'lin' => '.LIN',
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
            'txt' => '.txt',
            'xml' => 'XML',
            'dat' => '.dat',
            'sent' => '.SENT',
            'dos_module' => 'DOS MODULE',
            'xls' => '.xls',
            'csv' => '.csv',
            'msg' => '.msg',
            'json' => 'JSON',
            'pdf' => '.PDF',
            'psv' => 'PSV',
            'call' => 'call',
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
