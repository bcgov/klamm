<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelSchema extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'anonymous_siebel_schemas';
    protected $fillable = [
        'schema_name',
        'description',
        'type',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
        'database_id',
    ];
    protected $casts = [
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    public function database()
    {
        return $this->belongsTo(AnonymousSiebelDatabase::class, 'database_id');
    }

    public function tables()
    {
        return $this->hasMany(AnonymousSiebelTable::class, 'schema_id');
    }
}
