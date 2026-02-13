<?php

namespace App\Filament\Exports;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class AnonymousSiebelColumnExporter extends Exporter
{
    protected static ?string $model = AnonymousSiebelColumn::class;

    // export uses anonymization queue
    public function getJobQueue(): ?string
    {
        return 'anonymization';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'table.schema.database',
            'dataType',
            'anonymizationMethods',
            'tags',
            'parentColumns',
            'childColumns',
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('table.schema.database.database_name')
                ->label('DB_INSTANCE'),
            ExportColumn::make('table.schema.schema_name')
                ->label('OWNER'),
            ExportColumn::make('qualfield')
                ->label('QUALFIELD')
                ->state(function (AnonymousSiebelColumn $record) {
                    if ($record->qualfield) {
                        return $record->qualfield;
                    }

                    $table = $record->getRelationValue('table');

                    if (! $table || ! $record->column_name) {
                        return null;
                    }

                    return $table->table_name . '.' . $record->column_name;
                }),
            ExportColumn::make('column_id')
                ->label('COLUMN_ID'),
            ExportColumn::make('table.table_name')
                ->label('TABLE_NAME'),
            ExportColumn::make('column_name')
                ->label('COLUMN_NAME'),
            ExportColumn::make('anonymization_required')
                ->label('ANON_RULE')
                ->formatStateUsing(fn($state) => self::formatNullableBooleanToYn($state)),
            ExportColumn::make('anonymizationMethods')
                ->label('ANON_NOTE')
                ->state(fn(AnonymousSiebelColumn $record) => $record->anonymizationMethods
                    ->sortBy('name')
                    ->pluck('name')
                    ->values()
                    ->all())
                ->formatStateUsing(fn($state) => self::joinList($state)),
            ExportColumn::make('pr_key')
                ->label('PR_KEY'),
            ExportColumn::make('ref_tab_name')
                ->label('REF_TAB_NAME')
                ->state(fn(AnonymousSiebelColumn $record) => $record->ref_tab_name ?: $record->related_columns_raw),
            ExportColumn::make('num_distinct')
                ->label('NUM_DISTINCT'),
            ExportColumn::make('num_not_null')
                ->label('NUM_NOT_NULL'),
            ExportColumn::make('num_nulls')
                ->label('NUM_NULLS'),
            ExportColumn::make('num_rows')
                ->label('NUM_ROWS'),
            ExportColumn::make('dataType.data_type_name')
                ->label('DATA_TYPE'),
            ExportColumn::make('data_length')
                ->label('DATA_LENGTH'),
            ExportColumn::make('data_precision')
                ->label('DATA_PRECISION'),
            ExportColumn::make('data_scale')
                ->label('DATA_SCALE'),
            ExportColumn::make('column_comment')
                ->label('COMMENTS'),
            ExportColumn::make('sbl_user_name')
                ->label('SBL_USER_NAME'),
            ExportColumn::make('sbl_desc_text')
                ->label('SBL_DESC_TEXT'),
            ExportColumn::make('nullable')
                ->label('NULLABLE')
                ->formatStateUsing(fn($state) => self::formatNullableBooleanToYn($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your Siebel column export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    protected static function joinList($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $items = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return trim($item);
            }

            if (is_object($item) && isset($item->name)) {
                return trim((string) $item->name);
            }

            if (is_array($item) && isset($item['name'])) {
                return trim((string) $item['name']);
            }

            return is_scalar($item) ? trim((string) $item) : null;
        }, $value)));

        return $items === [] ? null : implode('; ', $items);
    }

    protected static function formatNullableBooleanToYn($state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return (bool) $state ? 'Y' : 'N';
    }

    protected static function formatRelatedColumns($raw, $fallbackRaw = null): ?string
    {
        if (is_array($raw)) {
            return self::joinList($raw);
        }

        if (is_string($raw)) {
            $list = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);

            return self::joinList($list);
        }

        if (is_string($fallbackRaw) && trim($fallbackRaw) !== '') {
            $list = preg_split('/[\s,]+/', $fallbackRaw, -1, PREG_SPLIT_NO_EMPTY);

            return self::joinList($list);
        }

        return null;
    }
}
