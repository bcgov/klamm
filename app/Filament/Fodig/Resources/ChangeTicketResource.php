<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\ChangeTicketResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use App\Filament\Fodig\Resources\AnonymousSiebelTableResource;
use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource;
use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource;
use App\Filament\Fodig\Resources\AnonymousUploadResource;
use App\Models\Anonymizer\ChangeTicket;
use App\Models\Anonymizer\AnonymousUpload;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ChangeTicketResource extends Resource
{


    // Shared option sets reused across the form, table, and filters.
    private const STATUS_OPTIONS = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
    ];

    private const PRIORITY_OPTIONS = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
    ];

    private const SEVERITY_OPTIONS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    private const SCOPE_TYPE_OPTIONS = [
        'database' => 'Database',
        'schema' => 'Schema',
        'table' => 'Table',
        'column' => 'Column',
        'upload' => 'Upload',
    ];

    protected static ?string $model = ChangeTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Change Tickets';
    protected static ?int $navigationSort = 75;

    public static function getNavigationBadge(): ?string
    {
        $count = ChangeTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->where('title', 'like', 'URGENT:%')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }


    // include soft-deleted records and sort by importance.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->orderByRaw("CASE severity WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderByRaw("CASE status WHEN 'open' THEN 3 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 1 WHEN 'dismissed' THEN 0 ELSE 0 END DESC")
            ->orderByDesc('created_at');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Ticket')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        ToggleButtons::make('status')
                            ->options(self::STATUS_OPTIONS)
                            ->inline()
                            ->required(),
                        ToggleButtons::make('priority')
                            ->options(self::PRIORITY_OPTIONS)
                            ->inline()
                            ->required(),
                        ToggleButtons::make('severity')
                            ->options(self::SEVERITY_OPTIONS)
                            ->inline()
                            ->required(),
                        Select::make('scope_type')
                            ->options(self::SCOPE_TYPE_OPTIONS),
                        TextInput::make('scope_name'),
                        Select::make('upload_id')
                            ->relationship('upload', 'original_name')
                            ->searchable(),
                        Textarea::make('impact_summary')->rows(4),
                        Textarea::make('diff_payload')->rows(6),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('status')->badge(),
                TextColumn::make('priority')->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('scope_type')->label('Scope')->toggleable(),
                TextColumn::make('scope_name')->toggleable(),
                TextColumn::make('upload.original_name')->label('Upload')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::STATUS_OPTIONS),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(self::PRIORITY_OPTIONS),
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'high' => self::SEVERITY_OPTIONS['high'],
                        'medium' => self::SEVERITY_OPTIONS['medium'],
                        'low' => self::SEVERITY_OPTIONS['low'],
                    ]),
                Tables\Filters\Filter::make('latest_upload')
                    ->label('Latest Upload')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $latestId = AnonymousUpload::query()->max('id');
                        if ($latestId) {
                            $query->where('upload_id', $latestId);
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label(fn(ChangeTicket $record): string => static::reviewActionLabel($record))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn(ChangeTicket $record): ?string => static::reviewUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn(ChangeTicket $record): bool => (bool) static::reviewUrl($record)),
                Tables\Actions\Action::make('mark_resolved')
                    ->label('Mark resolved')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(ChangeTicket $record): bool => ! $record->trashed() && $record->status !== 'resolved')
                    ->action(function (ChangeTicket $record): void {
                        $record->status = 'resolved';
                        $record->resolved_at = now();
                        $record->save();

                        $record->delete();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function reviewActionLabel(ChangeTicket $ticket): string
    {
        return match ($ticket->scope_type) {
            'column' => 'Review Column',
            'table' => 'Review Table',
            'schema' => 'Review Schema',
            'database' => 'Review Database',
            'upload' => 'Review Upload',
            default => 'Review',
        };
    }

    // Links to review url based on resource type
    public static function reviewUrl(ChangeTicket $ticket): ?string
    {
        // Upload-scope tickets may still have a usable upload_id even when scope_name is empty.
        if ($ticket->scope_type === 'upload') {
            return $ticket->upload_id
                ? AnonymousUploadResource::getUrl('view', ['record' => $ticket->upload_id])
                : null;
        }

        if (! $ticket->scope_type || ! $ticket->scope_name) {
            return null;
        }

        return match ($ticket->scope_type) {
            'column' => static::reviewColumnUrl($ticket->scope_name),
            'table' => static::reviewTableUrl($ticket->scope_name),
            'schema' => static::reviewSchemaUrl($ticket->scope_name),
            'database' => static::reviewDatabaseUrl($ticket->scope_name),
            default => null,
        };
    }


    // Resolve Ticket scope based on resource type helpers
    private static function reviewColumnUrl(string $scopeName): ?string
    {
        [$schemaName, $tableName, $columnName] = static::parseScopeTokens($scopeName, 3);
        if (! $schemaName || ! $tableName || ! $columnName) {
            return null;
        }

        $columnId = AnonymousSiebelColumn::query()
            ->withTrashed()
            ->where('column_name', $columnName)
            ->whereHas('table', function (Builder $q) use ($tableName, $schemaName): void {
                $q->where('table_name', $tableName)
                    ->whereHas('schema', fn(Builder $sq) => $sq->where('schema_name', $schemaName));
            })
            ->value('id');

        return $columnId ? AnonymousSiebelColumnResource::getUrl('view', ['record' => $columnId]) : null;
    }

    private static function reviewTableUrl(string $scopeName): ?string
    {
        [$schemaName, $tableName] = static::parseScopeTokens($scopeName, 2);
        if (! $schemaName || ! $tableName) {
            return null;
        }

        $tableId = AnonymousSiebelTable::query()
            ->withTrashed()
            ->where('table_name', $tableName)
            ->whereHas('schema', fn(Builder $q) => $q->where('schema_name', $schemaName))
            ->value('id');

        return $tableId ? AnonymousSiebelTableResource::getUrl('view', ['record' => $tableId]) : null;
    }

    private static function reviewSchemaUrl(string $scopeName): ?string
    {
        $schemaName = static::lastScopeToken($scopeName);
        if (! $schemaName) {
            return null;
        }

        $schemaId = AnonymousSiebelSchema::query()
            ->withTrashed()
            ->where('schema_name', $schemaName)
            ->value('id');

        return $schemaId ? AnonymousSiebelSchemaResource::getUrl('view', ['record' => $schemaId]) : null;
    }

    private static function reviewDatabaseUrl(string $scopeName): ?string
    {
        $databaseName = static::lastScopeToken($scopeName);
        if (! $databaseName) {
            return null;
        }

        $databaseId = AnonymousSiebelDatabase::query()
            ->withTrashed()
            ->where('database_name', $databaseName)
            ->value('id');

        return $databaseId ? AnonymousSiebelDatabaseResource::getUrl('edit', ['record' => $databaseId]) : null;
    }

    // parsing helpers for scope names
    private static function parseScopeTokens(string $scopeName, int $parts): array
    {
        return array_pad(explode('.', $scopeName, $parts), $parts, null);
    }

    private static function lastScopeToken(string $scopeName): string
    {
        return Str::of($scopeName)->afterLast('.')->toString();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeTickets::route('/'),
            'create' => Pages\CreateChangeTicket::route('/create'),
            'edit' => Pages\EditChangeTicket::route('/{record}/edit'),
        ];
    }
}
