<?php

namespace App\Constants\Fodig\Anonymizer;

final class SiebelMetadata
{
    public const REQUIRED_HEADER_COLUMNS = [
        'DATABASE_NAME',
        'SCHEMA_NAME',
        'OBJECT_TYPE',
        'TABLE_NAME',
        'COLUMN_NAME',
        'COLUMN_ID',
        'DATA_TYPE',
    ];

    public const OPTIONAL_HEADER_COLUMNS = [
        'DATA_LENGTH',
        'DATA_PRECISION',
        'DATA_SCALE',
        'NULLABLE',
        'CHAR_LENGTH',
        'TABLE_COMMENT',
        'COLUMN_COMMENT',
        'RELATED_COLUMNS',
    ];

    public const TEMP_HEADER_COLUMNS = [
        'DB_INSTANCE',
        'OWNER',
        'QUALFIELD',
        'COLUMN_ID',
        'TABLE_NAME',
        'COLUMN_NAME',
        'ANON_RULE',
        'ANON_NOTE',
        'PR_KEY',
        'REF_TAB_NAME',
        'NUM_DISTINCT',
        'NUM_NOT_NULL',
        'NUM_NULLS',
        'NUM_ROWS',
        'DATA_TYPE',
        'DATA_LENGTH',
        'DATA_PRECISION',
        'DATA_SCALE',
        'COMMENTS',
        'SBL_USER_NAME',
        'SBL_DESC_TEXT',
        'NULLABLE',
    ];

    public const IMPORT_DIRECTORY = 'anonymous-siebel/imports';

    // Defaults applied when transforming Siebel-column CSVs without explicit scope
    public const DEFAULT_DATABASE = 'Siebel';
    public const DEFAULT_SCHEMA = 'Siebel';

    // Truncate target used by the UI reset action.
    public const TRUNCATE_TABLES_CSV = 'anonymous_siebel_column_dependencies, anonymous_siebel_columns, anonymous_siebel_tables, anonymous_siebel_schemas, anonymous_siebel_databases, anonymous_siebel_data_types, anonymous_siebel_stagings, anonymization_uploads';
}
