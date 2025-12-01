<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;
use App\Models\AnonymizationMethods;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AnonymizationMethodResource extends Resource
{
    protected static ?string $model = AnonymizationMethods::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Methods';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Method Details')
                    ->description('Name and classify the reusable masking technique so other users can quickly discover it in the catalog.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Keep the name concise and action-oriented (e.g. "Hash contact email").'),
                        Forms\Components\TextInput::make('category')
                            ->label('Category')
                            ->datalist(fn() => self::categoryOptionsWithExisting())
                            ->maxLength(255)
                            ->placeholder('e.g. Hashing / Deterministic')
                            ->helperText('Categories keep the method library tidy. Pick one of the common groupings or enter a custom label.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Summarize when to use this method and any caveats for downstream teams.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Implementation Notes')
                    ->description('Capture the plain-language intent, supporting documentation, and the executable SQL snippet.')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('what_it_does')
                            ->label('What it does')
                            ->columnSpanFull()
                            ->helperText('Explain the user-visible outcome. This content can include bullet lists or links to policy references.'),
                        Forms\Components\MarkdownEditor::make('how_it_works')
                            ->label('How it works')
                            ->columnSpanFull()
                            ->helperText('Document the algorithmic steps or assumptions (e.g. seeded hash, surrogate lookups, referential integrity expectations).'),
                        Forms\Components\Textarea::make('sql_block')
                            ->label('SQL block')
                            ->rows(12)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('Use placeholders such as {{TABLE}}, {{COLUMN}}, or {{ALIAS}} when composing generalized snippets. These will be replaced during job generation.'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Preview & Guidance')
                    ->schema([
                        Forms\Components\Placeholder::make('sql_preview')
                            ->label('SQL preview')
                            ->content(fn(Get $get) => self::renderSqlPreview($get('sql_block'))),
                        Forms\Components\Placeholder::make('prompting_tip')
                            ->label('Prompt template')
                            ->content(fn(Get $get) => self::promptingHelp($get('name'), $get('category'))),
                        Forms\Components\Placeholder::make('usage_hint')
                            ->label('Column usage')
                            ->content(function (?AnonymizationMethods $record) {
                                if (! $record) {
                                    return 'Save the method to see how many columns reference it.';
                                }

                                $count = (int) $record->usage_count;

                                return $count > 0
                                    ? number_format($count) . ' column' . ($count === 1 ? '' : 's') . ' currently reference this method.'
                                    : 'No columns reference this method yet.';
                            }),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Record Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->created_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->updated_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Summary')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_sql')
                    ->label('SQL')
                    ->boolean()
                    ->tooltip(fn(AnonymizationMethods $record) => filled($record->sql_block) ? 'SQL block ready' : 'Missing SQL block')
                    ->state(fn(AnonymizationMethods $record) => filled($record->sql_block)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn() => AnonymizationMethods::query()
                        ->whereNotNull('category')
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->weight('bold'),
                                TextEntry::make('category')
                                    ->placeholder('—'),
                            ])->columns(2),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided.'),
                    ]),
                InfolistSection::make('How It Works')
                    ->schema([
                        TextEntry::make('what_it_does')
                            ->label('What it does')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('how_it_works')
                            ->label('How it works')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ])
                    ->collapsed()
                    ->collapsible(),
                InfolistSection::make('SQL Reference')
                    ->schema([
                        TextEntry::make('sql_block')
                            ->label('SQL block')
                            ->columnSpanFull()
                            ->placeholder('No SQL block documented.')
                            ->extraAttributes([
                                'class' => 'font-mono whitespace-pre-wrap text-sm'
                            ]),
                    ])
                    ->hidden(fn($record) => blank($record?->sql_block)),
                InfolistSection::make('Usage Metrics')
                    ->schema([
                        TextEntry::make('usage_count')
                            ->label('Columns using this method'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Updated'),
                    ])
                    ->columns(3),
                InfolistSection::make('Columns in Scope')
                    ->schema([
                        TextEntry::make('columns_preview')
                            ->label('Examples')
                            ->getStateUsing(fn(AnonymizationMethods $record) => self::columnsPreview($record))
                            ->columnSpanFull()
                            ->placeholder('No columns reference this method yet.'),
                    ])
                    ->visible(fn(AnonymizationMethods $record) => $record->usage_count > 0),
                InfolistSection::make('Prompt Guidance')
                    ->schema([
                        TextEntry::make('prompt_template')
                            ->label('Suggested template')
                            ->getStateUsing(fn(AnonymizationMethods $record) => self::promptingHelp($record->name, $record->category))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-sm text-gray-700']),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymizationMethods::route('/'),
            'create' => Pages\CreateAnonymizationMethod::route('/create'),
            'view' => Pages\ViewAnonymizationMethod::route('/{record}'),
            'edit' => Pages\EditAnonymizationMethod::route('/{record}/edit'),
        ];
    }

    protected static function categoryOptions(): array
    {
        return [
            'Hashing / Deterministic',
            'Masking / Redaction',
            'Pseudonymization',
            'Aggregation / Bucketing',
            'Synthetic Data',
            'Utility / Helper',
        ];
    }

    protected static function categoryOptionsWithExisting(): array
    {
        $existing = AnonymizationMethods::query()
            ->whereNotNull('category')
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique(array_merge(self::categoryOptions(), $existing)));
    }

    protected static function renderSqlPreview(?string $sql): HtmlString
    {
        if (blank($sql)) {
            return new HtmlString('<p class="text-sm text-gray-500">Add SQL to preview how it will appear in job exports.</p>');
        }

        $escaped = e($sql);

        return new HtmlString('<pre class="rounded-lg bg-slate-950/5 p-4 font-mono text-sm leading-relaxed">' . $escaped . '</pre>');
    }

    protected static function promptingHelp(?string $name, ?string $category): HtmlString
    {
        $method = $name ?: '<<Method Name>>';
        $categoryLabel = $category ?: '<<Category>>';

        $template = <<<HTML
<div class="space-y-2 text-sm text-gray-600">
    <pre class="overflow-x-auto rounded-lg bg-slate-950/5 p-3 font-mono text-xs">Generate an anonymization method named "{$method}" (category: {$categoryLabel}).
Describe the transformation and produce a SQL snippet that:
- Preserves referential integrity where possible
- Avoids selecting live production data
- Provides comments explaining intent
Use placeholders such as {{TABLE}} and {{COLUMN}} so the snippet can be reused.</pre>
</div>
HTML;

        return new HtmlString($template);
    }

    protected static function columnsPreview(AnonymizationMethods $record): string
    {
        $columns = $record->columns()
            ->with(['table.schema:id,schema_name', 'table:id,table_name,schema_id'])
            ->orderBy('column_name')
            ->limit(8)
            ->get();

        if ($columns->isEmpty()) {
            return 'No columns reference this method yet.';
        }

        $labels = $columns->map(function ($column) {
            $schema = $column->table?->schema?->schema_name;
            $table = $column->table?->table_name;
            $parts = array_filter([$schema, $table, $column->column_name]);

            return $parts !== [] ? implode('.', $parts) : $column->column_name;
        })->all();

        $preview = implode(', ', $labels);
        $remaining = max(0, (int) $record->usage_count - count($labels));

        if ($remaining > 0) {
            $preview .= ' +' . $remaining . ' more';
        }

        return $preview;
    }
}
