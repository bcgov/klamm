#!/usr/bin/env python3

"""Generate Oracle PL/SQL anonymization packages using Faker.

This script generates discrete anonymization data packages containing:
- First names (10,000+ entries)
- Last names (10,000+ entries)
- Full names (combined)
- Email addresses (10,000+ entries)
- Street addresses (10,000+ entries)
- Cities (5,000+ entries)
- States/Provinces (all US states + Canadian provinces)
- Postal codes (10,000+ entries)
- Phone numbers (10,000+ entries)
- Credit card numbers (masked, 10,000+ entries)
- Ages (valid range 18-99)
- Status values (common Siebel statuses)
- Company names (5,000+ entries)
- Job titles (2,000+ entries)
- sin surrogates (masked, 10,000+ entries)
- Account numbers (10,000+ entries)
- Dates (various ranges)

Output:
- Oracle PL/SQL packages with lookup functions
- SQL installer scripts
- PHP seeder data files

Usage:
    python generate_faker_anonymization_packages.py [--count N] [--output-dir DIR] [--seed S]

Requirements:
    pip install faker

Notes:
- All generated data is synthetic and contains no real PII
- Data is designed for Oracle/Siebel VARCHAR2, NUMBER, DATE column types
- Character lengths are configurable and respect Oracle limits
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import random
import sys
from dataclasses import dataclass, field
from datetime import date, timedelta
from pathlib import Path
from typing import Callable

try:
    # Dynamically import to avoid static analysis/lsp errors in editors where 'faker' is not installed.
    import importlib

    Faker = importlib.import_module("faker").Faker
except Exception:
    print("Error: faker library not installed. Run: pip install faker", file=sys.stderr)
    sys.exit(1)


# ==============================================================================
# Configuration
# ==============================================================================

DEFAULT_COUNT = 10000
DEFAULT_SEED = 42
DEFAULT_OUTPUT_DIR = "database/seeders/anonymization/packages"

# Siebel-standard maximum lengths for common column types
MAX_LENGTHS = {
    "first_name": 50,
    "last_name": 50,
    "full_name": 100,
    "email": 100,
    "phone": 40,
    "street_address": 200,
    "city": 50,
    "state": 30,
    "postal_code": 30,
    "country": 50,
    "company_name": 100,
    "job_title": 75,
    "credit_card": 30,
    "sin_surrogate": 15,
    "account_number": 30,
    "username": 50,
    "comments": 255,
}

# Common Siebel status values
SIEBEL_STATUSES = [
    "Active",
    "Inactive",
    "Pending",
    "Suspended",
    "Approved",
    "Rejected",
    "Draft",
    "Submitted",
    "Completed",
    "Cancelled",
    "Closed",
    "Open",
    "In Progress",
    "On Hold",
    "Expired",
    "Archived",
    "New",
    "Qualified",
    "Unqualified",
    "Converted",
]

# Common Siebel priority values
SIEBEL_PRIORITIES = [
    "1-ASAP",
    "2-High",
    "3-Medium",
    "4-Low",
    "Critical",
    "High",
    "Medium",
    "Low",
    "Urgent",
]

# Common Siebel type values
SIEBEL_TYPES = [
    "Individual",
    "Organization",
    "Household",
    "Partner",
    "Competitor",
    "Press",
    "Analyst",
    "Investor",
    "Vendor",
    "Reseller",
    "Customer",
    "Prospect",
    "Other",
]


# ==============================================================================
# Data Classes
# ==============================================================================


@dataclass
class AnonymizationDataSet:
    """Container for a category of anonymization data."""

    name: str
    handle: str
    description: str
    oracle_data_types: list[str]
    max_length: int
    entries: list[str] = field(default_factory=list)
    nullable_safe: bool = True
    format_pattern: str | None = None


@dataclass
class GeneratedPackage:
    """Container for a generated Oracle package."""

    name: str
    handle: str
    summary: str
    install_sql: str
    spec_sql: str
    body_sql: str
    data_count: int
    max_length: int


# ==============================================================================
# Data Generators
# ==============================================================================


class FakerDataGenerator:
    """Generates anonymization data sets using Faker."""

    def __init__(self, seed: int = DEFAULT_SEED, count: int = DEFAULT_COUNT):
        self.seed = seed
        self.count = count
        self.faker = Faker(["en_US", "en_CA", "en_GB"])
        Faker.seed(seed)
        random.seed(seed)

    def truncate(self, value: str, max_len: int) -> str:
        """Truncate string to max length."""
        if value is None:
            return ""
        return value[:max_len] if len(value) > max_len else value

    def sanitize_oracle(self, value: str) -> str:
        """Escape single quotes for Oracle strings."""
        if value is None:
            return ""
        return value.replace("'", "''")

    def generate_unique_set(
        self,
        generator: Callable[[], str],
        count: int,
        max_len: int,
        min_unique: int | None = None,
    ) -> list[str]:
        """Generate a unique set of values."""
        unique = set()
        attempts = 0
        max_attempts = count * 10

        while len(unique) < count and attempts < max_attempts:
            value = self.truncate(generator(), max_len)
            if value and len(value) >= 2:  # Minimum meaningful length
                unique.add(self.sanitize_oracle(value))
            attempts += 1

        result = sorted(unique)
        if min_unique and len(result) < min_unique:
            print(
                f"Warning: Only generated {len(result)} unique values (wanted {count})"
            )
        return result

    def generate_first_names(self) -> AnonymizationDataSet:
        """Generate unique first names."""
        entries = self.generate_unique_set(
            self.faker.first_name,
            self.count,
            MAX_LENGTHS["first_name"],
        )
        return AnonymizationDataSet(
            name="First Names",
            handle="anon_first_names",
            description="Synthetic first names for PII masking",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["first_name"],
            entries=entries,
        )

    def generate_last_names(self) -> AnonymizationDataSet:
        """Generate unique last names."""
        entries = self.generate_unique_set(
            self.faker.last_name,
            self.count,
            MAX_LENGTHS["last_name"],
        )
        return AnonymizationDataSet(
            name="Last Names",
            handle="anon_last_names",
            description="Synthetic last names for PII masking",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["last_name"],
            entries=entries,
        )

    def generate_full_names(self) -> AnonymizationDataSet:
        """Generate unique full names."""
        entries = self.generate_unique_set(
            self.faker.name,
            self.count,
            MAX_LENGTHS["full_name"],
        )
        return AnonymizationDataSet(
            name="Full Names",
            handle="anon_full_names",
            description="Synthetic full names for PII masking",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["full_name"],
            entries=entries,
        )

    def generate_emails(self) -> AnonymizationDataSet:
        """Generate unique email addresses."""

        def safe_email() -> str:
            # Use safe domains that won't accidentally be real
            local = self.faker.user_name()[:20]
            domain = random.choice(
                [
                    "example.com",
                    "example.org",
                    "example.net",
                    "test.example.com",
                    "demo.example.org",
                ]
            )
            return f"{local}@{domain}"

        entries = self.generate_unique_set(
            safe_email,
            self.count,
            MAX_LENGTHS["email"],
        )
        return AnonymizationDataSet(
            name="Email Addresses",
            handle="anon_emails",
            description="Synthetic email addresses using safe domains",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["email"],
            entries=entries,
            format_pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$",
        )

    def generate_phone_numbers(self) -> AnonymizationDataSet:
        """Generate unique phone numbers in various formats."""

        def safe_phone() -> str:
            # Use 555 exchange (reserved for fiction) or fake area codes
            area = random.choice(["555", "800", "888", "877", "866", "900"])
            exchange = f"{random.randint(100, 999)}"
            subscriber = f"{random.randint(1000, 9999)}"
            fmt = random.choice(
                [
                    f"({area}) {exchange}-{subscriber}",
                    f"{area}-{exchange}-{subscriber}",
                    f"{area}.{exchange}.{subscriber}",
                    f"+1{area}{exchange}{subscriber}",
                    f"1-{area}-{exchange}-{subscriber}",
                ]
            )
            return fmt

        entries = self.generate_unique_set(
            safe_phone,
            self.count,
            MAX_LENGTHS["phone"],
        )
        return AnonymizationDataSet(
            name="Phone Numbers",
            handle="anon_phones",
            description="Synthetic phone numbers using reserved exchanges",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["phone"],
            entries=entries,
        )

    def generate_street_addresses(self) -> AnonymizationDataSet:
        """Generate unique street addresses."""
        entries = self.generate_unique_set(
            self.faker.street_address,
            self.count,
            MAX_LENGTHS["street_address"],
        )
        return AnonymizationDataSet(
            name="Street Addresses",
            handle="anon_addresses",
            description="Synthetic street addresses",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2", "CLOB"],
            max_length=MAX_LENGTHS["street_address"],
            entries=entries,
        )

    def generate_cities(self) -> AnonymizationDataSet:
        """Generate unique city names."""
        entries = self.generate_unique_set(
            self.faker.city,
            min(self.count, 5000),  # Cities are naturally limited
            MAX_LENGTHS["city"],
        )
        return AnonymizationDataSet(
            name="Cities",
            handle="anon_cities",
            description="Synthetic city names",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["city"],
            entries=entries,
        )

    def generate_states(self) -> AnonymizationDataSet:
        """Generate state/province codes and names."""
        states = [
            # US States
            "AL",
            "AK",
            "AZ",
            "AR",
            "CA",
            "CO",
            "CT",
            "DE",
            "FL",
            "GA",
            "HI",
            "ID",
            "IL",
            "IN",
            "IA",
            "KS",
            "KY",
            "LA",
            "ME",
            "MD",
            "MA",
            "MI",
            "MN",
            "MS",
            "MO",
            "MT",
            "NE",
            "NV",
            "NH",
            "NJ",
            "NM",
            "NY",
            "NC",
            "ND",
            "OH",
            "OK",
            "OR",
            "PA",
            "RI",
            "SC",
            "SD",
            "TN",
            "TX",
            "UT",
            "VT",
            "VA",
            "WA",
            "WV",
            "WI",
            "WY",
            "DC",
            "PR",
            "VI",
            "GU",
            "AS",
            "MP",
            # Canadian Provinces
            "AB",
            "BC",
            "MB",
            "NB",
            "NL",
            "NS",
            "NT",
            "NU",
            "ON",
            "PE",
            "QC",
            "SK",
            "YT",
            # Full names for variety
            "Alabama",
            "Alaska",
            "Arizona",
            "California",
            "Colorado",
            "Florida",
            "Georgia",
            "Illinois",
            "New York",
            "Texas",
            "Ontario",
            "Quebec",
            "British Columbia",
            "Alberta",
        ]
        return AnonymizationDataSet(
            name="States/Provinces",
            handle="anon_states",
            description="US states and Canadian provinces",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["state"],
            entries=sorted(set(self.sanitize_oracle(s) for s in states)),
        )

    def generate_postal_codes(self) -> AnonymizationDataSet:
        """Generate unique postal codes."""

        def postal_code() -> str:
            if random.random() < 0.7:  # 70% US ZIP codes
                return f"{random.randint(10000, 99999)}"
            else:  # 30% Canadian postal codes
                letters = "ABCEGHJKLMNPRSTVXY"
                return f"{random.choice(letters)}{random.randint(0,9)}{random.choice(letters)} {random.randint(0,9)}{random.choice(letters)}{random.randint(0,9)}"

        entries = self.generate_unique_set(
            postal_code,
            self.count,
            MAX_LENGTHS["postal_code"],
        )
        return AnonymizationDataSet(
            name="Postal Codes",
            handle="anon_postal_codes",
            description="Synthetic US ZIP and Canadian postal codes",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["postal_code"],
            entries=entries,
        )

    def generate_company_names(self) -> AnonymizationDataSet:
        """Generate unique company names."""
        entries = self.generate_unique_set(
            self.faker.company,
            min(self.count // 2, 5000),
            MAX_LENGTHS["company_name"],
        )
        return AnonymizationDataSet(
            name="Company Names",
            handle="anon_companies",
            description="Synthetic company/organization names",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["company_name"],
            entries=entries,
        )

    def generate_job_titles(self) -> AnonymizationDataSet:
        """Generate unique job titles."""
        entries = self.generate_unique_set(
            self.faker.job,
            min(self.count // 5, 2000),
            MAX_LENGTHS["job_title"],
        )
        return AnonymizationDataSet(
            name="Job Titles",
            handle="anon_job_titles",
            description="Synthetic job titles",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["job_title"],
            entries=entries,
        )

    def generate_credit_card_numbers(self) -> AnonymizationDataSet:
        """Generate masked credit card numbers (not valid)."""

        def masked_cc() -> str:
            # Generate non-valid but realistic-looking masked numbers
            prefix = random.choice(
                ["4", "5", "3", "6"]
            )  # Visa, MC, Amex, Discover pattern
            if prefix == "3":
                masked = f"3XXX-XXXXXX-X{random.randint(1000, 9999)}"
            else:
                masked = f"{prefix}XXX-XXXX-XXXX-{random.randint(1000, 9999)}"
            return masked

        entries = self.generate_unique_set(
            masked_cc,
            self.count,
            MAX_LENGTHS["credit_card"],
        )
        return AnonymizationDataSet(
            name="Credit Card Numbers (Masked)",
            handle="anon_credit_cards",
            description="Masked credit card number surrogates (non-functional)",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["credit_card"],
            entries=entries,
        )

    def generate_sin_surrogates(self) -> AnonymizationDataSet:
        """Generate sin-format surrogates (not valid sins)."""

        def sin_surrogate() -> str:
            # Use invalid area numbers (900-999 are not assigned)
            area = random.randint(900, 999)
            group = random.randint(10, 99)
            serial = random.randint(1000, 9999)
            fmt = random.choice(
                [
                    f"{area}-{group}-{serial}",
                    f"{area}{group}{serial}",
                    f"XXX-XX-{serial}",
                ]
            )
            return fmt

        entries = self.generate_unique_set(
            sin_surrogate,
            self.count,
            MAX_LENGTHS["sin_surrogate"],
        )
        return AnonymizationDataSet(
            name="sin Surrogates",
            handle="anon_sin",
            description="Non-valid sin-format surrogates for testing",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["sin_surrogate"],
            entries=entries,
        )

    def generate_account_numbers(self) -> AnonymizationDataSet:
        """Generate synthetic account numbers."""

        def account_num() -> str:
            prefix = random.choice(["ACC", "ACT", "CUS", "CLT", "ORD", "INV"])
            number = "".join(
                str(random.randint(0, 9)) for _ in range(random.randint(8, 12))
            )
            return f"{prefix}-{number}"

        entries = self.generate_unique_set(
            account_num,
            self.count,
            MAX_LENGTHS["account_number"],
        )
        return AnonymizationDataSet(
            name="Account Numbers",
            handle="anon_accounts",
            description="Synthetic account/reference numbers",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["account_number"],
            entries=entries,
        )

    def generate_usernames(self) -> AnonymizationDataSet:
        """Generate unique usernames."""
        entries = self.generate_unique_set(
            self.faker.user_name,
            self.count,
            MAX_LENGTHS["username"],
        )
        return AnonymizationDataSet(
            name="Usernames",
            handle="anon_usernames",
            description="Synthetic usernames",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=MAX_LENGTHS["username"],
            entries=entries,
        )

    def generate_ages(self) -> AnonymizationDataSet:
        """Generate age values (18-99)."""
        entries = [str(age) for age in range(18, 100)]
        return AnonymizationDataSet(
            name="Ages",
            handle="anon_ages",
            description="Valid age values (18-99)",
            oracle_data_types=["NUMBER", "VARCHAR2", "CHAR"],
            max_length=3,
            entries=entries,
        )

    def generate_statuses(self) -> AnonymizationDataSet:
        """Generate Siebel-compatible status values."""
        return AnonymizationDataSet(
            name="Status Values",
            handle="anon_statuses",
            description="Common Siebel status values",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=30,
            entries=sorted(set(self.sanitize_oracle(s) for s in SIEBEL_STATUSES)),
        )

    def generate_priorities(self) -> AnonymizationDataSet:
        """Generate Siebel-compatible priority values."""
        return AnonymizationDataSet(
            name="Priority Values",
            handle="anon_priorities",
            description="Common Siebel priority values",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=20,
            entries=sorted(set(self.sanitize_oracle(s) for s in SIEBEL_PRIORITIES)),
        )

    def generate_types(self) -> AnonymizationDataSet:
        """Generate Siebel-compatible type values."""
        return AnonymizationDataSet(
            name="Type Values",
            handle="anon_types",
            description="Common Siebel entity type values",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2"],
            max_length=30,
            entries=sorted(set(self.sanitize_oracle(s) for s in SIEBEL_TYPES)),
        )

    def generate_comments(self) -> AnonymizationDataSet:
        """Generate lorem ipsum comment/notes text."""

        def comment_text() -> str:
            return self.faker.sentence(nb_words=random.randint(5, 20))

        entries = self.generate_unique_set(
            comment_text,
            min(self.count, 5000),
            MAX_LENGTHS["comments"],
        )
        return AnonymizationDataSet(
            name="Comment Text",
            handle="anon_comments",
            description="Lorem ipsum placeholder text for notes/comments",
            oracle_data_types=["VARCHAR2", "CHAR", "NVARCHAR2", "CLOB"],
            max_length=MAX_LENGTHS["comments"],
            entries=entries,
        )

    def generate_all(self) -> list[AnonymizationDataSet]:
        """Generate all data sets."""
        return [
            self.generate_first_names(),
            self.generate_last_names(),
            self.generate_full_names(),
            self.generate_emails(),
            self.generate_phone_numbers(),
            self.generate_street_addresses(),
            self.generate_cities(),
            self.generate_states(),
            self.generate_postal_codes(),
            self.generate_company_names(),
            self.generate_job_titles(),
            self.generate_credit_card_numbers(),
            self.generate_sin_surrogates(),
            self.generate_account_numbers(),
            self.generate_usernames(),
            self.generate_ages(),
            self.generate_statuses(),
            self.generate_priorities(),
            self.generate_types(),
            self.generate_comments(),
        ]


# ==============================================================================
# Oracle Package Generator
# ==============================================================================


class OraclePackageGenerator:
    """Generates Oracle PL/SQL packages from data sets."""

    def __init__(self, schema: str = "ANON_DATA"):
        self.schema = schema

    def generate_package(self, dataset: AnonymizationDataSet) -> GeneratedPackage:
        """Generate Oracle PL/SQL package for a data set."""
        pkg_name = f"PKG_{dataset.handle.upper()}"
        array_name = f"T_{dataset.handle.upper()}"
        func_name = f"GET_{dataset.handle.upper().replace('ANON_', '')}"
        count_const = f"C_COUNT"

        # Generate install SQL (creates supporting types if needed)
        install_sql = self._generate_install_sql(dataset, pkg_name)

        # Generate package specification
        spec_sql = self._generate_spec_sql(dataset, pkg_name, func_name)

        # Generate package body with embedded data
        body_sql = self._generate_body_sql(
            dataset, pkg_name, array_name, func_name, count_const
        )

        return GeneratedPackage(
            name=f"Anonymization Data: {dataset.name}",
            handle=dataset.handle,
            summary=dataset.description,
            install_sql=install_sql,
            spec_sql=spec_sql,
            body_sql=body_sql,
            data_count=len(dataset.entries),
            max_length=dataset.max_length,
        )

    def _generate_install_sql(
        self, dataset: AnonymizationDataSet, pkg_name: str
    ) -> str:
        """Generate installation SQL for the package."""
        lines = [
            f"-- Installation script for {pkg_name}",
            f"-- Data set: {dataset.name}",
            f"-- Entries: {len(dataset.entries)}",
            f"-- Max length: {dataset.max_length}",
            "",
            "-- No additional installation steps required for this package.",
            "-- The package is self-contained with embedded data.",
            "",
        ]
        return "\n".join(lines)

    def _generate_spec_sql(
        self,
        dataset: AnonymizationDataSet,
        pkg_name: str,
        func_name: str,
    ) -> str:
        """Generate package specification."""
        lines = [
            f"CREATE OR REPLACE PACKAGE {self.schema}.{pkg_name} AS",
            f"  /*",
            f"   * {dataset.name}",
            f"   * {dataset.description}",
            f"   *",
            f"   * Contains {len(dataset.entries)} unique entries.",
            f"   * Maximum length: {dataset.max_length} characters.",
            f"   * Compatible with: {', '.join(dataset.oracle_data_types)}",
            f"   */",
            "",
            f"  -- Get a deterministic entry based on a hash seed",
            f"  FUNCTION {func_name}(",
            f"    p_seed IN VARCHAR2,",
            f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
            f"  ) RETURN VARCHAR2 DETERMINISTIC;",
            "",
            f"  -- Get a random entry (non-deterministic)",
            f"  FUNCTION {func_name}_RANDOM(",
            f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
            f"  ) RETURN VARCHAR2;",
            "",
            f"  -- Get entry by index (1-based)",
            f"  FUNCTION {func_name}_BY_INDEX(",
            f"    p_index IN NUMBER,",
            f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
            f"  ) RETURN VARCHAR2 DETERMINISTIC;",
            "",
            f"  -- Get total count of entries",
            f"  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;",
            "",
            f"END {pkg_name};",
            "/",
        ]
        return "\n".join(lines)

    def _generate_body_sql(
        self,
        dataset: AnonymizationDataSet,
        pkg_name: str,
        array_name: str,
        func_name: str,
        count_const: str,
    ) -> str:
        """Generate package body with embedded data."""
        lines = [
            f"CREATE OR REPLACE PACKAGE BODY {self.schema}.{pkg_name} AS",
            "",
            f"  -- Data array type",
            f"  TYPE {array_name} IS TABLE OF VARCHAR2({dataset.max_length}) INDEX BY PLS_INTEGER;",
            "",
            f"  -- Embedded data",
            f"  g_data {array_name};",
            f"  {count_const} CONSTANT NUMBER := {len(dataset.entries)};",
            "",
            f"  -- Initialize data array",
            f"  PROCEDURE init_data IS",
            f"  BEGIN",
        ]

        # Add data entries in batches to avoid PL/SQL line limits
        batch_size = 100
        for i, entry in enumerate(dataset.entries, 1):
            if i % batch_size == 1 and i > 1:
                lines.append(f"  END;")
                lines.append(f"")
                lines.append(f"  PROCEDURE init_data_{i // batch_size + 1} IS")
                lines.append(f"  BEGIN")

            lines.append(f"    g_data({i}) := '{entry}';")

        lines.extend(
            [
                f"  END;",
                "",
                f"  -- Deterministic lookup by seed",
                f"  FUNCTION {func_name}(",
                f"    p_seed IN VARCHAR2,",
                f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
                f"  ) RETURN VARCHAR2 DETERMINISTIC IS",
                f"    v_hash RAW(32);",
                f"    v_idx NUMBER;",
                f"  BEGIN",
                f"    v_hash := DBMS_CRYPTO.HASH(",
                f"      UTL_RAW.CAST_TO_RAW(NVL(p_seed, 'NULL')),",
                f"      DBMS_CRYPTO.HASH_SH256",
                f"    );",
                f"    v_idx := MOD(TO_NUMBER(SUBSTR(RAWTOHEX(v_hash), 1, 8), 'XXXXXXXX'), {count_const}) + 1;",
                f"    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, {dataset.max_length}));",
                f"  END {func_name};",
                "",
                f"  -- Random lookup (non-deterministic)",
                f"  FUNCTION {func_name}_RANDOM(",
                f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
                f"  ) RETURN VARCHAR2 IS",
                f"    v_idx NUMBER;",
                f"  BEGIN",
                f"    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, {count_const} + 1));",
                f"    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, {dataset.max_length}));",
                f"  END {func_name}_RANDOM;",
                "",
                f"  -- Index-based lookup",
                f"  FUNCTION {func_name}_BY_INDEX(",
                f"    p_index IN NUMBER,",
                f"    p_max_len IN NUMBER DEFAULT {dataset.max_length}",
                f"  ) RETURN VARCHAR2 DETERMINISTIC IS",
                f"    v_idx NUMBER;",
                f"  BEGIN",
                f"    v_idx := MOD(ABS(NVL(p_index, 0)), {count_const}) + 1;",
                f"    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, {dataset.max_length}));",
                f"  END {func_name}_BY_INDEX;",
                "",
                f"  -- Count function",
                f"  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS",
                f"  BEGIN",
                f"    RETURN {count_const};",
                f"  END GET_COUNT;",
                "",
                f"BEGIN",
                f"  -- Initialize data on package load",
                f"  init_data;",
            ]
        )

        # Add calls to additional init procedures if data was split
        num_batches = (len(dataset.entries) - 1) // batch_size
        for i in range(2, num_batches + 2):
            lines.append(f"  init_data_{i};")

        lines.extend(
            [
                f"END {pkg_name};",
                "/",
            ]
        )

        return "\n".join(lines)


# ==============================================================================
# PHP Seeder Generator
# ==============================================================================


class PHPSeederGenerator:
    """Generates Laravel/PHP seeders for anonymization methods."""

    def generate_data_export(
        self,
        datasets: list[AnonymizationDataSet],
        output_dir: Path,
    ) -> None:
        """Export data sets as JSON for PHP consumption."""
        data_dir = output_dir / "data"
        data_dir.mkdir(parents=True, exist_ok=True)

        manifest = []

        for dataset in datasets:
            filename = f"{dataset.handle}.json"
            filepath = data_dir / filename

            export_data = {
                "name": dataset.name,
                "handle": dataset.handle,
                "description": dataset.description,
                "oracle_data_types": dataset.oracle_data_types,
                "max_length": dataset.max_length,
                "nullable_safe": dataset.nullable_safe,
                "format_pattern": dataset.format_pattern,
                "count": len(dataset.entries),
                "entries": dataset.entries,
            }

            with open(filepath, "w", encoding="utf-8") as f:
                json.dump(export_data, f, indent=2, ensure_ascii=False)

            manifest.append(
                {
                    "name": dataset.name,
                    "handle": dataset.handle,
                    "file": filename,
                    "count": len(dataset.entries),
                    "max_length": dataset.max_length,
                    "oracle_data_types": dataset.oracle_data_types,
                }
            )

            print(
                f"  Exported {dataset.handle}: {len(dataset.entries)} entries -> {filename}"
            )

        # Write manifest
        manifest_path = data_dir / "manifest.json"
        with open(manifest_path, "w", encoding="utf-8") as f:
            json.dump(
                {
                    "generated": date.today().isoformat(),
                    "datasets": manifest,
                },
                f,
                indent=2,
            )

        print(f"  Manifest written to {manifest_path}")

    def generate_method_seeder(self, output_dir: Path) -> str:
        """Generate PHP seeder for lookup-based anonymization methods."""
        seeder_content = """<?php

