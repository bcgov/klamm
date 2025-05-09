<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use App\Filament\Components\ContainerBlock;
use App\Filament\Components\FieldGroupBlock;
use App\Filament\Components\FormFieldBlock;
use App\Helpers\UniqueIDsHelper;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Session;
use App\Models\FormVersion;
use App\Jobs\GenerateFormTemplateJob;
use Illuminate\Support\Facades\Cache;

use Filament\Notifications\Notification;

class FormVersionBuilder
{
    public static function schema()
    {
        FormDataHelper::load();

        return Grid::make()
            ->schema([
                Select::make('form_id')
                    ->relationship('form', 'form_id_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->searchable()
                    ->default(request()->query('form_id_title')),
                Select::make('status')
                    ->options(FormVersion::getStatusOptions())
                    ->default('draft')
                    ->disabled()
                    ->required(),
                Section::make('Form Properties')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->compact()
                    ->schema([
                        Fieldset::make('Requester Information')
                            ->schema([
                                TextInput::make('form_requester_name')
                                    ->label('Name'),
                                TextInput::make('form_requester_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Requester Information'),
                        Fieldset::make('Approver Information')
                            ->schema([
                                TextInput::make('form_approver_name')
                                    ->label('Name'),
                                TextInput::make('form_approver_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Approver Information'),
                        Select::make('deployed_to')
                            ->label('Deployed To')
                            ->options([
                                'dev' => 'Development',
                                'test' => 'Testing',
                                'prod' => 'Production',
                            ])
                            ->columnSpan(1)
                            ->nullable()
                            ->afterStateUpdated(fn(callable $set) => $set('deployed_at', now())),
                        DateTimePicker::make('deployed_at')
                            ->label('Deployment Date')
                            ->columnSpan(1),
                        Select::make('form_data_sources')
                            ->multiple()
                            ->preload()
                            ->columnSpan(1)
                            ->relationship('formDataSources', 'name'),
                        TextInput::make('footer')
                            ->columnSpanFull(),
                        Textarea::make('comments')
                            ->columnSpanFull()
                            ->maxLength(500),
                    ]),
                Builder::make('components')
                    ->label('Form Elements')
                    ->addActionLabel('Add to Form Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->cloneAction(UniqueIDsHelper::cloneElement())
                    ->columnSpan(2)
                    ->blockNumbers(false)
                    ->cloneable()
                    ->afterStateUpdated(function (Get $get) {
                        $formVersionId = $get('id');
                        // handle when form version ID is not yet set
                        if (!$formVersionId) return;

                        // Free memory before template generation
                        gc_collect_cycles();

                        $requestedAt = now()->unix();

                        // Throttle template generation to avoid excessive processing
                        $lastRequestedAt = Cache::get("formtemplate:{$formVersionId}:requested_at", 0);
                        if ($requestedAt - $lastRequestedAt < 5) return;

                        // remember the latest request timestamp
                        Cache::put(
                            "formtemplate:{$formVersionId}:requested_at",
                            $requestedAt,
                            now()->addHours(1)
                        );

                        // dispatch form generation job in the background
                        GenerateFormTemplateJob::dispatch($formVersionId, $requestedAt);
                    })
                    ->blockPreviews()
                    ->editAction(
                        fn(Action $action) => $action
                            ->visible(fn() => true)
                            ->icon(function ($livewire) {
                                return $livewire instanceof \Filament\Resources\Pages\ViewRecord
                                    ? 'heroicon-o-eye'
                                    : 'heroicon-o-pencil';
                            })
                            ->label(function ($livewire) {
                                return $livewire instanceof \Filament\Resources\Pages\ViewRecord
                                    ? 'View'
                                    : 'Edit';
                            })
                            ->disabledForm(fn($livewire) => ($livewire instanceof \Filament\Resources\Pages\ViewRecord)) // Disable the form
                            ->modalHeading('View Form Field')
                            ->modalSubmitAction(function ($action, $livewire) {
                                if ($livewire instanceof \Filament\Resources\Pages\ViewRecord) {
                                    return false;
                                } else {
                                    $action->label('Save');
                                }
                            })
                            ->modalCancelAction(function ($action, $livewire) {
                                if ($livewire instanceof \Filament\Resources\Pages\ViewRecord) {
                                    $action->label('Close');
                                } else {
                                    $action->label('Cancel');
                                }
                            })
                    )
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        Session::put('elementCounter', self::getHighestID($get('components')) + 1);
                        FormDataHelper::ensureFullyLoaded();
                    })
                    ->blocks([
                        FormFieldBlock::make(fn() => UniqueIDsHelper::calculateElementID()),
                        FieldGroupBlock::make(fn() => UniqueIDsHelper::calculateElementID()),
                        ContainerBlock::make(fn() => UniqueIDsHelper::calculateElementID()),
                    ]),
                // Used by the Create and Edit pages to store IDs in session, so that Blocks can validate their rules.
                Hidden::make('all_instance_ids')
                    ->default(fn(Get $get) => $get('all_instance_ids') ?? [])
                    ->dehydrated(fn() => true),
                // Components for view View page
                Actions::make([
                    // Checks if form template is generated in cache, otherwise generates it
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set, $livewire) {
                            $formVersionId = $get('id');
                            if (!$formVersionId) {
                                $set('generated_text', 'Please save the form version first before generating template.');
                                return;
                            }

                            $cacheKey = "formtemplate:{$formVersionId}:cached_json";
                            $json = Cache::get($cacheKey);
                            if (!$json) {
                                try {
                                    $set('generated_text', 'Generating template...');
                                    $json = FormTemplateHelper::generateJsonTemplate($formVersionId);
                                    $set('generated_text', $json);
                                    Cache::put($cacheKey, $json, now()->addHours(6));
                                } catch (\Exception $e) {
                                    $set('generated_text', 'Error generating template: ' . $e->getMessage());
                                }
                            } else {
                                $set('generated_text', $json);
                            }
                            $livewire->js('
                                setTimeout(() => {
                                    const textarea = document.getElementById("data.generated_text");
                                    if (!textarea || !textarea.value) {
                                        console.error("Could not find textarea or it has no value");
                                        return;
                                    }
                                    const textToCopy = textarea.value;
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(textToCopy)
                                            .catch(err => {
                                                console.error("Failed to copy: ", err);
                                            });
                                    } else {
                                        // Fallback
                                        try {
                                            textarea.select();
                                            document.execCommand("copy");
                                        } catch (err) {
                                            console.error("Fallback copy failed: ", err);
                                        }
                                    }
                                }, 500);
                            ');
                            Notification::make()
                                ->title('Template Generated!')
                                ->body('Form template generated successfully and copied to clipboard.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                ]),
                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    // Function to find highest used instance ID
    protected static function getHighestID(array $blocks): int
    {
        $maxID = 0;
        foreach ($blocks as $block) {
            // Check top-level elements
            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                $numericPart = str_replace('element', '', $idString); // Remove the 'element' prefix
                if (is_numeric($numericPart) && $numericPart > 0) {
                    $id = (int) $numericPart;
                    $maxID = max($maxID, $id); // Update the maximum ID
                }
            }
            // Recursively check elements inside of Containers
            if (isset($block['data']['components']) && is_array($block['data']['components'])) {
                $nestedMaxID = self::getHighestID($block['data']['components']);
                $maxID = max($maxID, $nestedMaxID);
            }
            // Recursively check elements inside of Groups
            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields'])) {
                $nestedMaxID = self::getHighestID($block['data']['form_fields']);
                $maxID = max($maxID, $nestedMaxID);
            }
        }

        return $maxID;
    }

    // Recursively assign new instance IDs to cloned elements
    protected static function processNestedInstanceIDs(array $data): array
    {
        return collect($data)
            ->map(function ($value) {
                if (is_array($value)) {
                    if (array_key_exists('instance_id', $value)) {
                        $value['instance_id'] = UniqueIDsHelper::calculateElementID();
                    }
                    return static::processNestedInstanceIDs($value);
                }

                return $value;
            })
            ->all();
    }
}
