<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Fieldset;

class SelectInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'enableVarSub',
        'defaultSelected',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'labelText' => '',
        'defaultSelected' => null,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            Fieldset::make('Field Label')
                ->schema([
                    SchemaHelper::getLabelTextField($disabled)->required(),
                    SchemaHelper::getEnableVariableSubstitutionToggle($disabled),
                    SchemaHelper::getHideLabelToggle($disabled),
                ])
                ->columns(1),
            Fieldset::make('Values')
                ->schema([
                Select::make('elementable_data.defaultSelected')
                    ->label('Default Selected Value')
                    ->options(function (callable $get) {
                        $options = $get('elementable_data.options') ?? [];
                        $selectOptions = [];
                        foreach ($options as $option) {
                            if (!empty($option['value'])) {
                                $selectOptions[$option['value']] = $option['label'] ?? $option['value'];
                            }
                        }
                        return $selectOptions;
                    })
                    ->disabled($disabled),
                Repeater::make('elementable_data.options')
                    ->label('Options')
                    ->schema([
                        TextInput::make('label')
                            ->label('Option Label')
                            ->required()
                            ->columnSpan(2)
                            ->autocomplete(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                $value = $get('value');
                                if (empty($value) && !empty($state)) {
                                    $slug = \Illuminate\Support\Str::slug($state, '-');
                                    $set('value', $slug);
                                }
                            }),
                        TextInput::make('value')
                            ->label('Option Value')
                            ->required()
                            ->columnSpan(2)
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('regenerate_value')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip('Regenerate from Option Label')
                                    ->action(function (callable $set, callable $get) {
                                        $label = $get('label');
                                        if (!empty($label)) {
                                            $slug = \Illuminate\Support\Str::slug($label, '-');
                                            $set('value', $slug);
                                        }
                                    })
                            ),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel('Add Option')
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn(array $state): ?string => $state['label'] ?? 'Option')
                    ->disabled($disabled)
                    ->minItems(1),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get the form element that owns this select input element.
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
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'enableVarSub' => $this->enableVarSub,
            'defaultSelected' => $this->defaultSelected,
        ];
    }

    /**
     * Get the options for this select input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'hideLabel' => false,
            'labelText' => '',
            'enableVarSub' => false,
            'defaultSelected' => null,
            'options' => [
                ['label' => 'True', 'value' => 'true'],
                ['label' => 'False', 'value' => 'false'],
            ],
        ];
    }
}
