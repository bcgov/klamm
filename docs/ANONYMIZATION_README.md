# Klamm Anonymization Workflow Guide

This interface within Klamm is a metadata-driven anonymization script generator for Siebel and Oracle environments.

Important: Klamm does not execute masking SQL in production. It builds reviewed SQL scripts that your DBA/change-control process executes manually.

This guide explains the full lifecycle:

1. Metadata upload and synchronization.
2. Marking columns for anonymization.
3. Designing and managing rules, methods, and packages.
4. Creating and maintaining anonymization jobs.
5. Generating and downloading SQL for execution in Oracle.

## What Klamm Stores and What It Does Not

- Klamm stores metadata only: databases, schemas, tables, columns, data types, and relationships.
    - this metadata can be from any oracle siebel instance, and the import tool is flexible to support other sources in the future.
- Klamm stores anonymization design metadata: required flags, comments, tags, rules, methods, package dependencies, and job definitions.
- Klamm does not ingest production data rows.
    - **Important**: No production data is ever uploaded to or stored in Klamm. The anonymization design is based on metadata and rules, not actual data values.
- Klamm does not run the anonymization SQL against your database, but only creates scripts for manual execution.
    - This preserves the air-gapped nature of production data and allows DBAs to review and control execution.
- Logs are generated for anonymization activities, but they do not contain production data values. Logs focus on process status, errors, and metadata-level information. Changes to metadata and job definitions are tracked, but not the actual data being anonymized.

## Prerequisites

- You have access to the FODIG panel and Anonymizer resources.
- Queue workers are running, especially the dedicated `anonymization` queue worker.
    - Local example: run a worker with `--queue=anonymization`.
- Your Oracle helper packages (if used) are installed and executable in the target environment. The SQL generation process will validate package dependencies and install if you are logged in as the src user/schema and have the necessary permissions.
- Optional environment settings in `.env`:
    - `ANONYMIZATION_UPLOAD_RETENTION_DAYS` (default `30`)
    - `ANONYMIZATION_STAGING_RETENTION_DAYS` (default `7`)
    - `ANONYMIZATION_PACKAGE_OWNER` (default `ANON_DATA`)

## High-Level End-to-End Process

1. Import metadata CSV through `Anonymizer > Metadata Uploads > Import`.
2. Review imported columns in `Anonymizer > Siebel Columns`.
3. Create packages in `Anonymizer > Packages` (optional, but recommended for reusable PL/SQL).
4. Create methods in `Anonymizer > Methods` and attach package dependencies.
5. Create rules in `Anonymizer > Rules` and assign methods (default + optional strategy variants).
6. Assign rules to columns and mark `anonymization_required` where needed.
7. Create job in `Anonymizer > Jobs` with scope and generation options.
8. Save/regenerate SQL, review readiness, then download SQL from the job view.
9. Review and validate SQL, then execute manually in Oracle through your controlled deployment process.

## Metadata Upload: Full Details

### UI Path

- `FODIG > Anonymizer > Metadata Uploads > Import`

### Import Options

- `Import type`
    - `partial`: upserts only rows in CSV; no delete reconciliation.
        - Missing rows in scoped catalog are left unchanged.
        - Useful for incremental updates or when source CSV is a filtered subset.
    - `full`: upserts CSV rows and soft-deletes missing rows in scoped catalog.
        - Missing rows in scoped catalog are marked deleted (soft delete).
        - Useful for authoritative syncs where CSV represents the full scope.
- `Create change tickets after import`
    - If enabled (default), Klamm queues change ticket analysis after successful import.
- `Override anonymization rules from CSV` (admin only)
    - Controls whether `ANON_RULE` and `ANON_NOTE` from CSV are applied to column anonymization settings.
    - See behavior details below.

### Accepted Upload Formats

Klamm detects and processes these header styles:

