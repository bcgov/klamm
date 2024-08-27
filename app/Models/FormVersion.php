<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'version_number',
        'status',
        'form_requester_name',
        'form_requester_email',
        'form_developer_name',
        'form_developer_email',
        'form_approver_name',
        'form_approver_email',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function formInstanceFields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class);
    }
}
