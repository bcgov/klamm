# Faker Anonymization Packages

This directory contains Python-generated Oracle PL/SQL packages with synthetic data for data anonymization.

## Overview

The `generate_faker_anonymization_packages.py` script generates:

1. **Oracle PL/SQL Packages** - Self-contained packages with 10,000+ synthetic data entries each
2. **JSON Data Exports** - Portable data files for use outside Oracle
3. **Laravel Seeders** - PHP seeders for Klamm anonymization methods

## Data Sets Generated

| Dataset               | Count   | Max Length | Oracle Types                    | Description                    |
| --------------------- | ------- | ---------- | ------------------------------- | ------------------------------ |
| First Names           | 10,000+ | 50         | VARCHAR2, CHAR, NVARCHAR2       | Synthetic first names          |
| Last Names            | 10,000+ | 50         | VARCHAR2, CHAR, NVARCHAR2       | Synthetic last names           |
| Full Names            | 10,000+ | 100        | VARCHAR2, CHAR, NVARCHAR2       | Combined first + last          |
| Email Addresses       | 10,000+ | 100        | VARCHAR2, CHAR, NVARCHAR2       | Safe @example.\* domains       |
| Phone Numbers         | 10,000+ | 40         | VARCHAR2, CHAR, NVARCHAR2       | 555/toll-free exchanges        |
| Street Addresses      | 10,000+ | 200        | VARCHAR2, CHAR, NVARCHAR2, CLOB | Full street addresses          |
| Cities                | 5,000+  | 50         | VARCHAR2, CHAR, NVARCHAR2       | City names                     |
| States/Provinces      | ~70     | 30         | VARCHAR2, CHAR, NVARCHAR2       | US states + Canadian provinces |
| Postal Codes          | 10,000+ | 30         | VARCHAR2, CHAR, NVARCHAR2       | US ZIP + Canadian postal       |
| Company Names         | 5,000+  | 100        | VARCHAR2, CHAR, NVARCHAR2       | Organization names             |
| Job Titles            | 2,000+  | 75         | VARCHAR2, CHAR, NVARCHAR2       | Professional titles            |
| Credit Cards (Masked) | 10,000+ | 30         | VARCHAR2, CHAR, NVARCHAR2       | Non-functional masked CC       |
| SIN Surrogates        | 10,000+ | 15         | VARCHAR2, CHAR, NVARCHAR2       | Invalid SIN-format strings     |
| Account Numbers       | 10,000+ | 30         | VARCHAR2, CHAR, NVARCHAR2       | Synthetic account refs         |
| Usernames             | 10,000+ | 50         | VARCHAR2, CHAR, NVARCHAR2       | Login-style usernames          |
| Ages                  | 82      | 3          | NUMBER, VARCHAR2                | Valid ages 18-99               |
| Status Values         | ~20     | 30         | VARCHAR2, CHAR, NVARCHAR2       | Siebel status values           |
| Priority Values       | ~9      | 20         | VARCHAR2, CHAR, NVARCHAR2       | Siebel priority values         |
| Type Values           | ~13     | 30         | VARCHAR2, CHAR, NVARCHAR2       | Siebel entity types            |
| Comments              | 5,000+  | 255        | VARCHAR2, CHAR, NVARCHAR2, CLOB | Lorem ipsum text               |

## Usage

### Prerequisites

```bash
pip install faker
```

### Generate Packages

```bash
# Default: 10,000 entries per set
python scripts/oracle/generate_faker_anonymization_packages.py

# Custom count
python scripts/oracle/generate_faker_anonymization_packages.py --count 50000

# Custom seed for reproducibility
python scripts/oracle/generate_faker_anonymization_packages.py --seed 12345

# Custom output directory
python scripts/oracle/generate_faker_anonymization_packages.py --output-dir /path/to/output

# Custom schema name
python scripts/oracle/generate_faker_anonymization_packages.py --schema MY_ANON_SCHEMA
```

### Install Oracle Packages

```bash
# Connect to Oracle as a privileged user
sqlplus / as sysdba

# Run the master installation script
@database/seeders/anonymization/packages/ANON_DATA_INSTALL_ALL.sql
```

Or install individual packages:

```bash
@database/seeders/anonymization/packages/packages/anon_first_names_spec.sql
@database/seeders/anonymization/packages/packages/anon_first_names_body.sql
```

### Seed Laravel Methods

```bash
# Run the Faker lookup method seeder
sail artisan db:seed --class=AnonymizationFakerLookupMethodSeeder

# Or run all anonymization seeders
sail artisan db:seed --class=AnonymizationMethodSeeder
```

## Oracle Package Functions

Each generated package provides these functions:

