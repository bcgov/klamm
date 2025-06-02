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
use Filament\Forms\Components\View;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Session;
use App\Models\FormVersion;
use App\Jobs\GenerateFormTemplateJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class FormVersionBuilder
{
    const CACHE_TTL = 3600;

    public static function schema()
    {
        gc_collect_cycles();

        FormDataHelper::load();

        return Grid::make()
            ->schema([
                Select::make('form_id')
                    ->relationship('form', 'form_id_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->searchable()
                    ->default(request()->query('form_id_title'))
                    // Cache form relationship data to avoid repeated queries
                    ->getSearchResultsUsing(function (string $search) {
                        $cacheKey = "form_search_results:" . md5($search);
                        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($search) {
                            return DB::table('forms')
                                ->where('form_id_title', 'like', "%{$search}%")
                                ->select('id', 'form_id_title')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn($row) => [$row->id => $row->form_id_title])
                                ->toArray();
                        });
                    }),
                Select::make('status')
                    ->options(function () {
                        return Cache::remember('form_status_options', self::CACHE_TTL, function () {
                            return FormVersion::getStatusOptions();
                        });
                    })
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
                            ->relationship('formDataSources', 'name')
                            ->getOptionLabelUsing(
                                fn($value) =>
                                Cache::remember("form_data_source:{$value}", self::CACHE_TTL, function () use ($value) {
                                    return DB::table('form_data_sources')->where('id', $value)->value('name');
                                })
                            )
                            ->options(function () {
                                return Cache::remember('form_data_sources_options', self::CACHE_TTL, function () {
                                    return DB::table('form_data_sources')
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn($row) => [$row->id => $row->name])
                                        ->toArray();
                                });
                            }),
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
                    ->blockPreviews()
                    ->live(onBlur: true)
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

                    ->cloneable()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $formVersionId = $get('id');
                        // handle when form version ID is not yet set
                        if (!$formVersionId) return;

                        $components = $get('components');
                        // Free memory before template generation
                        gc_collect_cycles();

                        $requestedAt = now()->unix();

                        // Throttled template generation to avoid excessive processing
                        $lastRequestedAt = Cache::get("formtemplate:{$formVersionId}:draft_requested_at", 0);
                        if ($requestedAt - $lastRequestedAt < 5) return;

                        // Stores the requested timestamp for job deduplication and throttling
                        Cache::tags(['form-template'])->put(
                            "formtemplate:{$formVersionId}:draft_requested_at",
                            $requestedAt,
                            now()->addDay()
                        );

                        // Dispatch form generation job in the background with the updated components
                        // Submits as draft; New job will be created for final version on save
                        GenerateFormTemplateJob::dispatch($formVersionId, $requestedAt, $components, true);
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
                        // Get components data and cache it to avoid repeated processing
                        $components = $get('components') ?? [];
                        $formVersionId = $get('id');

                        // Calculate highest ID efficiently with memory optimization
                        if ($formVersionId) {
                            $highestId = Cache::remember("form_version:{$formVersionId}:highest_id", self::CACHE_TTL, function () use ($components) {
                                // Process in chunks to avoid memory issues with large forms
                                return self::getHighestIDChunked($components) + 1;
                            });
                        } else {
                            $highestId = self::getHighestIDChunked($components) + 1;
                        }

                        Session::put('elementCounter', $highestId);

                        // Clean up after processing
                        gc_collect_cycles();
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
                                    Cache::tags(['form-template'])->put($cacheKey, $json, now()->addDay());

                                    Notification::make()
                                        ->title('Template Generated!')
                                        ->body('Form template generated successfully and copied to clipboard.')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    $set('generated_text', 'Error generating template: ' . $e->getMessage());
                                    return;
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
                    ->id('data.generated_text')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    /**
     * Function to find highest used instance ID with chunked processing
     * Optimized to use memoization and chunk processing to avoid memory issues
     */
    protected static function getHighestIDChunked(array $blocks): int
    {
        if (empty($blocks)) {
            return 0;
        }

        // Process only 20 blocks at a time to manage memory usage
        $maxID = 0;
        $chunks = array_chunk($blocks, 20);

        foreach ($chunks as $chunkIndex => $chunk) {
            // Release memory periodically
            if ($chunkIndex > 0 && $chunkIndex % 5 === 0) {
                gc_collect_cycles();
            }

            $chunkMax = self::processBlockChunk($chunk);
            $maxID = max($maxID, $chunkMax);
        }

        return $maxID;
    }

    /**
     * Process a chunk of blocks to find the highest ID
     */
    protected static function processBlockChunk(array $blockChunk): int
    {
        static $memoizedResults = [];
        $maxID = 0;

        foreach ($blockChunk as $block) {
            // Check top-level elements
            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                // More efficient string processing
                if (strpos($idString, 'element') === 0) {
                    $numericPart = substr($idString, 7); // Skip 'element' prefix
                    if (is_numeric($numericPart) && $numericPart > 0) {
                        $id = (int) $numericPart;
                        $maxID = max($maxID, $id);
                    }
                }
            }

            // Handle memory-efficient nested component processing
            if (isset($block['data']['components']) && is_array($block['data']['components']) && !empty($block['data']['components'])) {
                // Use cache for previously processed component groups to save memory
                $componentsHash = md5(serialize($block['data']['components']));

                if (isset($memoizedResults[$componentsHash])) {
                    $componentMax = $memoizedResults[$componentsHash];
                } else {
                    // Process in smaller chunks for very large nested components
                    if (count($block['data']['components']) > 50) {
                        $componentMax = self::getHighestIDChunked($block['data']['components']);
                    } else {
                        $componentMax = self::getHighestID($block['data']['components']);
                    }

                    // Cache result to avoid reprocessing
                    $memoizedResults[$componentsHash] = $componentMax;

                    // Limit the size of the memoization cache
                    if (count($memoizedResults) > 100) {
                        // Remove oldest entries when cache gets too large
                        $memoizedResults = array_slice($memoizedResults, -50, 50, true);
                    }
                }

                $maxID = max($maxID, $componentMax);
            }

            // Memory-efficient process for form fields
            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields']) && !empty($block['data']['form_fields'])) {
                // Process form fields similarly to components
                $fieldsHash = md5(serialize($block['data']['form_fields']));

                if (isset($memoizedResults[$fieldsHash])) {
                    $fieldsMax = $memoizedResults[$fieldsHash];
                } else {
                    // Process in smaller chunks for very large field groups
                    if (count($block['data']['form_fields']) > 50) {
                        $fieldsMax = self::getHighestIDChunked($block['data']['form_fields']);
                    } else {
                        $fieldsMax = self::getHighestID($block['data']['form_fields']);
                    }

                    // Cache result
                    $memoizedResults[$fieldsHash] = $fieldsMax;
                }

                $maxID = max($maxID, $fieldsMax);
            }
        }

        return $maxID;
    }

    /**
     * Original function to find highest used instance ID
     * Optimized to use memoization to avoid recalculating
     */
    protected static function getHighestID(array $blocks): int
    {
        static $memoizedResults = [];

        // Use a unique hash as the cache key
        $cacheKey = md5(serialize($blocks));

        // Return memoized result if available
        if (isset($memoizedResults[$cacheKey])) {
            return $memoizedResults[$cacheKey];
        }

        $maxID = 0;

        if (empty($blocks)) {
            return $maxID;
        }

        foreach ($blocks as $block) {
            // Check top-level elements
            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                // More efficient string processing
                if (strpos($idString, 'element') === 0) {
                    $numericPart = substr($idString, 7); // Skip 'element' prefix
                    if (is_numeric($numericPart) && $numericPart > 0) {
                        $id = (int) $numericPart;
                        $maxID = max($maxID, $id);
                    }
                }
            }

            // Process nested components
            if (isset($block['data']['components']) && is_array($block['data']['components']) && !empty($block['data']['components'])) {
                $maxID = max($maxID, self::getHighestID($block['data']['components']));
            }

            // Process form fields
            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields']) && !empty($block['data']['form_fields'])) {
                $maxID = max($maxID, self::getHighestID($block['data']['form_fields']));
            }
        }

        // Store result in memoization cache
        $memoizedResults[$cacheKey] = $maxID;

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
