<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AnonymousSiebelStaging extends Model
{
    use HasFactory;
    protected $table = 'anonymous_siebel_stagings';
    protected $fillable = [
        'upload_id',
        'database_name',
        'schema_name',
        'object_type',
        'table_name',
        'column_name',
        'column_id',
        'data_type',
        'data_length',
        'data_precision',
        'data_scale',
        'nullable',
        'char_length',
        'column_comment',
        'table_comment',
        'related_columns_raw',
        'related_columns',
        'content_hash',
    ];
    protected $casts = [
        'upload_id' => 'integer',
        'column_id' => 'integer',
        'data_length' => 'integer',
        'data_precision' => 'integer',
        'data_scale' => 'integer',
        'char_length' => 'integer',
        'related_columns' => 'array',
    ];

    public function upload()
    {
        return $this->belongsTo(AnonymousUpload::class, 'upload_id');
    }
}
