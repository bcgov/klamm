#!/usr/bin/env python3

import csv
import sys
import os
import re
from datetime import datetime
import math


def read_csv_file(file_path):
    """Read CSV file and return data as a list of dictionaries."""
    if not os.path.exists(file_path):
        print(f"Error: File {file_path} not found.")
        sys.exit(1)

    with open(file_path, "r", encoding="utf-8") as file:
        reader = csv.DictReader(file)
        return list(reader)


def extract_field_references_from_csv(fields_data):
    """
    Extract field references directly from the field_references column.
    Returns a list of tuples (parent_field_id, referenced_field_id).
    """
    field_references = []
    field_name_to_id = {
        field["field_name"]: int(field["id"])
        for field in fields_data
        if "field_name" in field and "id" in field
    }

    # Update the has_field_references flag for each field
    for field in fields_data:
        field_id = int(field.get("id", 0))
        field_name = field.get("field_name", "")

        if "field_references" not in field or not field["field_references"]:
            field["has_field_references"] = False
            continue

        # Extract referenced field names from field_references column
        referenced_fields = [
            ref.strip() for ref in field["field_references"].split(",") if ref.strip()
        ]

        if referenced_fields:
            field["has_field_references"] = True
            for ref_field_name in referenced_fields:
                if ref_field_name in field_name_to_id:
                    ref_id = field_name_to_id[ref_field_name]
                    field_references.append((field_id, ref_id))
        else:
            field["has_field_references"] = False

    return field_references


def extract_field_references_from_calculated(fields_data):
    """
    Extract field references from calculated_value for fields that don't have explicit field_references.
    Returns a list of tuples (parent_field_id, referenced_field_id).
    """
    field_references = []
    field_name_to_id = {
        field["field_name"]: int(field["id"])
        for field in fields_data
        if "field_name" in field and "id" in field
    }

    # Check for fields that don't have explicit field_references but do have calculated_value
    for field in fields_data:
        field_id = int(field.get("id", 0))
        calculated_value = field.get("calculated_value", "")

        if field.get("has_field_references") or not calculated_value:
            continue

        # Look for field references in calculated fields
        # Pattern matches [Field.FieldName] format commonly used in Siebel expressions
        referenced_field_matches = re.findall(r"\[Field\.([^\]]+)\]", calculated_value)

        if referenced_field_matches:
            field["has_field_references"] = True
            for ref_field_name in referenced_field_matches:
                if ref_field_name in field_name_to_id:
                    ref_id = field_name_to_id[ref_field_name]
                    field_references.append((field_id, ref_id))

    return field_references


def identify_lov_fields(fields_data):
    """
    Identify fields that have list of values relationships directly from list_of_values column.
    Returns a list of tuples (field_id, field_name, lov_name) for further processing.
    """
    field_lov_relations = []

    for field in fields_data:
        field_id = int(field.get("id", 0))
        field_name = field.get("field_name", "")
        lov_value = field.get("list_of_values", "").strip()

        if lov_value:
            field["has_list_of_values"] = True
            # Store the relationship for later LOV ID lookup
            field_lov_relations.append((field_id, field_name, lov_value))
        else:
            field["has_list_of_values"] = False

    return field_lov_relations


