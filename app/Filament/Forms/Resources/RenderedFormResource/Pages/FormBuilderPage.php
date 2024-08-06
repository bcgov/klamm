<?php

namespace App\Filament\Forms\Resources\RenderedFormResource\Pages;

use App\Filament\Forms\Resources\RenderedFormResource;
use App\Models\FormField;
use App\Models\Ministry;
use App\Models\RenderedForm;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Blade;
use App\Models\SelectOptions;

class FormBuilderPage extends Page
{
    protected static string $resource = RenderedFormResource::class;

    protected static string $view = 'filament.resources.rendered-form-resource.pages.form-builder-page';

    public $name;
    public $description;
    public $ministry_id;
    public $formFields = [];
    public $selectedFields = [];
    public $availableFields = [];
    public $ministries = [];

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make("Step 1")->schema([
                    Forms\Components\TextInput::make("name")
                        ->label("Form Name")
                        ->required()
                        ->live(),
                    Forms\Components\Textarea::make("description")
                        ->label("Form Description")
                        ->required()
                        ->live(),
                    Forms\Components\Select::make("ministry_id")
                        ->label("Ministry")
                        ->options($this->ministries)
                        ->required()
                        ->live(),
                ]),
                Forms\Components\Wizard\Step::make("Step 2")->schema([
                    Forms\Components\Repeater::make("selectedFields")
                        ->label("Form Fields")
                        ->schema([
                            Forms\Components\Select::make("field")
                                ->label("Form Field")
                                ->options($this->availableFields)
                                ->required()
                                ->live()
                                ->reactive()
                                ->distinct()
                                ->searchable(),
                        ])
                        ->addActionLabel("Add Another Form Field")
                        ->collapsible(false)
                        ->minItems(1)
                        ->reorderable(true)
                        ->defaultItems(1),
                ]),
                Forms\Components\Wizard\Step::make("Step 3")->schema([
                    Forms\Components\Card::make()->schema([
                        Forms\Components\Placeholder::make("")->content(
                            function ($get) {
                                $formName = $get("name");
                                $formDescription = $get("description");
                                $authorName = auth()->user()->name;
                                $ministry = Ministry::find($get("ministry_id"));
                                $ministryName = $ministry ? $ministry->name : "Unkown Ministry";
                                $selectedFields = collect(
                                    $get("selectedFields")
                                );
                                $fieldsContent = $selectedFields
                                    ->map(function ($field) {
                                        $formField = FormField::find(
                                            $field["field"]
                                        );
                                        if ($formField) {
                                            $fieldContent =
                                                $formField->label .
                                                " - " .
                                                StringHelper::removeHyphensAndCapitalize(
                                                    $formField->dataType->name
                                                );
                                            if (
                                                $formField->dataType->name ===
                                                "dropdown" &&
                                                is_array($formField->options)
                                            ) {
                                                $selectOptionForDD = SelectOptions::where('form_field_id',$formField->id)->get();
                                               
                                                $options = $selectOptionForDD->implode('label', ', ');
                                                $fieldContent .= " (Options: $options)";
                                            }
                                            return $fieldContent;
                                        }
                                        return "Unknown Field";
                                    })
                                    ->implode("<br>");

                                $content = "
                                            <div style='margin-bottom: 1rem;'>
                                                <div style='font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;'>Form Name:</div>
                                                <div style='margin-bottom: 1rem;'>$formName</div>
                                                
                                                <div style='font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;'>Form Description:</div>
                                                <div style='margin-bottom: 1rem;'>$formDescription</div>

                                                <div style='font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;'>Ministry:</div>
                                                <div style='margin-bottom: 1rem;'>$ministryName</div>
                                                
                                                <div style='font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;'>Author:</div>
                                                <div style='margin-bottom: 1rem;'>$authorName</div>
                                                
                                                <div style='font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;'>Selected Fields:</div>
                                                <div>$fieldsContent</div>
                                            </div>";

                                return new HtmlString($content);
                            }
                        ),
                    ]),
                ]),
            ])->submitAction(
                new HtmlString(
                    Blade::render(
                        <<<BLADE
    <x-filament::button
        type="submit"
    >
        Submit
    </x-filament::button>
BLADE
                    )
                )
            ),
        ];
    }

    protected function formatField($field)
    {
        $base = [
            "type" => $field->dataType->name,
            "label" => $field->label,
            "id" => (string)$field->id,
            "codeContext" => [
                "name" => $field->name,
            ],
        ];

        switch ($field->dataType->name) {
            case "text-input":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",
                    "helperText" => "{$field->label} as it appears on official documents",
                    "inputType" => "text",
                ]);
            case "text-area":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",
                    "helperText" => "{$field->label} as it appears on official documents",
                    "inputType" => "text",
                ]);
            case "dropdown":
                return array_merge($base, [
                    "placeholder" => "Select your {$field->label}",
                    "isMulti" => false,
                    "isInline" => false,
                    "selectionFeedback" => "top-after-reopen",
                    "direction" => "bottom",
                    "size" => "md",
                    "helperText" => "Choose one from the list",
                    "fieldId" => $field->id,
                    "listItems" => collect(SelectOptions::where('form_field_id',$field->id)->get())            
                        ->map(function ($selectOption) {
                            return ["value" => $selectOption->value,
                                    "text" => $selectOption->label];
                        })
                        ->toArray(),
                ]);
            case "checkbox":
                return array_merge($base, [
                    "label" => $field->label,
                    "helperText" => "Select item on or off",
                ]);
            case "toggle":
                return array_merge($base, [
                    "header" => "Enable {$field->label}",
                    "offText" => "Off",
                    "onText" => "On",
                    "disabled" => false,
                    "checked" => false,
                    "size" => "md",
                ]);
            case "date":
                return [
                    "type" => "date-picker",
                    "id" => (string)$field->name,
                    "labelText" => $field->label,
                    "placeholder" => "mm/dd/yyyy",
                ];

            case "button":
                return array_merge($base, [
                    "kind" => "primary",                    
                    "helperText" => "{$field->label}",
                    "size" => "lg",                    
                ]);
            case "number-input":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",
                    "helperText" => "{$field->label} as it appears on official documents",                    
                ]);
            case "text-info":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",                    
                    "style" => [
                        "marginBottom" => "20px",
                        "fontSize" => "20px"
                    ]
                ]); 
            case "link":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",
                    "value" => "#",                   
                    
                ]);
            case "file":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",                    
                    "labelTitle" => "Upload files", 
                    "labelDescription" => "Max file size is 500mb. Only .jpg files are supported.",
                     "buttonLabel" => "Add file", 
                     "buttonKind" => "primary" ,
                     "size" => "md" ,
                     "filenameStatus" => "edit",                                       
                    
                ]);
            case "table":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",                    
                    "tableTitle" => "My Table", 
                    "initialRows" => "3",
                    "initialColumns" => "3",
                    "initialHeaderNames" => "Hedaer1,Header2,Header3",                                                 
                    
                ]);              
            default:
                return $base;
        }
    }

    public function submit()
    {
        $this->validate([
            "name" => "required|string|max:255",
            "description" => "required|string|max:65535",
            "selectedFields" => "required|array",
            "selectedFields.*.field" => "required|exists:form_fields,id",
            "ministry_id" => "required|exists:ministries,id",
        ]);

        $items = collect($this->selectedFields)->map(function ($field) {
            $formField = FormField::find($field['field']);
            return $this->formatField($formField);
        })->values()->all();

        RenderedForm::create([
            "name" => $this->name,
            "description" => $this->description,
            "ministry_id" => $this->ministry_id,
            "structure" => json_encode([
                "version" => "0.0.1",
                "ministry_id" => $this->ministry_id,
                "id" => (string) \Str::uuid(),
                "lastModified" => now()->toIso8601String(),
                "title" => $this->name,
                "data" => [
                    "items" => $items,
                ],
            ]),
            "created_by" => auth()->id(),
        ]);

        Notification::make()
            ->title("Rendered form created successfully!")
            ->success()
            ->send();

        $this->redirect(RenderedFormResource::getUrl("index"));
    }

    public function mount()
    {
        $this->availableFields = FormField::select('id', 'label')->get()->mapWithKeys(function ($item) {
            return [$item['id'] => $item['label']];
        });
        $this->ministries = Ministry::select('id', 'name')->get()->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name']];
        });
    }
}
