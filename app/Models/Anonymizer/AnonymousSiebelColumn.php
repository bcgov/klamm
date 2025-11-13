<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelColumn extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'anonymous_siebel_columns';

    protected $fillable = [
        'column_name',
        'column_id',
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
        'last_synced_at',
        'changed_at',
        'changed_fields',
        'table_id',
        'data_type_id',
    ];

    protected $casts = [
        'column_id' => 'integer',
        'data_length' => 'integer',
        'data_precision' => 'integer',
        'data_scale' => 'integer',
        'nullable' => 'boolean',
        'char_length' => 'integer',
        'related_columns' => 'array',
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    public function table()
    {
        return $this->belongsTo(AnonymousSiebelTable::class, 'table_id');
    }

    public function dataType()
    {
        return $this->belongsTo(AnonymousSiebelDataType::class, 'data_type_id');
    }

    public function childColumns()
    {
        return $this->belongsToMany(self::class, 'anonymous_siebel_column_dependencies', 'parent_field_id', 'child_field_id');
    }

    public function parentColumns()
    {
        return $this->belongsToMany(self::class, 'anonymous_siebel_column_dependencies', 'child_field_id', 'parent_field_id');
    }
}