def map_lov_names_to_ids(field_lov_relations, values_data):
    """
    Map list of values names to their IDs using the values data.
    Returns a list of tuples (field_id, lov_id).
    """
    mapped_relations = []

    # Create mappings from value names and types to IDs
    lov_name_to_id = {}
    lov_type_to_id = {}

    for value in values_data:
        if "id" in value and value["id"]:
            # Map by name
            if "name" in value and value["name"]:
                lov_name_to_id[value["name"].strip().lower()] = int(value["id"])

            # Map by type
            if "type" in value and value["type"]:
                lov_type_to_id[value["type"].strip().lower()] = int(value["id"])

    unmapped_lovs = []

    for field_id, field_name, lov_value in field_lov_relations:
        lov_value_lower = lov_value.lower()
        mapped = False

        # Try matching by exact name first
        if lov_value_lower in lov_name_to_id:
            mapped_relations.append((field_id, lov_name_to_id[lov_value_lower]))
            mapped = True

        # Try matching by type
        elif lov_value_lower in lov_type_to_id:
            mapped_relations.append((field_id, lov_type_to_id[lov_value_lower]))
            mapped = True

        # Try matching by partial name (for cases where there might be slight variations)
        else:
            for name, lov_id in lov_name_to_id.items():
                if (lov_value_lower in name) or (name in lov_value_lower):
                    print(f"Found partial match for '{lov_value}' with value '{name}'")
                    mapped_relations.append((field_id, lov_id))
                    mapped = True
                    break

        if not mapped:
            unmapped_lovs.append((field_id, field_name, lov_value))
            print(
                f"Warning: List of values '{lov_value}' not found for field '{field_name}' (ID: {field_id})"
            )

    print(
        f"Successfully mapped {len(mapped_relations)} out of {len(field_lov_relations)} LOV relationships"
    )
    if unmapped_lovs:
        print(f"Could not map {len(unmapped_lovs)} LOV values")

    return mapped_relations


def make_php_safe_string(value):
    """Make a string PHP-safe by escaping special characters."""
    if value is None:
        return ""

    # Replace backslashes first to avoid double-escaping
    value = str(value).replace("\\", "\\\\")

    # Replace single quotes as they are used to delimit the string in PHP
    value = value.replace("'", "\\'")

    # Replace other potentially problematic characters
    value = value.replace("\n", "\\n")
    value = value.replace("\r", "\\r")
    value = value.replace("\t", "\\t")

    return value


def generate_field_references_seeder(field_references, output_path):
    """Generate a seeder file for field references."""
    today = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Remove duplicates from field_references
    unique_field_references = list(set(field_references))

    seeder_content = f"""<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;

class SiebelFieldReferencesSeeder extends Seeder
{{
    /**
     * Auto-generated seeder for Siebel Field References
     * Generated on {today}
     * Total references: {len(unique_field_references)}
     *
     * @return void
     */
    public function run()
    {{
        // Process records in batches to optimize memory usage
        $records = $this->getRecords();

        // Use chunking to insert in batches
        foreach (array_chunk($records, 500) as $batch) {{
            DB::table('siebel_field_references')->insert($batch);
        }}
    }}

    /**
     * Get the records to be inserted.
     *
     * @return array
     */
    private function getRecords()
    {{
        $now = Carbon::now();
        return [
"""

    for i, (parent_id, referenced_id) in enumerate(unique_field_references):
        seeder_content += "            [\n"
        seeder_content += f"                'id' => {i + 1},\n"
        seeder_content += f"                'parent_field_id' => {parent_id},\n"
        seeder_content += f"                'referenced_field_id' => {referenced_id},\n"
        seeder_content += "                'created_at' => null,\n"
        seeder_content += "                'updated_at' => null,\n"
        seeder_content += (
            "            ]"
            + ("," if i < len(unique_field_references) - 1 else "")
            + "\n"
        )

    seeder_content += """        ];
    }
}
"""

    with open(output_path, "w", encoding="utf-8") as file:
        file.write(seeder_content)

    print(
        f"Written field references seeder with {len(unique_field_references)} entries to: {output_path}"
    )
    return output_path


