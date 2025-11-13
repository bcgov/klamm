<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelDatabase extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'anonymous_siebel_databases';

    protected $fillable = [
        'database_name',
        'description',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    public function schemas()
    {
        return $this->hasMany(AnonymousSiebelSchema::class, 'database_id');
    }
}
