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
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.name')
                ->label('Element Name')
                ->disabled($disabled),
            \Filament\Forms\Components\Textarea::make('elementable_data.html_content')
                ->label('HTML Content')
                ->rows(8)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.repeater_item_label')
                ->label('Repeater Item Label')
                ->helperText('Used when this element is part of a repeater')
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this HTML element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Return this element's data as an array
     */
    public function getData(): array
    {
        return [
            'name' => $this->name,
            'html_content' => $this->html_content,
            'repeater_item_label' => $this->repeater_item_label,
        ];
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