def generate_field_values_seeder(field_lov_relations, output_path):
    """Generate a seeder file for field to LOV relationships."""
    today = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Remove duplicates from field_lov_relations
    unique_field_lov_relations = list(set(field_lov_relations))

    seeder_content = f"""<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;

class SiebelFieldValuesSeeder extends Seeder
{{
    /**
     * Auto-generated seeder for Siebel Field to Values Relationships
     * Generated on {today}
     * Total relationships: {len(unique_field_lov_relations)}
     *
     * @return void
     */
    public function run()
    {{
        // Process records in batches to optimize memory usage
        $records = $this->getRecords();

        // Use chunking to insert in batches
        foreach (array_chunk($records, 500) as $batch) {{
            DB::table('siebel_field_values')->insert($batch);
        }}
    }}

    /**
     * Get the records to be inserted.
     *
     * @return array
     */
    private function getRecords()
    {{
        $now = Carbon::now();
        return [
"""

    for i, (field_id, lov_id) in enumerate(unique_field_lov_relations):
        seeder_content += "            [\n"
        seeder_content += f"                'id' => {i + 1},\n"
        seeder_content += f"                'siebel_field_id' => {field_id},\n"
        seeder_content += f"                'siebel_value_id' => {lov_id},\n"
        seeder_content += "                'created_at' => null,\n"
        seeder_content += "                'updated_at' => null,\n"
        seeder_content += (
            "            ]"
            + ("," if i < len(unique_field_lov_relations) - 1 else "")
            + "\n"
        )

    seeder_content += """        ];
    }
}
"""

    with open(output_path, "w", encoding="utf-8") as file:
        file.write(seeder_content)

    print(
        f"Written field values seeder with {len(unique_field_lov_relations)} entries to: {output_path}"
    )
    return output_path


def main():
    if len(sys.argv) < 3:  # Changed to require siebel_values.csv
        print(
            "Usage: python generate_siebel_field_relationships_seeder.py <siebel_fields_with_relations.csv> "
            "<siebel_values.csv> [output_dir] [max_entries_per_file]"
        )
        sys.exit(1)

    siebel_fields_path = sys.argv[1]
    siebel_values_path = sys.argv[2]
    output_dir = sys.argv[3] if len(sys.argv) > 3 else "."

    # Ensure output directory exists
    os.makedirs(output_dir, exist_ok=True)

    print(f"Reading Siebel fields from: {siebel_fields_path}")
    fields_data = read_csv_file(siebel_fields_path)

    # Ensure each field has an ID (important for generated seeders)
    for i, field in enumerate(fields_data):
        if "id" not in field or not field["id"]:
            field["id"] = i + 1

    # Extract field references from the field_references column
    print("Extracting field references from field_references column...")
    field_references = extract_field_references_from_csv(fields_data)

    # Extract additional field references from calculated_value
    print("Extracting additional field references from calculated_value...")
    calculated_references = extract_field_references_from_calculated(fields_data)

    # Combine all field references
    all_field_references = field_references + calculated_references
    print(f"Found total of {len(all_field_references)} field references")

    # Identify LOV fields
    print("Identifying fields with list of values...")
    field_lov_relations = identify_lov_fields(fields_data)
    print(f"Found {len(field_lov_relations)} potential field-LOV relationships")

    # Map LOV names to IDs
    print(f"Reading Siebel values from: {siebel_values_path}")
    if not os.path.exists(siebel_values_path):
        print(f"Error: Siebel values file not found at {siebel_values_path}")
        sys.exit(1)

    values_data = read_csv_file(siebel_values_path)
    print(f"Found {len(values_data)} value entries in siebel_values.csv")

    print("Mapping LOV names to IDs...")
    mapped_lov_relations = map_lov_names_to_ids(field_lov_relations, values_data)
    print(f"Mapped {len(mapped_lov_relations)} field-LOV relationships")

    # Generate field references seeder
    if all_field_references:
        references_seeder_path = os.path.join(
            output_dir, "SiebelFieldReferencesSeeder.php"
        )
        generate_field_references_seeder(all_field_references, references_seeder_path)

    # Generate field values seeder
    if mapped_lov_relations:
        values_seeder_path = os.path.join(output_dir, "SiebelFieldValuesSeeder.php")
        generate_field_values_seeder(mapped_lov_relations, values_seeder_path)
    else:
        print(
            "No LOV relationships mapped, skipping SiebelFieldValuesSeeder generation"
        )

    print("All seeder files generated successfully!")


if __name__ == "__main__":
    main()