```sql
-- Deterministic lookup (same seed = same result)
ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES(
    p_seed    IN VARCHAR2,      -- Seed string for deterministic selection
    p_max_len IN NUMBER DEFAULT 50  -- Maximum return length
) RETURN VARCHAR2 DETERMINISTIC;

-- Random lookup (different each call)
ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES_RANDOM(
    p_max_len IN NUMBER DEFAULT 50
) RETURN VARCHAR2;

-- Index-based lookup (1 to N)
ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES_BY_INDEX(
    p_index   IN NUMBER,
    p_max_len IN NUMBER DEFAULT 50
) RETURN VARCHAR2 DETERMINISTIC;

-- Get total entry count
ANON_DATA.PKG_ANON_FIRST_NAMES.GET_COUNT RETURN NUMBER DETERMINISTIC;
```

## Data Masking Methods

The generated Laravel seeder creates anonymization methods for:

### Deterministic Lookup Methods

-   Consistent results for the same seed across runs
-   Uses `{{SEED_EXPR}}` placeholder for row-level determinism
-   Uses `{{JOB_SEED_LITERAL}}` for job-level reproducibility

### Random Lookup Methods

-   Different results each execution
-   No seed required
-   Good for non-reproducible test data

### Siebel-Specific Methods

-   Status values (Active, Inactive, Pending, etc.)
-   Priority values (1-ASAP, 2-High, 3-Medium, 4-Low)
-   Type values (Individual, Organization, etc.)

### Data Type-Specific Methods

| Data Type             | Recommended Methods                        |
| --------------------- | ------------------------------------------ |
| VARCHAR2/CHAR (names) | Faker First/Last/Full Name                 |
| VARCHAR2 (email)      | Faker Email                                |
| VARCHAR2 (phone)      | Faker Phone Number                         |
| VARCHAR2 (address)    | Faker Street Address, City, State, Postal  |
| VARCHAR2 (codes)      | VARCHAR2 Char Substitute                   |
| NUMBER (age)          | Faker Age                                  |
| NUMBER (amounts)      | Numeric Perturbation                       |
| DATE                  | Date Shift (±30/90/365 days)               |
| TIMESTAMP             | Timestamp Shift, Time Component Shuffle    |
| CLOB                  | CLOB Comment Replacement, CLOB Hard Redact |

## Safety Features

### Email Addresses

All generated emails use RFC 2606 reserved domains:

-   `@example.com`
-   `@example.org`
-   `@example.net`

### Phone Numbers

Uses reserved/fictional exchanges:

-   555-xxxx (reserved for fiction)
-   800/888/877/866 toll-free prefixes
-   900 premium prefixes

### SIN Surrogates

Uses invalid area numbers (900-999) that are never assigned by SSA.

### Credit Cards

Masked format with visible last 4 digits only; fails Luhn check.

## Column Length Handling

Methods use the `{{COLUMN_MAX_LEN_EXPR}}` placeholder which resolves to the target column's maximum length from metadata. This ensures:

-   No buffer overflows
-   Proper truncation for shorter columns
-   Full utilization of available length

## Nullable Column Handling

For nullable columns, use the "Nullable-Safe" method variants which:

-   Preserve NULL values (don't convert to empty string)
-   Use Oracle's `NVL2()` for conditional updates
-   Don't require WHERE clauses

## Reproducibility

For reproducible anonymization across multiple runs:

1. Use the same job seed (`{{JOB_SEED_LITERAL}}`)
2. Use deterministic (not random) methods
3. Ensure `{{SEED_EXPR}}` resolves to a stable row identifier

Example seed expression configuration:

-   ROW_ID (Siebel primary key)
-   INTEGRATION_ID (cross-system identifier)
-   Composite: `ROW_ID || '|' || CREATED`

## File Structure

```
database/seeders/anonymization/packages/
├── ANON_DATA_INSTALL_ALL.sql       # Master installation script
├── data/
│   ├── manifest.json               # Data set metadata
│   ├── anon_first_names.json       # First names data
│   ├── anon_last_names.json        # Last names data
│   └── ...                         # Other data files
└── packages/
    ├── anon_first_names_spec.sql   # Package specification
    ├── anon_first_names_body.sql   # Package body with data
    └── ...                         # Other packages
```

## Extending

To add new data sets:

1. Add a generator method to `FakerDataGenerator` class
2. Add the dataset to `generate_all()` method
3. Add corresponding method(s) to `AnonymizationFakerLookupMethodSeeder`
4. Regenerate packages and re-run seeders

## Security Considerations

-   Generated data is synthetic and contains no real PII
-   Packages should be installed in a dedicated schema with limited access
-   In production Oracle environments, consider:
    -   Schema-level access controls
    -   Separate tablespace for package data
    -   Audit logging on package function calls
