<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class HTMLFormElement extends Model
{
    use HasFactory;

    protected $table = 'h_t_m_l_form_elements';

    protected $fillable = [
        'name',
        'html_content',
        'repeater_item_label',
    ];

    /**
     * Get the form element that owns this HTML element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get sanitized HTML content for safe display.
     */
    public function getSanitizedHtmlAttribute(): string
    {
        // Basic HTML sanitization - you might want to use a more robust solution
        return strip_tags($this->html_content, '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span>');
    }
}