1. Canonical metadata format. (Standard format generated through use of Klamm's included Oracle export template at `/scripts/ScriptTemplates/anonymization-relationship-export.sql`).
2. Temp round-trip format. (This is the format used for export from the `Siebel Columns` page and re-import after spreadsheet editing).

- There is a difference in header requirements and validation rules between these formats, so be sure to use the correct format for your use case. This is due to what is directly stored in the db, versus the additional fields introduced by Klamm for anonymization management.

CSV/TXT files are accepted. Import upload size limit is configured in UI as `500 MB`.

### Canonical Required Headers

- `DATABASE_NAME`
- `SCHEMA_NAME`
- `OBJECT_TYPE`
- `TABLE_NAME`
- `COLUMN_NAME`
- `COLUMN_ID`
- `DATA_TYPE`

### Canonical Optional Headers

- `DATA_LENGTH`
- `DATA_PRECISION`
- `DATA_SCALE`
- `NULLABLE`
- `CHAR_LENGTH`
- `TABLE_COMMENT`
- `COLUMN_COMMENT`
- `RELATED_COLUMNS`

### Temp Round-Trip Headers

- `DB_INSTANCE`
- `OWNER`
- `QUALFIELD`
- `COLUMN_ID`
- `TABLE_NAME`
- `COLUMN_NAME`
- `ANON_RULE`
- `ANON_NOTE`
- `PR_KEY`
- `REF_TAB_NAME`
- `NUM_DISTINCT`
- `NUM_NOT_NULL`
- `NUM_NULLS`
- `NUM_ROWS`
- `DATA_TYPE`
- `DATA_LENGTH`
- `DATA_PRECISION`
- `DATA_SCALE`
- `COMMENTS`
- `SBL_USER_NAME`
- `SBL_DESC_TEXT`
- `NULLABLE`

### Header Validation Rules

- Canonical and temp formats validate required columns and fail on unknown headers.
- Siebel columns-style format is recognized heuristically and transformed into canonical rows.

### Import Runtime Behavior

- Upload is queued (`status=queued`) and processed asynchronously on queue `anonymization`.
- Processing phases include staging, metadata reconcile, column upsert, optional anonymization-rule sync (if override selected as admin), deletion reconcile (full import), and relationship rebuild.
- Failed imports can be resumed or restarted from upload actions.
    - `Resume` attempts to reuse staged rows when possible.
    - `Restart` clears staging and reprocesses from file.

### Full vs Partial Import Effects

- `partial` import:
    - Updates touched records only.
    - Does not soft-delete missing columns.
- `full` import:
    - Updates touched records.
    - Soft-deletes columns missing from the effective scope.

### Scope and Defaults During Transform

- For Siebel-columns and temp transformations without explicit scope values, defaults are used:
    - Database: `Siebel`
    - Schema: `Siebel`

### CSV Validation Warning Tickets

- Row-level validation problems create warning artifacts:
    - Upload warnings in metadata upload record.
    - `.errors.json` report file.
    - Validation change ticket (if not already open for same upload context).

## Anonymization Design Model

Klamm composes SQL through three reusable entities:

1. Packages: reusable SQL/PLSQL building blocks.
2. Methods: masking logic templates (with placeholders).
3. Rules: strategy-aware mapping from column to method choice.

Columns are then linked to rules, and jobs use column scope plus job strategy to resolve final method selection.

## Packages

### Path

- `Anonymizer > Packages`

### Core Fields

- `name` (display)
- `handle` (internal unique reference)
- `package_name` (database package name)
- `database_platform` (Oracle/Siebel, PostgreSQL, MySQL, SQL Server, Generic)
- `summary`
- SQL blocks:
    - `install_sql`
    - `package_spec_sql`
    - `package_body_sql`

### Behavior

- Methods can depend on one or more packages, or be completely standalone.
- Job SQL includes package SQL blocks before masking statements.
- Package versions can be created (`New version`) without auto-rewiring existing method associations.

## Methods

### Path

- `Anonymizer > Methods`

### Core Fields

- `name` (unique)
- `categories`
- `description`
- `what_it_does`
- `how_it_works`
- `sql_block`
- Seed contract flags:
    - `emits_seed`
    - `requires_seed`
    - `supports_composite_seed`
    - `seed_notes`
- `packages` relationship

**Important Note Regarding Seed Contract Support:**
Methods that emit or require seeds have additional expectations around how they are used in rules and jobs. Review method metadata and seed contract flags carefully when designing your anonymization logic, especially if you plan to use seed-based techniques for referential integrity or realistic value generation. These seeds maintain relationships across columns and tables, as well as supporting deterministic anonymization across runs.

### Placeholder Support (common)

Method SQL can use placeholders resolved during generation, including:

- `{{TABLE}}`
- `{{TABLE_NAME}}`
- `{{SCHEMA}}`
- `{{COLUMN}}`
- `{{ALIAS}}`
- `{{SEED_EXPR}}`
- `{{SEED_MAP_LOOKUP}}`
- `{{JOB_SEED_LITERAL}}`

Note: methods are primarily resolved through rules. Direct column-method links are allowed, but should be avoided unless necessary for one-offs/specific cases.

Review seeded method examples for more complex placeholder usage patterns, especially around seed contract support.

## Rules

### Path

- `Anonymizer > Rules`

### Core Fields

- `name`
- `description`
- method assignments:
    - one default method (`is_default=true`)
    - optional strategy-labeled alternatives (`strategy`)

### Resolution Logic

When generating SQL for a column with a rule:

1. If job has `strategy`, the job creator will try matching rule method with that strategy.
2. Otherwise it will use the rule default method.
3. If no rule method resolves, fallback to direct method, if one is attached.

## Column Designation and Annotation (Creating the "Anonymization Catalog")

### Path

- `Anonymizer > Siebel Columns`

### Key Column-Level Controls

- `anonymization_required`
- `anonymization_requirement_reviewed`
- `metadata_comment`
- `anonymization_rule_id`
- tags (`Column tags`)
- parent/child dependencies (relationship managers)
- table-level target relation override (`table` vs `view`)

### Recommended Practice

1. Assign a rule to each sensitive column.
2. Mark required columns with `anonymization_required=true`.
3. Mark reviewed columns once validated.
4. Maintain dependency links for deterministic FK-safe generation. (These should usually be handled in the source metadata, but can be adjusted here as needed).

## ANON_RULE / ANON_NOTE Import Behavior

When importing metadata from CSV, the `ANON_RULE` and `ANON_NOTE` fields can optionally override existing anonymization settings for matched columns. This behavior is controlled by the `Override anonymization rules from CSV` option during import and is intended to allow bulk updates to anonymization design through spreadsheet editing. This is only available to admins to avoid overwrite risks for non-admin users who may be importing metadata without intending to change anonymization settings.

### Without Override (`Override anonymization rules from CSV = false`)

- Staging captures `ANON_RULE` / `ANON_NOTE` values.
- Column anonymization settings are not synced from these fields.
- Existing required/reviewed/rule/comment settings remain unchanged.

### With Override (`true`, admin mode)

For matched columns, Klamm applies `ANON_RULE` and `ANON_NOTE` to anonymization settings.

- `ANON_RULE = no_change` (case-insensitive):
    - `anonymization_required = false`
    - `anonymization_requirement_reviewed = true`
    - rule mapping cleared
- `ANON_RULE = <existing rule name>`:
    - `anonymization_required = true`
    - `anonymization_requirement_reviewed = true`
    - rule mapped to named rule
- `ANON_RULE` blank/null:
    - clears required/reviewed/rule mapping for matched row
- `ANON_NOTE` maps to `metadata_comment` (blank clears comment)
- Unknown rule names create upload-scoped change tickets.

## CSV Round-Trip Details (Spreadsheet Workflow)

Klamm supports exporting the current filtered column set, editing in a spreadsheet, then re-importing, in order to manage anonymization without being 100% dependendent on the Klamm interface and Klamm uptime.

### Export Path

- `Anonymizer > Siebel Columns > Export > Export currently filtered set`

### Export Columns (temp format)

- `DB_INSTANCE`
- `OWNER`
- `QUALFIELD`
- `COLUMN_ID`
- `TABLE_NAME`
- `COLUMN_NAME`
- `ANON_RULE`
- `ANON_NOTE`
- `PR_KEY`
- `REF_TAB_NAME`
- `NUM_DISTINCT`
- `NUM_NOT_NULL`
- `NUM_NULLS`
- `NUM_ROWS`
- `DATA_TYPE`
- `DATA_LENGTH`
- `DATA_PRECISION`
- `DATA_SCALE`
- `COMMENTS`
- `SBL_USER_NAME`
- `SBL_DESC_TEXT`
- `NULLABLE`

### Exported Anonymization Values

- `ANON_RULE`
    - required + rule assigned: exports rule name
    - reviewed + not required: exports `no_change`
    - otherwise: blank
- `ANON_NOTE`
    - exports `metadata_comment`

### Import Notes for Round-Trip Files

- Temp headers are accepted directly during upload.
- `ANON_RULE` and `ANON_NOTE` only affect anonymization settings when `Override anonymization rules from CSV` is enabled.
- With override disabled, existing anonymization settings are preserved regardless of CSV values.

## Job Creation and SQL Generation

### Path

- `Anonymizer > Jobs`

### Core Job Fields

- `name`
- `job_type` (`full` or `partial`)
- `output_format` (`sql`)
- `status`
- `strategy` (optional; drives rule strategy selection)

### Scope Selection

- Select databases, schemas, tables.
- Use column builder modes:
    - `Manual` (default): manually select columns from dropdown
    - `Flagged columns` (only show columns marked `anonymization_required=true`)
    - `Has methods` (only show columns with any method assigned)
    - `Has rules` (only show columns with rules assigned)
    - `Missing method`
    - `Entire scope`

`Entire scope` mode avoids loading huge manual column selections and defers bulk selection sync to save-time.

Note on output format: `output_format` currently only supports `sql`. Parquet support is planned for future implementation and may require additional design around how method SQL blocks are composed and applied in a non-SQL context.

### Execution Options

- `target_schema`
- `target_table_mode`:
    - `prefixed` (safe working copies)
    - `anon`
    - `exact`
- `target_relation_kind`:
    - `table`
    - `view`
- `seed_store_mode`:
    - `temporary`
    - `persistent`
- `seed_store_schema`
- `seed_store_prefix`
- `seed_map_hygiene_mode`:
    - `none`
    - `commented`
    - `execute`
- `job_seed`
- `pre_mask_sql`
- `post_mask_sql`

### Partial vs Full Job SQL Behavior

- `full` job: builds full clone/masking flow for in-scope selected columns.
- `partial` job: allows nulling behavior for unselected columns in generated flows where applicable.

### Generation Pipeline

- Saving/updating job queues `GenerateAnonymizationJobSql` on `anonymization` queue.
- Large jobs use chunked SQL generation:
    - `GenerateAnonymizationJobSqlChunk`
    - `AssembleAnonymizationJobSqlChunks`
    - `GenerateAnonymizationJobConstraintsSql`
- SQL preview is truncated for very large scripts; full SQL downloaded via `Download SQL` action.

### Readiness Checks

Job readiness reports identify:

- required columns missing methods
- required columns not reviewed
- ambiguous multi-method assignments
- missing dependency columns
- urgent change tickets intersecting job scope

Use `Download Readiness Report` on create/edit pages for markdown output.

## SQL Download and Execution

### Download

- Open a job record and click `Download SQL`.
- Route streams SQL in chunks from database to avoid memory spikes.

### Execution

- Execute manually in Oracle per your normal change process.
- You will need to first run the script in the source schema to update permissions for the target schema's ability to copy, then switch to target schema for the anonymization execution.
    - There are no concerns with running the script in the source schema as it only updates metadata and permissions, not actual data. The anonymization SQL blocks are designed to be schema-agnostic and will apply to the target schema's tables/columns as specified in the job design.
- Validate resulting anonymized targets, constraints, and package dependencies.

## Upload and Staging Retention

- Upload files are retained until `retention_until` then purged.
- Staging rows are pruned by age and status.
- Scheduled command:
    - `anonymization:purge-uploads`
    - runs every 30 minutes via `routes/console.php`.

## Operational Checklist

1. Ensure `anonymization` queue worker is running.
2. Import metadata and resolve validation warnings.
3. Create/verify packages, methods, and rules.
4. Assign rules and required/reviewed statuses on columns.
5. Build job scope and options.
6. Check readiness report.
7. Regenerate SQL and download final script.
8. Execute in Oracle via controlled deployment.

### Current Limitations / Roadmap Notes

- `parquet` job creation and SQL generation is not yet implemented.
- SQL linting/validation for method templates can be expanded.
- Export auditing and permission hardening can be expanded further.
- More complex placeholder support and method resolution patterns can be added as needed.
- additional testing post-generation can be added to validate SQL structure and dependencies before download.
