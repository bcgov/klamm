<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextareaInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'rows',
        'cols',
        'maxlength',
        'minlength',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'rows' => 'integer',
        'cols' => 'integer',
        'maxlength' => 'integer',
        'minlength' => 'integer',
    ];

    protected $attributes = [
        'visible_label' => true,
        'rows' => 3,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.placeholder_text')
                ->label('Placeholder Text')
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Field Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.visible_label')
                ->label('Show Label')
                ->default(true)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.rows')
                ->label('Number of Rows')
                ->numeric()
                ->default(3)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.cols')
                ->label('Number of Columns')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.minlength')
                ->label('Minimum Length')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.maxlength')
                ->label('Maximum Length')
                ->numeric()
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this textarea input element.
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
            'placeholder_text' => $this->placeholder_text,
            'label' => $this->label,
            'visible_label' => $this->visible_label,
            'rows' => $this->rows,
            'cols' => $this->cols,
            'maxlength' => $this->maxlength,
            'minlength' => $this->minlength,
        ];
    }
}
