# Klamm Anonymization (Quick Workflow)

Klamm builds anonymization SQL from metadata. It does not run SQL in production.

## Workflow

1. Import metadata
    - FODIG panel → Anonymizer → Siebel Databases → Import.
    - Upload the metadata export (structure only).
2. (Optional) Add packages
    - Anonymizer → Packages.
    - Store reusable SQL/PLSQL helpers if your methods need them.
3. Create methods
    - Anonymizer → Methods.
    - Add a name, a short description, and the SQL block.
    - Use placeholders like {{TABLE}} and {{COLUMN}}.
    - Attach any packages required by the method.
4. Assign methods to columns
    - Anonymizer → Siebel Columns.
    - Mark columns that need masking and link one or more methods.
5. Create a job
    - Anonymizer → Jobs → Create.
    - Pick job type (full or partial) and output format (SQL).
    - Choose scope (databases, schemas, tables, or specific columns).
    - Save to generate SQL.
6. Review and execute
    - Open the job and download the SQL.
    - Run it in Oracle using your normal change-control process.
    - Update job status and run metadata if you track it.

## Notes

-   SQL generation is queued. In local, run a queue worker if the script does not update.
-   Jobs are for script generation only; execution is manual.

## Still to do

-   Add automated SQL validation/linting for method templates.
-   Add export permissions and download auditing.
-   Implement parquet output.
