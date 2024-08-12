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
use App\Models\FieldGroup;
use Illuminate\Support\Str;

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
                        ->label("Form Fields / Field Groups")
                        ->schema([
                            Forms\Components\Select::make("field")
                                ->label("Form Field / Group")
                                ->options($this->availableFields)
                                ->required()
                                ->live()
                                ->reactive()                                
                                ->searchable(),
                        ])
                        ->addActionLabel("Add Another Form Field / Group")
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
                                //
                                
                                //
                                $fieldsContent = $selectedFields
                                    ->map(function ($field) {                                        
                                        $selectedfieldId = $field['field'];
                                        if (Str::startsWith($selectedfieldId, 'fld_')) {
                                            $fieldId = Str::after($selectedfieldId, 'fld_');   
                                            $formField = FormField::find($fieldId);
                                            if($formField){                                                                                       
                                                return $this->getFieldContents($formField);
                                            }
                                        } elseif (Str::startsWith($selectedfieldId, 'grp_')) {
                                            $groupId = Str::after($selectedfieldId, 'grp_');             
                                            $fieldGroup = FieldGroup::find($groupId); 
                                            if($fieldGroup){                                                          
                                                return $this->getFieldGroupContents($fieldGroup);  
                                            }              
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

    protected function formatField($field,$index)
    {
        $base = [
            "type" => $field->dataType->name,
            "label" => $field->label,
            "id" => $field->name.'_'.(string)$index + 1,
            "fieldId" => (string)$field->id,
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
                return array_merge($base, [
                    "type" => "date-picker",                    
                    "labelText" => $field->label,
                    "placeholder" => "mm/dd/yyyy",
                ]);

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
            case "radio":
                return array_merge($base, [
                    "placeholder" => "Select your {$field->label}",
                    "helperText" => "Choose one from the list",
                    "fieldId" => $field->id,
                    "listItems" => collect(SelectOptions::where('form_field_id',$field->id)->get())            
                        ->map(function ($selectOption) {
                            return ["value" => $selectOption->value,
                                    "text" => $selectOption->label];
                        })
                        ->toArray(),
                ]);                  
            default:
                return $base;
        }
    }

    protected function formatGroup($group,$index)
    {
        $fieldsInGroup = $group->formFields;
        $fields = collect($fieldsInGroup)->map(function ($field,$index) {
            return $this->formatField($field,$index);
        } )->values()->all();  
        
        $base = [
            "type" => "group",
            "label" => $group->label,
            "id" => $group->name.'_'.(string)$index +1,
            "groupId" => (string)$group->id,
            "repeater" => $group->repeater,
            "codeContext" => [
                "name" => $group->name,
            ],     
            
        ];
       
        return array_merge($base,  [
            "groupItems" => [
                [
                "fields" => $fields, 
                ]
        ]]);  
            
    }

    protected function getFieldContents($formField)
    {
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

    protected function getFieldGroupContents($fieldGroup)
    {
        $labelWithIds = collect($fieldGroup->formFields)->map(function ($field) {
            return $field['label'] . '(' . StringHelper::removeHyphensAndCapitalize(
                $field->dataType->name
            ) . ')';
        })->implode(',');
        //    

        $fieldContent =
                $fieldGroup->label .
                " - " .
                $labelWithIds;
            
            return $fieldContent;
    } 

    public function submit()
    {
        $this->validate([
            "name" => "required|string|max:255",
            "description" => "required|string|max:65535",
            "selectedFields" => "required|array",
            //"selectedFields.*.field" => "required|exists:form_fields,id",
            "selectedFields.*.field" => "required",
            "ministry_id" => "required|exists:ministries,id",
        ]);

        $items = collect($this->selectedFields)->values()->map(function ($field,$index) {
           $selectedfieldId = $field['field'];
            if (Str::startsWith($selectedfieldId, 'fld_')) {
                $fieldId = Str::after($selectedfieldId, 'fld_');   
                $formField = FormField::find($fieldId);
                return $this->formatField($formField,$index);
            } elseif (Str::startsWith($selectedfieldId, 'grp_')) {
                $groupId = Str::after($selectedfieldId, 'grp_');             
                $fieldGroup = FieldGroup::find($groupId);                
                return $this->formatGroup($fieldGroup,$index);                
            }
           
        })->all();
        

        RenderedForm::create([
            "name" => $this->name,
            "description" => $this->description,
            "ministry_id" => $this->ministry_id,
            "structure" => json_encode([
                "version" => "0.0.1",
                "ministry_id" => $this->ministry_id,
                "id" => (string) Str::uuid(),
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
        $this->availableFields = ['Fields' => FormField::all()->mapWithKeys(function ($item) {
                                        return ['fld_' .$item->id => $item->label];
                                    })->toArray(),
                                    'Groups' => FieldGroup::all()->mapWithKeys(function ($group) {
                                        return ['grp_' .$group->id => $group->label];
                                    })->toArray(),];
        $this->ministries = Ministry::select('id', 'name')->get()->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name']];
        });
    }
}