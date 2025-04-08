<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ReportEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'business_area_id',
        'report_dictionary_label_id',
        'existing_label',
        'label_source_id',
        'data_field',
        'icm_data_field_path',
        'data_matching_rate',
        'follow_up_required',
        'note'
    ];

    protected $casts = [
        'data_matching_rate' => 'string',
        'follow_up_required' => 'string',
    ];

    public function reportBusinessArea(): BelongsTo
    {
        return $this->belongsTo(ReportBusinessArea::class, 'business_area_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function labelSource(): BelongsTo
    {
        return $this->belongsTo(ReportLabelSource::class, 'label_source_id');
    }

    public function reportDictionaryLabel(): BelongsTo
    {
        return $this->belongsTo(ReportDictionaryLabel::class);
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    protected static function booted()
    {
        static::saving(function ($report) {
            $report->last_updated_by = Auth::id();;

            if (is_null($report->data_matching_rate)) {
                $report->data_matching_rate = 'n/a';
            }
            if (is_null($report->follow_up_required)) {
                $report->follow_up_required = 'tbd';
            }
        });

        static::creating(function ($report) {
            if (is_null($report->data_matching_rate)) {
                $report->data_matching_rate = 'n/a';
            }
            if (is_null($report->follow_up_required)) {
                $report->follow_up_required = 'tbd';
            }
        });
    }
}
