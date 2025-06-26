<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;
use App\Http\Middleware\CheckRole;
use App\Models\FormSchemaImportSession;
use App\Models\Ministry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\Request;

class FormSchemaImporterResource extends Resource
{
    protected static ?string $model = FormSchemaImportSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Form Administration';

    protected static ?string $navigationLabel = 'Form Migrations';

    protected static ?string $slug = 'schema-import';

    protected static ?string $recordTitleAttribute = 'session_name';



    // Explicitly set the panel to forms
    public static function canAccess(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'forms';
    }

    /**
     * Navigation should only be visible for:
     * - Admins (always)
     * - Form developers who have any migrations (any status)
     */
    public static function shouldRegisterNavigation(): bool
    {
        $request = request();

        // Always show for admins
        if (CheckRole::hasRole($request, 'admin')) {
            return true;
        }

        // Show for form-developers who have any migrations
        if (CheckRole::hasRole($request, 'form-developer')) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user) {
                return static::getModel()::where('user_id', $user->id)->exists();
            }
        }

        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::forCurrentUser()->active()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $count = static::getNavigationBadge();
        return $count > 0 ? 'primary' : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_name')
                    ->label('Import Session')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'blue' => 'in_progress',
                        'green' => 'completed',
                        'red' => 'failed',
                        'orange' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('target_form_id')
                    ->label('Target Form')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('targetMinistry.name')
                    ->label('Ministry')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('total_fields')
                    ->label('Total Fields')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('mapped_fields')
                    ->label('Mapped')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->total_fields > 0) {
                            $percentage = round(($state / $record->total_fields) * 100);
                            return "{$state}/{$record->total_fields} ({$percentage}%)";
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn(FormSchemaImportSession $record): bool => $record->canBeResumed())
                    ->url(
                        fn(FormSchemaImportSession $record): string =>
                        static::getUrl('import_session', ['session' => $record->session_token])
                    ),

                Tables\Actions\Action::make('view_result')
                    ->label('View Result')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(
                        fn(FormSchemaImportSession $record): bool =>
                        $record->status === 'completed' && $record->result_form_version_id
                    )
                    ->url(
                        fn(FormSchemaImportSession $record): string =>
                        route('filament.forms.resources.form-versions.view', ['record' => $record->result_form_version_id])
                    )
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(FormSchemaImportSession $record): bool => $record->canBeDeleted()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_activity_at', 'desc');
    }

    /**
     * Parse schema and return summary information (for UI, synchronous)
     */
    public static function parseSchema($content): ?array
    {
        return self::parseSchemaContent($content);
    }

    /**
     * Parse schema and return summary information (for queue/job, or UI)
     * This is the original parseSchema logic, moved here for use by jobs and UI.
     */
    public static function parseSchemaContent($content): ?array
    {
        try {
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            // Count fields recursively
            $fieldCount = 0;
            $containerCount = 0;

            // Recursive function to count fields and containers
            $countElements = function ($elements) use (&$fieldCount, &$containerCount, &$countElements) {
                foreach ($elements as $element) {
                    // Handle new format with elementType
                    if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements') {
                        $containerCount++;

                        if (isset($element['elements'])) {
                            $countElements($element['elements']);
                        }
                    }
                    // Handle old format with type=container
                    elseif (isset($element['type']) && $element['type'] === 'container') {
                        $containerCount++;

                        if (isset($element['children'])) {
                            $countElements($element['children']);
                        }
                    }
                    // Count any other element as a field
                    else {
                        $fieldCount++;
                    }
                }
            };

            // Handle import format
            if (isset($data['data']) && isset($data['data']['elements'])) {
                $countElements($data['data']['elements']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'adze-template',
                ];
            }
            // Handle old format
            elseif (isset($data['fields'])) {
                $countElements($data['fields']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => $data['format'] ?? 'legacy',
                ];
            } else {
                // Unknown format
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => 0,
                    'container_count' => 0,
                    'format' => 'unknown',
                ];
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchemaImport::route('/'),
            'import' => Pages\ImportSchema::route('/import'),
            'import_session' => Pages\ImportSchema::route('/import/{session}'),
        ];
    }
}
