<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AnonymousSiebelTable extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'anonymous_siebel_tables';

    protected $fillable = [
        'object_type',
        'table_name',
        'table_comment',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
        'schema_id',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    public function schema()
    {
        return $this->belongsTo(AnonymousSiebelSchema::class, 'schema_id');
    }

    public function columns()
    {
        return $this->hasMany(AnonymousSiebelColumn::class, 'table_id');
    }
}