namespace Database\\Seeders;

use App\\Models\\Anonymizer\\AnonymizationMethods;
use App\\Models\\Anonymizer\\AnonymizationPackage;
use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\File;

/**
 * Seeds anonymization methods that use Faker-generated lookup packages.
 *
 * These methods use pre-generated synthetic data stored in Oracle PL/SQL packages
 * for deterministic and random lookups, providing realistic masked values.
 *
 * @see scripts/oracle/generate_faker_anonymization_packages.py
 */
class AnonymizationFakerLookupMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // ================================================================
            // DETERMINISTIC LOOKUP METHODS (use seed for consistent results)
            // ================================================================
            [
                'name' => 'Faker First Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces first names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original first names to realistic synthetic first names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated first names.',
                'sql_block' => <<<SQL
-- Deterministic first name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES(
       {{JOB_SEED_LITERAL}} || '|FN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Last Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces last names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original last names to realistic synthetic last names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated last names.',
                'sql_block' => <<<SQL
-- Deterministic last name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_LAST_NAMES.GET_LAST_NAMES(
       {{JOB_SEED_LITERAL}} || '|LN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Full Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces full names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original full names to realistic synthetic full names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated full names.',
                'sql_block' => <<<SQL
-- Deterministic full name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FULL_NAMES.GET_FULL_NAMES(
       {{JOB_SEED_LITERAL}} || '|NAME|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Email (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces email addresses with synthetic values using safe domains.',
                'what_it_does' => 'Maps original emails to realistic synthetic emails deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated safe emails.',
                'sql_block' => <<<SQL
-- Deterministic email lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_EMAILS.GET_EMAILS(
       {{JOB_SEED_LITERAL}} || '|EMAIL|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'All generated emails use @example.com/org/net domains.',
            ],
            [
                'name' => 'Faker Phone Number (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces phone numbers with synthetic values using reserved exchanges.',
                'what_it_does' => 'Maps original phones to realistic synthetic phone numbers deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic phone numbers.',
                'sql_block' => <<<SQL
-- Deterministic phone lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PHONES.GET_PHONES(
       {{JOB_SEED_LITERAL}} || '|PHONE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses 555 and toll-free exchanges. Various formats supported.',
            ],
            [
                'name' => 'Faker Street Address (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces street addresses with synthetic values.',
                'what_it_does' => 'Maps original addresses to realistic synthetic addresses deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated addresses.',
                'sql_block' => <<<SQL
-- Deterministic address lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ADDRESSES.GET_ADDRESSES(
       {{JOB_SEED_LITERAL}} || '|ADDR|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Max length 200 chars.',
            ],
            [
                'name' => 'Faker City (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces city names with synthetic values.',
                'what_it_does' => 'Maps original cities to realistic synthetic city names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ Faker-generated city names.',
                'sql_block' => <<<SQL
-- Deterministic city lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_CITIES.GET_CITIES(
       {{JOB_SEED_LITERAL}} || '|CITY|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 50 chars.',
            ],
            [
                'name' => 'Faker Postal Code (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces postal/ZIP codes with synthetic values.',
                'what_it_does' => 'Maps original postal codes to synthetic US ZIP or Canadian postal codes.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic postal codes.',
                'sql_block' => <<<SQL
-- Deterministic postal code lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_POSTAL_CODES.GET_POSTAL_CODES(
       {{JOB_SEED_LITERAL}} || '|ZIP|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Mix of US ZIP (5-digit) and Canadian postal codes.',
            ],
            [
                'name' => 'Faker Company Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces company/organization names with synthetic values.',
                'what_it_does' => 'Maps original company names to realistic synthetic company names.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ Faker-generated company names.',
                'sql_block' => <<<SQL
-- Deterministic company name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_COMPANIES.GET_COMPANIES(
       {{JOB_SEED_LITERAL}} || '|ORG|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 100 chars.',
            ],
            [
                'name' => 'Faker sin Surrogate (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces sin/SIN values with non-valid format-preserving surrogates.',
                'what_it_does' => 'Maps original sin values to synthetic sin-format strings that are not valid.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ invalid-but-formatted sin surrogates.',
                'sql_block' => <<<SQL
-- Deterministic sin surrogate lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_sin.GET_sin(
       {{JOB_SEED_LITERAL}} || '|sin|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses invalid sin area codes (900-999). Not valid for verification.',
            ],
            [
                'name' => 'Faker Credit Card (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces credit card numbers with masked non-functional surrogates.',
                'what_it_does' => 'Maps original CC values to masked format-preserving surrogates.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ masked card number patterns.',
                'sql_block' => <<<SQL
-- Deterministic credit card lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_CREDIT_CARDS.GET_CREDIT_CARDS(
       {{JOB_SEED_LITERAL}} || '|CC|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Masked format with visible last 4 digits. Not functional.',
            ],
            [
                'name' => 'Faker Account Number (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces account/reference numbers with synthetic values.',
                'what_it_does' => 'Maps original account numbers to synthetic account number patterns.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic account numbers.',
                'sql_block' => <<<SQL
-- Deterministic account number lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ACCOUNTS.GET_ACCOUNTS(
       {{JOB_SEED_LITERAL}} || '|ACCT|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: PREFIX-NNNNNNNN',
            ],
            [
                'name' => 'Faker Username (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces usernames/login IDs with synthetic values.',
                'what_it_does' => 'Maps original usernames to realistic synthetic usernames.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated usernames.',
                'sql_block' => <<<SQL
-- Deterministic username lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_USERNAMES.GET_USERNAMES(
       {{JOB_SEED_LITERAL}} || '|USER|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 50 chars.',
            ],
            [
                'name' => 'Faker Comment/Notes (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces free-text comments with lorem ipsum placeholder text.',
                'what_it_does' => 'Maps original comments to generic lorem ipsum text.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ lorem ipsum sentences.',
                'sql_block' => <<<SQL
-- Deterministic comment lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_COMMENTS.GET_COMMENTS(
       {{JOB_SEED_LITERAL}} || '|NOTES|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For CLOB columns, use with explicit max_len or separate CLOB method.',
            ],
            [
                'name' => 'Faker Job Title (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces job titles with synthetic values.',
                'what_it_does' => 'Maps original job titles to realistic synthetic job titles.',
                'how_it_works' => 'Uses a seed-based hash to select from 2,000+ Faker-generated job titles.',
                'sql_block' => <<<SQL
-- Deterministic job title lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_JOB_TITLES.GET_JOB_TITLES(
       {{JOB_SEED_LITERAL}} || '|TITLE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 75 chars.',
            ],

            // ================================================================
            // RANDOM LOOKUP METHODS (non-deterministic, each run different)
            // ================================================================
            [
                'name' => 'Faker First Name (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces first names with random synthetic values.',
                'what_it_does' => 'Replaces original first names with random synthetic first names.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated first names.',
                'sql_block' => <<<SQL
-- Random first name from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Different results each execution.',
            ],
            [
                'name' => 'Faker Last Name (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces last names with random synthetic values.',
                'what_it_does' => 'Replaces original last names with random synthetic last names.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated last names.',
                'sql_block' => <<<SQL
-- Random last name from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_LAST_NAMES.GET_LAST_NAMES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Different results each execution.',
            ],
            [
                'name' => 'Faker Email (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces emails with random synthetic values using safe domains.',
                'what_it_does' => 'Replaces original emails with random synthetic emails.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated safe emails.',
                'sql_block' => <<<SQL
-- Random email from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_EMAILS.GET_EMAILS_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Uses @example.com/org/net domains.',
            ],
            [
                'name' => 'Faker Phone (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces phone numbers with random synthetic values.',
                'what_it_does' => 'Replaces original phone numbers with random synthetic phones.',
                'how_it_works' => 'Selects randomly from 10,000+ synthetic phone numbers.',
                'sql_block' => <<<SQL
-- Random phone from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PHONES.GET_PHONES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Uses 555/toll-free exchanges.',
            ],
            [
                'name' => 'Faker Address (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces addresses with random synthetic values.',
                'what_it_does' => 'Replaces original addresses with random synthetic addresses.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated addresses.',
                'sql_block' => <<<SQL
-- Random address from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ADDRESSES.GET_ADDRESSES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Max length 200 chars.',
            ],

            // ================================================================
            // SIEBEL STATUS/CATEGORY METHODS (deterministic from fixed lists)
            // ================================================================
            [
                'name' => 'Siebel Status Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces status values with valid Siebel status terms.',
                'what_it_does' => 'Maps original status values to valid Siebel-compatible status values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel status values.',
                'sql_block' => <<<SQL
-- Deterministic status value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_STATUSES.GET_STATUSES(
       {{JOB_SEED_LITERAL}} || '|STATUS|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel status values (Active, Inactive, Pending, etc.)',
            ],
            [
                'name' => 'Siebel Priority Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces priority values with valid Siebel priority terms.',
                'what_it_does' => 'Maps original priority values to valid Siebel-compatible priority values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel priority values.',
                'sql_block' => <<<SQL
-- Deterministic priority value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PRIORITIES.GET_PRIORITIES(
       {{JOB_SEED_LITERAL}} || '|PRIORITY|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel priority values (1-ASAP, 2-High, 3-Medium, 4-Low)',
            ],
            [
                'name' => 'Siebel Type Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces type/category values with valid Siebel type terms.',
                'what_it_does' => 'Maps original type values to valid Siebel-compatible type values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel entity types.',
                'sql_block' => <<<SQL
-- Deterministic type value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_TYPES.GET_TYPES(
       {{JOB_SEED_LITERAL}} || '|TYPE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel type values (Individual, Organization, etc.)',
            ],

            // ================================================================
            // NUMERIC METHODS
            // ================================================================
            [
                'name' => 'Faker Age (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Replaces age values with valid ages (18-99).',
                'what_it_does' => 'Maps original ages to valid age values within the 18-99 range.',
                'how_it_works' => 'Uses a seed-based hash to select deterministically from valid ages.',
                'sql_block' => <<<SQL
-- Deterministic age value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_NUMBER(ANON_DATA.PKG_ANON_AGES.GET_AGES(
       {{JOB_SEED_LITERAL}} || '|AGE|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}),
       3
   ))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Returns NUMBER. Valid range 18-99.',
            ],
            [
                'name' => 'Numeric Perturbation (±10%)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Perturbs numeric values by ±10% using deterministic hashing.',
                'what_it_does' => 'Adjusts numeric values by a stable pseudo-random percentage.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent ±10% adjustment.',
                'sql_block' => <<<SQL
-- Numeric perturbation ±10%
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} * (
       0.9 + (
           MOD(
               TO_NUMBER(
                   SUBSTR(
                       LOWER(RAWTOHEX(STANDARD_HASH(
                           {{JOB_SEED_LITERAL}} || '|NUM|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}),
                           'SHA256'
                       ))),
                       1, 8
                   ),
                   'xxxxxxxx'
               ),
               201
           ) / 1000.0
       )
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For NUMBER columns. Preserves sign and approximate magnitude.',
            ],

            // ================================================================
            // DATE/TIME METHODS
            // ================================================================
            [
                'name' => 'Date Shift (±30 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts dates by ±30 days using deterministic hashing.',
                'what_it_does' => 'Adjusts dates by a stable pseudo-random offset within ±30 days.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent day offset.',
                'sql_block' => <<<SQL
-- Date shift ±30 days
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} + (
       MOD(
           TO_NUMBER(
               SUBSTR(
                   LOWER(RAWTOHEX(STANDARD_HASH(
                       {{JOB_SEED_LITERAL}} || '|DATE30|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}, 'YYYYMMDD'),
                       'SHA256'
                   ))),
                   1, 8
               ),
               'xxxxxxxx'
           ),
           61
       ) - 30
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For DATE columns. Preserves relative ordering within ±30 day variance.',
            ],
            [
                'name' => 'Timestamp Shift (±7 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts timestamps by ±7 days using deterministic hashing.',
                'what_it_does' => 'Adjusts timestamps by a stable pseudo-random offset.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent time offset.',
                'sql_block' => <<<SQL
-- Timestamp shift ±7 days
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} + (
       MOD(
           TO_NUMBER(
               SUBSTR(
                   LOWER(RAWTOHEX(STANDARD_HASH(
                       {{JOB_SEED_LITERAL}} || '|TS7|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}, 'YYYYMMDDHH24MISS'),
                       'SHA256'
                   ))),
                   1, 8
               ),
               'xxxxxxxx'
           ),
           15
       ) - 7
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For TIMESTAMP columns. ±7 day variance.',
            ],

            // ================================================================
            // CLOB/LARGE TEXT METHODS
            // ================================================================
            [
                'name' => 'CLOB Comment Replacement',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces CLOB content with lorem ipsum placeholder text.',
                'what_it_does' => 'Replaces large text fields with generic placeholder content.',
                'how_it_works' => 'Uses a deterministic lookup to select lorem ipsum text.',
                'sql_block' => <<<SQL
-- CLOB replacement with lorem ipsum
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CLOB(ANON_DATA.PKG_ANON_COMMENTS.GET_COMMENTS(
       {{JOB_SEED_LITERAL}} || '|CLOB|' || TO_CHAR({{SEED_EXPR}}),
       255
   ))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For CLOB columns. Truncates to 255 chars; extend as needed.',
            ],

            // ================================================================
            // NULL-SAFE CONDITIONAL METHODS
            // ================================================================
            [
                'name' => 'Faker First Name (Nullable-Safe)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING, AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces first names, preserving NULL values.',
                'what_it_does' => 'Maps non-null first names to synthetic values; NULLs remain NULL.',
                'how_it_works' => 'Uses NVL2 to conditionally apply masking only to non-null values.',
                'sql_block' => <<<SQL
-- Nullable-safe first name replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = NVL2(
       tgt.{{COLUMN}},
       ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES(
           {{JOB_SEED_LITERAL}} || '|FN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
           {{COLUMN_MAX_LEN_EXPR}}
       ),
       NULL
   );
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves NULL values. No WHERE clause needed.',
            ],
        ];

        foreach ($methods as $payload) {
            $method = AnonymizationMethods::withTrashed()->updateOrCreate(
                ['name' => $payload['name']],
                $payload
            );

            if ($method->trashed()) {
                $method->restore();
            }
        }

        $this->command->info('Seeded ' . count($methods) . ' Faker-based anonymization methods.');
    }
}
"""

        seeder_path = output_dir / "AnonymizationFakerLookupMethodSeeder.php"
        with open(seeder_path, "w", encoding="utf-8") as f:
            f.write(seeder_content)

        print(f"  Generated method seeder: {seeder_path}")
        return str(seeder_path)


# ==============================================================================
# SQL Script Generator
# ==============================================================================


class SQLScriptGenerator:
    """Generates combined installation SQL scripts."""

    def __init__(self, schema: str = "ANON_DATA"):
        self.schema = schema

    def generate_master_install(
        self,
        packages: list[GeneratedPackage],
        output_dir: Path,
    ) -> None:
        """Generate master installation script."""
        lines = [
            "-- ============================================================================",
            "-- ANONYMIZATION DATA PACKAGES - MASTER INSTALLATION SCRIPT",
            f"-- Generated: {date.today().isoformat()}",
            f"-- Target Schema: {self.schema}",
            "-- ============================================================================",
            "",
            "-- This script installs all Faker-generated anonymization data packages.",
            "-- Run as a user with CREATE privileges in the target schema.",
            "",
            f"-- Create schema if not exists (adjust grants as needed)",
            "DECLARE",
            "  v_count NUMBER;",
            "BEGIN",
            f"  SELECT COUNT(*) INTO v_count FROM all_users WHERE username = '{self.schema}';",
            "  IF v_count = 0 THEN",
            f"    EXECUTE IMMEDIATE 'CREATE USER {self.schema} IDENTIFIED BY \"{self.schema}_pwd\" QUOTA UNLIMITED ON USERS';",
            f"    EXECUTE IMMEDIATE 'GRANT CREATE SESSION, CREATE PROCEDURE, CREATE TABLE TO {self.schema}';",
            "  END IF;",
            "END;",
            "/",
            "",
            f"ALTER SESSION SET CURRENT_SCHEMA = {self.schema};",
            "",
        ]

        # Add each package
        for pkg in packages:
            lines.extend(
                [
                    f"-- ============================================================================",
                    f"-- Package: {pkg.name}",
                    f"-- Handle: {pkg.handle}",
                    f"-- Data Count: {pkg.data_count}",
                    f"-- Max Length: {pkg.max_length}",
                    f"-- ============================================================================",
                    "",
                    pkg.spec_sql,
                    "",
                    pkg.body_sql,
                    "",
                ]
            )

        lines.extend(
            [
                "-- ============================================================================",
                "-- Installation Complete",
                "-- ============================================================================",
                f"SELECT '{self.schema} packages installed successfully' AS status FROM dual;",
            ]
        )

        install_path = output_dir / "ANON_DATA_INSTALL_ALL.sql"
        with open(install_path, "w", encoding="utf-8") as f:
            f.write("\n".join(lines))

        print(f"  Generated master install script: {install_path}")

    def generate_individual_scripts(
        self,
        packages: list[GeneratedPackage],
        output_dir: Path,
    ) -> None:
        """Generate individual package scripts."""
        pkg_dir = output_dir / "packages"
        pkg_dir.mkdir(parents=True, exist_ok=True)

        for pkg in packages:
            # Spec file
            spec_path = pkg_dir / f"{pkg.handle}_spec.sql"
            with open(spec_path, "w", encoding="utf-8") as f:
                f.write(f"-- Package Specification: {pkg.name}\n")
                f.write(f"-- {pkg.summary}\n")
                f.write(f"-- Data Count: {pkg.data_count}\n\n")
                f.write(pkg.spec_sql)

            # Body file
            body_path = pkg_dir / f"{pkg.handle}_body.sql"
            with open(body_path, "w", encoding="utf-8") as f:
                f.write(f"-- Package Body: {pkg.name}\n")
                f.write(f"-- {pkg.summary}\n")
                f.write(f"-- Data Count: {pkg.data_count}\n\n")
                f.write(pkg.body_sql)

            print(f"  Generated {pkg.handle}: spec + body")


# ==============================================================================
# Main
# ==============================================================================


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Generate Oracle anonymization packages using Faker"
    )
    parser.add_argument(
        "--count",
        "-n",
        type=int,
        default=DEFAULT_COUNT,
        help=f"Number of entries per data set (default: {DEFAULT_COUNT})",
    )
    parser.add_argument(
        "--seed",
        "-s",
        type=int,
        default=DEFAULT_SEED,
        help=f"Random seed for reproducibility (default: {DEFAULT_SEED})",
    )
    parser.add_argument(
        "--output-dir",
        "-o",
        type=str,
        default=DEFAULT_OUTPUT_DIR,
        help=f"Output directory (default: {DEFAULT_OUTPUT_DIR})",
    )
    parser.add_argument(
        "--schema",
        type=str,
        default="ANON_DATA",
        help="Oracle schema name for packages (default: ANON_DATA)",
    )
    parser.add_argument(
        "--skip-sql", action="store_true", help="Skip generating Oracle SQL packages"
    )
    parser.add_argument(
        "--skip-php", action="store_true", help="Skip generating PHP seeders"
    )

    args = parser.parse_args()

    # Resolve output directory relative to repo root
    script_dir = Path(__file__).parent
    repo_root = script_dir.parent.parent
    output_dir = repo_root / args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    print(f"Generating anonymization packages...")
    print(f"  Count per set: {args.count}")
    print(f"  Seed: {args.seed}")
    print(f"  Output: {output_dir}")
    print(f"  Schema: {args.schema}")
    print()

    # Generate data
    print("Generating fake data sets...")
    generator = FakerDataGenerator(seed=args.seed, count=args.count)
    datasets = generator.generate_all()

    for ds in datasets:
        print(f"  {ds.name}: {len(ds.entries)} entries (max {ds.max_length} chars)")

    print()

    # Generate Oracle packages
    if not args.skip_sql:
        print("Generating Oracle PL/SQL packages...")
        pkg_generator = OraclePackageGenerator(schema=args.schema)
        packages = [pkg_generator.generate_package(ds) for ds in datasets]

        sql_generator = SQLScriptGenerator(schema=args.schema)
        sql_generator.generate_master_install(packages, output_dir)
        sql_generator.generate_individual_scripts(packages, output_dir)
        print()

    # Generate PHP seeders and data exports
    if not args.skip_php:
        print("Generating PHP seeders and data exports...")
        php_generator = PHPSeederGenerator()
        php_generator.generate_data_export(datasets, output_dir)
        php_generator.generate_method_seeder(output_dir.parent.parent)
        print()

    print("Done!")
    print()
    print("Next steps:")
    print(f"  1. Review generated files in {output_dir}")
    print(
        f"  2. Install Oracle packages: sqlplus @{output_dir}/ANON_DATA_INSTALL_ALL.sql"
    )
    print(
        f"  3. Run Laravel seeder: sail artisan db:seed --class=AnonymizationFakerLookupMethodSeeder"
    )


if __name__ == "__main__":
    main()
