# Siebel Field Generation

This document outlines the steps required to generate database seeds for Siebel Fields with their appropriate configurations and relationships using the scripts in this folder.

## Prerequisites

-   CSV input files (review examples folder for csv structure):
    -   `ICM_FIELDS.csv`: Original ICM field definitions with `calculated_value` expressions exported from the original Siebel DB.
    -   `s_buscomp.csv`: Original Siebel Business Components exported from the original Siebel DB.
    -   `siebel_business_components.csv`: Klamm database dump of existing Siebel business components.
    -   `siebel_tables.csv`: Klamm database dump of existing Siebel tables.
    -   `siebel_values.csv`: Klamm database dump of existing Siebel (LOV) values.

## Process Steps

### 1. Extract and Analyze Expressions

```
python 1-expression-extraction.py
```

This script:

-   Uses the `ICM_FIELDS.csv` file as input
-   Analyzes the calculated value expressions in each field
-   Generates `ICM_FIELDS_ANALYZED.csv` containing the interpreted expressions. This generates additional columns (see examples folder):
    -   field_references
    -   list_of_values
    -   functions_used
    -   operators_used
    -   constants
    -   referenced_business_components
    -   expression_type
    -   field_reference_count

### 2. Generate Siebel Fields Seeder

```
python 2-generate_siebel_fields_seeder.py ICM_FIELDS_ANALYZED.csv siebel_business_components.csv SiebelFieldsTableSeeder.php
```

This script:

-   Takes the ICM fields and Siebel Business Components exported from the original Siebel DB, and the existing klamm business components data and siebel table data.
-   Maps fields to appropriate business components by matching unique names, and replacing them with the business component id.
-   Generates a PHP seeder file (`SiebelFieldsTableSeeder.php`) that will create the Siebel fields in the database with the appropriate relationships to business components and tables.
-   Also generates a (`SiebelMissingBusinessComponentsSeeder.php`) with relevant business components if they are not found in the original file dump. This matches entries with the exported Siebel Business Components from the original Siebel DB to create entries in the same format as existing Business Components. If created, this file should be seeded to the database prior to (`SiebelFieldsTableSeeder.php`).

#### CSV Format Requirements

##### ICM_FIELDS_ANALYZED.csv

The field template CSV should have the following columns:

-   `id`: (Optional) Original field ID
-   `field_name`: The name of the field
-   `business_component`: The name of the business component this field belongs to
-   `table`: The table name associated with the field
-   `table_column`: The table column name
-   `multi_value_link`: Multi-value link identifier
-   `multi_value_link_field`: Multi-value link field
-   `join`: Join relationship name
-   `join_column`: Join column name
-   `calculated_value`: Calculated value expression

##### siebel_business_components.csv

The business components CSV should contain at least:

-   `id`: The ID of the business component
-   `name`: The name of the business component
-   `table_id`: The table ID associated with the business component

#### Notes

-   Fields with missing business components will be skipped
-   For large datasets, multiple seeder files will be created automatically. A master seeder will be generated that calls all the individual seeders.

### 3. Generate Siebel Field Relationships

```
python 3-generate_siebel_field_relationships_seeder.py ICM_FIELDS_ANALYZED.csv siebel_values.csv
```

This script:

-   Establishes relationships between the newly created Siebel fields and existing values, which includes other Siebel fields and Siebel (LOV) values
-   Uses the analyzed fields and existing Siebel values to create the appropriate relationships
-   Generates (`SiebelFieldReferencesSeeder.php`) and (`SiebelFieldValuesSeeder.php`) files if there are applicable relationships.

## Execution Order

Follow these steps in sequence to properly generate all Siebel fields and their relationships:

1. Run expression extraction to analyze ICM fields
2. Generate the Siebel fields seeder(s)
3. Generate the field relationships

After generating all seeds, ensure that you seed the database in the appropriate order:

1. Missing Business Components
2. Siebel Fields
3. Siebel Field Relationships

## Notes

-   The scripts will generate seeders with auto-incrementing IDs
-   Ensure all input CSV files are properly formatted before running the scripts
-   The generated seeder files should be reviewed before execution
