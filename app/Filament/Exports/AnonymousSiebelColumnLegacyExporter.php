<?php

namespace App\Filament\Exports;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Actions\Exports\ExportColumn;

class AnonymousSiebelColumnLegacyExporter extends AnonymousSiebelColumnExporter
{
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('column_name')
                ->label('Name'),
            ExportColumn::make('changed')
                ->label('Changed')
                ->state(fn() => null),
            ExportColumn::make('table.table_name')
                ->label('Parent Table'),
            ExportColumn::make('parent_database')
                ->label('Parent Database')
                ->state(fn(AnonymousSiebelColumn $record) => $record->table?->schema?->database?->database_name),
            ExportColumn::make('parent_schema')
                ->label('Parent Schema')
                ->state(fn(AnonymousSiebelColumn $record) => $record->table?->schema?->schema_name),
            ExportColumn::make('project')
                ->label('Project')
                ->state(fn() => null),
            ExportColumn::make('repository_name')
                ->label('Repository Name')
                ->state(fn() => null),
            ExportColumn::make('sbl_user_name')
                ->label('User Name'),
            ExportColumn::make('alias')
                ->label('Alias')
                ->state(fn() => null),
            ExportColumn::make('type')
                ->label('Type')
                ->state(fn() => null),
            ExportColumn::make('pr_key')
                ->label('Primary Key'),
            ExportColumn::make('user_key_sequence')
                ->label('User Key Sequence')
                ->state(fn() => null),
            ExportColumn::make('nullable')
                ->label('Nullable')
                ->formatStateUsing(fn($state) => self::formatNullableBooleanToYn($state)),
            ExportColumn::make('translate')
                ->label('Translate')
                ->state(fn() => null),
            ExportColumn::make('translation_table_name')
                ->label('Translation Table Name')
                ->state(fn() => null),
            ExportColumn::make('required')
                ->label('Required')
                ->state(fn() => null),
            ExportColumn::make('ref_tab_name')
                ->label('Foreign Key Table')
                ->state(fn(AnonymousSiebelColumn $record) => $record->ref_tab_name ?: $record->related_columns_raw),
            ExportColumn::make('use_function_key')
                ->label('Use Function Key')
                ->state(fn() => null),
            ExportColumn::make('dataType.data_type_name')
                ->label('Physical Type'),
            ExportColumn::make('data_length')
                ->label('Length'),
            ExportColumn::make('data_precision')
                ->label('Precision'),
            ExportColumn::make('data_scale')
                ->label('Scale'),
            ExportColumn::make('default')
                ->label('Default')
                ->state(fn() => null),
            ExportColumn::make('lov_type')
                ->label('LOV Type')
                ->state(fn() => null),
            ExportColumn::make('lov_bounded')
                ->label('LOV Bounded')
                ->state(fn() => null),
            ExportColumn::make('sequence_object')
                ->label('Sequence Object')
                ->state(fn() => null),
            ExportColumn::make('force_case')
                ->label('Force Case')
                ->state(fn() => null),
            ExportColumn::make('cascade_clear')
                ->label('Cascade Clear')
                ->state(fn() => null),
            ExportColumn::make('primary_child_column')
                ->label('Primary Child Column')
                ->state(fn() => null),
            ExportColumn::make('primary_inter_table')
                ->label('Primary Inter Table')
                ->state(fn() => null),
            ExportColumn::make('transaction_log_code')
                ->label('Transaction Log Code')
                ->state(fn() => null),
            ExportColumn::make('valid_condition')
                ->label('Valid Condition')
                ->state(fn() => null),
            ExportColumn::make('denormalization_path')
                ->label('Denormalization Path')
                ->state(fn() => null),
            ExportColumn::make('primary_child_table')
                ->label('Primary Child Table')
                ->state(fn() => null),
            ExportColumn::make('primary_child_column_2')
                ->label('Primary Child Column')
                ->state(fn() => null),
            ExportColumn::make('status')
                ->label('Status')
                ->state(fn() => null),
            ExportColumn::make('primary_child_join_column')
                ->label('Primary Child Join Column')
                ->state(fn() => null),
            ExportColumn::make('primary_join_column')
                ->label('Primary Join Column')
                ->state(fn() => null),
            ExportColumn::make('eim_processing_column_flag')
                ->label('EIM Processing Column Flag')
                ->state(fn() => null),
            ExportColumn::make('fk_column_1_m_rel_name')
                ->label('FK Column 1:M Rel Name')
                ->state(fn() => null),
            ExportColumn::make('fk_column_m_1_rel_name')
                ->label('FK Column M:1 Rel Name')
                ->state(fn() => null),
            ExportColumn::make('sequence')
                ->label('Sequence')
                ->state(fn() => null),
            ExportColumn::make('ascii_only')
                ->label('ASCII Only')
                ->state(fn() => null),
            ExportColumn::make('inactive')
                ->label('Inactive')
                ->state(fn() => null),
            ExportColumn::make('column_comment')
                ->label('Comments'),
            ExportColumn::make('no_match_value')
                ->label('No Match Value')
                ->state(fn() => null),
            ExportColumn::make('system_field_mapping')
                ->label('System Field Mapping')
                ->state(fn() => null),
            ExportColumn::make('partition_sequence_number')
                ->label('Partition Sequence Number')
                ->state(fn() => null),
            ExportColumn::make('default_insensitivity')
                ->label('Default Insensitivity')
                ->state(fn() => null),
            ExportColumn::make('computation_expression')
                ->label('Computation Expression')
                ->state(fn() => null),
            ExportColumn::make('encrypt_key_specifier')
                ->label('Encrypt Key Specifier')
                ->state(fn() => null),
            ExportColumn::make('module')
                ->label('Module')
                ->state(fn() => null),
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
        ];
    }
}
