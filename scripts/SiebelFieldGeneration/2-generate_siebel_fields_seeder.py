#!/usr/bin/env python3

import csv
import sys
import os
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


def read_buscomp_file(file_path):
    """Read siebel_business_components.csv and extract relevant information."""
    if not os.path.exists(file_path):
        print(f"Warning: Business component source file {file_path} not found.")
        return {}

    try:
        with open(file_path, "r", encoding="utf-8") as file:
            # Skip the first row if it contains the filepath comment
            first_line = file.readline().strip()
            if first_line.startswith("// filepath:"):
                # Reset file pointer to beginning and then skip the first line
                file.seek(0)
                next(file)
            else:
                # If it's not a comment, reset the file pointer to the beginning
                file.seek(0)

            reader = csv.DictReader(file)
            buscomp_data = {}
            for row in reader:
                name = row.get("NAME")
                if name:
                    # Convert all keys to lowercase for case-insensitive matching
                    row_lower = {k.lower(): v for k, v in row.items()}

                    # Map columns from siebel_business_components.csv to our data structure
                    # Use lowercase keys for consistent access
                    buscomp_data[name] = {
                        "type": row_lower.get("type", "Non-Transient"),
                        "scripted": (
                            1 if row_lower.get("scripted_flg", "").upper() == "Y" else 0
                        ),
                        "search_specification": row_lower.get(
                            "search_specification", ""
                        )
                        or row_lower.get("srchspec", ""),
                        "sort_specification": row_lower.get("sort_specification", "")
                        or row_lower.get("sortspec", ""),
                        "table_name": row_lower.get("table_name", ""),
                        "class_name": row_lower.get("class_name", "CSSBusComp"),
                        "comments": row_lower.get("comments", ""),
                        "cache_data": (
                            "Y"
                            if row_lower.get("cache_data", "").upper() == "Y"
                            else None
                        ),
                        "data_source": row_lower.get("data_source", ""),
                        "dirty_reads": (
                            "Y"
                            if row_lower.get("dirty_reads", "").upper() == "Y"
                            else None
                        ),
                        "distinct": (
                            "Y"
                            if row_lower.get("distinct", "").upper() == "Y"
                            else None
                        ),
                        "enclosure_id_field": row_lower.get("enclosure_id_field", ""),
                        "force_active": (
                            "Y"
                            if row_lower.get("force_active", "").upper() == "Y"
                            else None
                        ),
                        "gen_reassign_act": (
                            "Y"
                            if row_lower.get("gen_reassign_act", "").upper() == "Y"
                            else None
                        ),
                        "hierarchy_parent_field": row_lower.get(
                            "hierarchy_parent_field", ""
                        ),
                        "inactive": (
                            "Y"
                            if row_lower.get("inactive", "").upper() == "Y"
                            else None
                        ),
                        "insert_update_all_columns": (
                            "Y"
                            if row_lower.get("insert_update_all_columns", "").upper()
                            == "Y"
                            else None
                        ),
                        "log_changes": (
                            "Y"
                            if row_lower.get("log_changes", "").upper() == "Y"
                            else None
                        ),
                        "maximum_cursor_size": row_lower.get("maximum_cursor_size", ""),
                        "multirecipient_select": (
                            "Y"
                            if row_lower.get("multirecipient_select", "").upper() == "Y"
                            else None
                        ),
                        "no_delete": (
                            "Y"
                            if row_lower.get("no_delete", "").upper() == "Y"
                            else None
                        ),
                        "no_insert": (
                            "Y"
                            if row_lower.get("no_insert", "").upper() == "Y"
                            else None
                        ),
                        "no_update": (
                            "Y"
                            if row_lower.get("no_update", "").upper() == "Y"
                            else None
                        ),
                        "no_merge": (
                            "Y"
                            if row_lower.get("no_merge", "").upper() == "Y"
                            else None
                        ),
                        "owner_delete": (
                            "Y"
                            if row_lower.get("owner_delete", "").upper() == "Y"
                            else None
                        ),
                        "placeholder": (
                            "Y"
                            if row_lower.get("placeholder", "").upper() == "Y"
                            else None
                        ),
                        "popup_visibility_auto_all": (
                            "Y"
                            if row_lower.get("popup_visibility_auto_all", "").upper()
                            == "Y"
                            else None
                        ),
                        "popup_visibility_type": row_lower.get(
                            "popup_visibility_type", ""
                        ),
                        "prefetch_size": row_lower.get("prefetch_size", ""),
                        "recipient_id_field": row_lower.get("recipient_id_field", "")
                        or row_lower.get("rec_id_fld_name", ""),
                        "reverse_fill_threshold": row_lower.get(
                            "reverse_fill_threshold", ""
                        )
                        or row_lower.get("rev_fill_thresh", ""),
                        "status_field": row_lower.get("status_field", ""),
                        "synonym_field": row_lower.get("synonym_field", ""),
                        "upgrade_ancestor": row_lower.get("upgrade_ancestor", ""),
                        "xa_attribute_value_bus_comp": row_lower.get(
                            "xa_attribute_value_bus_comp", ""
                        ),
                        "xa_class_id_field": row_lower.get("xa_class_id_field", ""),
                        "object_locked": (
                            "Y"
                            if row_lower.get("object_locked", "").upper() == "Y"
                            else None
                        ),
                        "object_language_locked": (
                            "Y"
                            if row_lower.get("object_language_locked", "").upper()
                            == "Y"
                            else None
                        ),
                        "project_id": row_lower.get("project_id", ""),
                        "class_id": row_lower.get("class_id", ""),
                        "row_id": row_lower.get("row_id", ""),
                    }

                    # Clean up empty strings, replace them with None for better SQL handling
                    for key, value in buscomp_data[name].items():
                        if value == "":
                            buscomp_data[name][key] = None

            return buscomp_data
    except Exception as e:
        print(f"Error reading business component source file: {e}")
        return {}


def read_tables_file(file_path):
    """Read the siebel tables CSV file and extract table id to name mapping."""
    if not os.path.exists(file_path):
        print(f"Warning: Siebel tables source file {file_path} not found.")
        return {}

    try:
        with open(file_path, "r", encoding="utf-8") as file:
            # Skip the first row if it contains the filepath comment
            first_line = file.readline().strip()
            if first_line.startswith("// filepath:"):
                # Reset file pointer to beginning and then skip the first line
                file.seek(0)
                next(file)
            else:
                # If it's not a comment, reset the file pointer to the beginning
                file.seek(0)

            reader = csv.DictReader(file)
            tables_data = {}
            for row in reader:
                name = row.get("NAME")
                table_id = row.get("ID")
                if name and table_id:
                    tables_data[name] = table_id

            return tables_data
    except Exception as e:
        print(f"Error reading tables source file: {e}")
        return {}


def match_business_components(
    field_templates, business_components, buscomp_data=None, tables_data=None
):
    """Match business components in field templates with their IDs from business_components."""
    bc_map = {
        bc["name"]: {"id": bc["id"], "table_id": bc.get("table_id")}
        for bc in business_components
    }

    # Create a mapping of table names to table IDs from the tables_data for easy lookup
    table_name_to_id = {}
    if tables_data:
        for name, table_id in tables_data.items():
            table_name_to_id[name.strip().upper()] = table_id

    # Create a mapping of table names in field templates for direct lookup
    field_table_names = {}
    for field in field_templates:
        if "table_name" in field and field["table_name"]:
            table_name = field["table_name"].strip().upper()
            if table_name:
                field_table_names[field["field_name"]] = table_name

    # Keep track of missing business components
    missing_bc = {}
    # Start with next ID in list to avoid conflicts with existing IDs
    next_bc_id = 11228

    for field in field_templates:
        bc_name = field.get("business_component")
        if not bc_name:
            field["business_component_id"] = None
            field["table_id"] = None
            continue

        if bc_name in bc_map:
            field["business_component_id"] = bc_map[bc_name]["id"]
            # Set table_id to None if it's empty or not present in the business component
            field["table_id"] = (
                bc_map[bc_name]["table_id"] if bc_map[bc_name]["table_id"] else None
            )
        else:
            print(
                f"Warning: Business component '{bc_name}' not found in business components file"
            )

            # Check if we've already assigned an ID to this missing BC
            if bc_name in missing_bc:
                bc_id = missing_bc[bc_name]["id"]
            else:
                # Assign a new ID and add to missing_bc dictionary
                bc_id = next_bc_id

                # Initialize default values
                bc_info = {
                    "id": bc_id,
                    "name": bc_name,
                    "changed": False,
                    "repository_name": "Siebel Repository",
                    "cache_data": "N",
                    "data_source": "",
                    "dirty_reads": "N",
                    "distinct": "N",
                    "enclosure_id_field": "",
                    "force_active": "N",
                    "gen_reassign_act": "N",
                    "hierarchy_parent_field": "",
                    "type": "Non-Transient",
                    "inactive": "N",
                    "insert_update_all_columns": "N",
                    "log_changes": "N",
                    "maximum_cursor_size": "",
                    "multirecipient_select": "N",
                    "no_delete": "N",
                    "no_insert": "N",
                    "no_update": "N",
                    "no_merge": "N",
                    "owner_delete": "N",
                    "placeholder": "N",
                    "popup_visibility_auto_all": "N",
                    "popup_visibility_type": "",
                    "prefetch_size": "",
                    "recipient_id_field": "",
                    "reverse_fill_threshold": "",
                    "scripted": 0,
                    "search_specification": "",
                    "sort_specification": "",
                    "status_field": "",
                    "synonym_field": "",
                    "upgrade_ancestor": "",
                    "xa_attribute_value_bus_comp": "",
                    "xa_class_id_field": "",
                    "comments": f"Auto-generated for missing component {bc_name}",
                    "object_locked": "N",
                    "object_language_locked": "N",
                    "project_id": "",
                    "class_id": "",
                    "table_id": "",
                    "table_name": "",
                    "class_name": "CSSBusComp",
                }

                # Override with real data if available from buscomp_data
                if buscomp_data and bc_name in buscomp_data:
                    # Create a mapping of buscomp_data fields to bc_info fields
                    field_mapping = {
                        "type": "type",
                        "scripted": "scripted",
                        "search_specification": "search_specification",
                        "sort_specification": "sort_specification",
                        "table_name": "table_name",
                        "class_name": "class_name",
                        "comments": "comments",
                        "cache_data": "cache_data",
                        "data_source": "data_source",
                        "dirty_reads": "dirty_reads",
                        "distinct": "distinct",
                        "enclosure_id_field": "enclosure_id_field",
                        "force_active": "force_active",
                        "gen_reassign_act": "gen_reassign_act",
                        "hierarchy_parent_field": "hierarchy_parent_field",
                        "inactive": "inactive",
                        "insert_update_all_columns": "insert_update_all_columns",
                        "log_changes": "log_changes",
                        "maximum_cursor_size": "maximum_cursor_size",
                        "multirecipient_select": "multirecipient_select",
                        "no_delete": "no_delete",
                        "no_insert": "no_insert",
                        "no_update": "no_update",
                        "no_merge": "no_merge",
                        "owner_delete": "owner_delete",
                        "placeholder": "placeholder",
                        "popup_visibility_auto_all": "popup_visibility_auto_all",
                        "popup_visibility_type": "popup_visibility_type",
                        "prefetch_size": "prefetch_size",
                        "recipient_id_field": "recipient_id_field",
                        "reverse_fill_threshold": "reverse_fill_threshold",
                        "status_field": "status_field",
                        "synonym_field": "synonym_field",
                        "upgrade_ancestor": "upgrade_ancestor",
                        "xa_attribute_value_bus_comp": "xa_attribute_value_bus_comp",
                        "xa_class_id_field": "xa_class_id_field",
                        "object_locked": "object_locked",
                        "object_language_locked": "object_language_locked",
                        "project_id": "project_id",
                        "class_id": "class_id",
                        "table_id": "table_id",
                        "row_id": "row_id",
                    }

                    # Update bc_info with values from buscomp_data where available
                    for bc_key, info_key in field_mapping.items():
                        if (
                            bc_key in buscomp_data[bc_name]
                            and buscomp_data[bc_name][bc_key] is not None
                        ):
                            bc_info[info_key] = buscomp_data[bc_name][bc_key]

                # If we have a table_name but no table_id, try to look up the table_id from tables_data
                if bc_info["table_name"] and not bc_info["table_id"] and tables_data:
                    table_name = bc_info["table_name"].strip().upper()
                    if table_name in tables_data:
                        bc_info["table_id"] = tables_data[table_name]
                        print(
                            f"Found table ID {bc_info['table_id']} for table {table_name} from tables data"
                        )

                missing_bc[bc_name] = bc_info
                next_bc_id += 1

            field["business_component_id"] = bc_id

            # Try multiple methods to get the table_id
            table_id = None

            # Method 1: Use missing_bc table_id if it exists
            if bc_name in missing_bc:
                table_id = missing_bc[bc_name]["table_id"]

                # Method 2: Try to look up table_id based on table_name in missing_bc
                if not table_id and missing_bc[bc_name]["table_name"] and tables_data:
                    table_name = missing_bc[bc_name]["table_name"].strip().upper()
                    if table_name in tables_data:
                        table_id = tables_data[table_name]

            # Method 3: Try to look up table_id based on table_name in the field template
            if (
                not table_id
                and field.get("field_name") in field_table_names
                and tables_data
            ):
                table_name = field_table_names[field.get("field_name")]
                if table_name in table_name_to_id:
                    table_id = table_name_to_id[table_name]
                    print(
                        f"Found table ID {table_id} for field {field.get('field_name')} from field table_name '{table_name}'"
                    )

            # Method 4: Try to look up table_id directly from field's table_name if it exists
            if (
                not table_id
                and "table_name" in field
                and field["table_name"]
                and tables_data
            ):
                table_name = field["table_name"].strip().upper()
                if table_name in tables_data:
                    table_id = tables_data[table_name]
                    print(
                        f"Found table ID {table_id} for field {field.get('field_name')} directly from field's table_name '{table_name}'"
                    )

                    # Also update missing_bc with this information for future reference
                    if bc_name in missing_bc and not missing_bc[bc_name]["table_id"]:
                        missing_bc[bc_name]["table_id"] = table_id
                        missing_bc[bc_name]["table_name"] = field["table_name"]

            field["table_id"] = table_id

    return field_templates, missing_bc


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


def generate_seeder_file(fields_data, file_number=1, max_entries=50000):
    """Generate a seeder file content based on the matched field data."""
    today = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Create class name with file number if > 1
    class_name = (
        "SiebelFieldsTableSeeder"
        if file_number == 1
        else f"SiebelFieldsTableSeeder{file_number}"
    )

    # Calculate start and end record IDs for this seeder file
    start_record = (file_number - 1) * max_entries + 1
    end_record = start_record + len(fields_data) - 1

    seeder_content = f"""<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;

class {class_name} extends Seeder
{{
    /**
     * Auto-generated seeder for Siebel Fields
     * Generated on {today}
     * Part {file_number} of {math.ceil(len(fields_data) / max_entries)}
     * Records {start_record} to {end_record}
     *
     * @return void
     */
    public function run()
    {{
        // Process records in batches to optimize memory usage
        $records = $this->getRecords();
        $now = now();

        // Use chunking to insert in batches
        foreach (array_chunk($records, 500) as $batch) {{
            DB::table('siebel_fields')->insert($batch);
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

    for i, field in enumerate(fields_data):
        # Make field name PHP-safe
        field_name = make_php_safe_string(field.get("field_name", ""))

        # Handle business_component_id specially to ensure NULL is used properly
        bc_id = field.get("business_component_id")
        bc_id_str = f"{bc_id}" if bc_id is not None else "null"

        # Handle table_id specially to ensure NULL is used properly when empty or None
        table_id = field.get("table_id")
        table_id_str = f"{table_id}" if table_id not in [None, "", "NULL"] else "null"

        # Generate the field entry
        seeder_content += "            [\n"
        seeder_content += f"                'id' => {start_record + i},\n"
        seeder_content += f"                'name' => '{field_name}',\n"
        seeder_content += f"                'business_component_id' => {bc_id_str},\n"
        seeder_content += f"                'table_id' => {table_id_str},\n"
        seeder_content += f"                'table_column' => '{make_php_safe_string(field.get('table_column', ''))}',\n"
        seeder_content += f"                'multi_value_link' => '{make_php_safe_string(field.get('multi_value_link', ''))}',\n"
        seeder_content += f"                'multi_value_link_field' => '{make_php_safe_string(field.get('multi_value_link_field', ''))}',\n"
        seeder_content += f"                'join' => '{make_php_safe_string(field.get('join', ''))}',\n"
        seeder_content += f"                'join_column' => '{make_php_safe_string(field.get('join_column', ''))}',\n"

        # Special handling for calculated_value to ensure it's PHP-safe
        calculated_value = make_php_safe_string(field.get("calculated_value", ""))
        seeder_content += (
            f"                'calculated_value' => '{calculated_value}',\n"
        )
        seeder_content += "                'created_at' => null,\n"
        seeder_content += "                'updated_at' => null,\n"
        seeder_content += (
            "            ]" + ("," if i < len(fields_data) - 1 else "") + "\n"
        )

    seeder_content += """        ];
    }
}
"""

    return seeder_content


def generate_business_components_seeder(missing_bc):
    """Generate a seeder file for missing business components."""
    if not missing_bc:
        return None

    today = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    seeder_content = f"""<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;
use Carbon\\Carbon;

class SiebelMissingBusinessComponentsSeeder extends Seeder
{{
    /**
     * Auto-generated seeder for missing Siebel Business Components
     * Generated on {today}
     * Total components: {len(missing_bc)}
     *
     * @return void
     */
    public function run()
    {{
        // Process records in batches to optimize memory usage
        $records = $this->getRecords();

        // Use chunking to insert in batches
        foreach (array_chunk($records, 100) as $batch) {{
            DB::table('siebel_business_components')->insert($batch);
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

    for i, (bc_name, bc_data) in enumerate(missing_bc.items()):
        # Escape single quotes in strings for PHP using our new function
        comments = make_php_safe_string(bc_data.get("comments", ""))
        search_spec = make_php_safe_string(bc_data.get("search_specification", ""))
        sort_spec = make_php_safe_string(bc_data.get("sort_specification", ""))
        repository_name = make_php_safe_string(
            bc_data.get("repository_name", "Siebel Repository")
        )

        seeder_content += "            [\n"
        seeder_content += f"                'id' => {bc_data['id']},\n"
        seeder_content += f"                'name' => '{bc_name}',\n"
        # Convert boolean to 0/1 instead of false/true
        seeder_content += (
            f"                'changed' => {1 if bc_data.get('changed') else 0},\n"
        )
        seeder_content += f"                'repository_name' => '{repository_name}',\n"

        # Add all fields from the schema, handling NULL values appropriately
        schema_fields = [
            "cache_data",
            "data_source",
            "dirty_reads",
            "distinct",
            "enclosure_id_field",
            "force_active",
            "gen_reassign_act",
            "hierarchy_parent_field",
            "type",
            "inactive",
            "insert_update_all_columns",
            "log_changes",
            "maximum_cursor_size",
            "multirecipient_select",
            "no_delete",
            "no_insert",
            "no_update",
            "no_merge",
            "owner_delete",
            "placeholder",
            "popup_visibility_auto_all",
            "popup_visibility_type",
            "prefetch_size",
            "recipient_id_field",
            "reverse_fill_threshold",
            "scripted",
            "search_specification",
            "sort_specification",
            "status_field",
            "synonym_field",
            "upgrade_ancestor",
            "xa_attribute_value_bus_comp",
            "xa_class_id_field",
            "comments",
            "object_locked",
            "object_language_locked",
            "class_id",
            "table_id",
        ]

        # Add non-required basic fields with appropriate handling for NULL values
        for field in schema_fields:
            if field in ["search_specification", "sort_specification", "comments"]:
                # Skip these as they're handled separately
                continue

            # Skip table_name and class_name as requested
            if field in ["table_name", "class_name"]:
                continue

            value = bc_data.get(field)

            # Format different types of values appropriately
            if field == "type":
                seeder_content += f"                '{field}' => '{value if value else 'Non-Transient'}',\n"
            elif field == "scripted":
                seeder_content += f"                '{field}' => {value if value is not None else 0},\n"
            elif field in [
                "cache_data",
                "dirty_reads",
                "distinct",
                "force_active",
                "gen_reassign_act",
                "inactive",
                "insert_update_all_columns",
                "log_changes",
                "multirecipient_select",
                "no_delete",
                "no_insert",
                "no_update",
                "no_merge",
                "owner_delete",
                "placeholder",
                "popup_visibility_auto_all",
                "object_locked",
                "object_language_locked",
            ]:
                # Boolean fields that use Y/N - convert to 1/0
                if value == "Y":
                    seeder_content += f"                '{field}' => 1,\n"
                elif value == "N":
                    seeder_content += f"                '{field}' => 0,\n"
                else:
                    seeder_content += f"                '{field}' => {0 if field in ['dirty_reads', 'log_changes'] else 'null'},\n"
            elif isinstance(value, bool):
                # Convert boolean to 0/1 instead of false/true
                seeder_content += f"                '{field}' => {1 if value else 0},\n"
            elif isinstance(value, int) or value is None:
                if value is None:
                    seeder_content += f"                '{field}' => null,\n"
                else:
                    seeder_content += f"                '{field}' => {value},\n"
            elif value:  # For string fields that are not empty
                escaped_value = str(value).replace("'", "\\'")
                seeder_content += f"                '{field}' => '{escaped_value}',\n"
            else:
                seeder_content += f"                '{field}' => null,\n"

        # Always set project_id to NULL as requested
        seeder_content += f"                'project_id' => null,\n"

        # Add the previously handled fields
        seeder_content += (
            f"                'search_specification' => '{search_spec}',\n"
        )
        seeder_content += f"                'sort_specification' => '{sort_spec}',\n"
        seeder_content += f"                'comments' => '{comments}',\n"

        # Add timestamps
        seeder_content += "                'updated_at' => null,\n"
        seeder_content += "                'created_at' => null,\n"
        seeder_content += (
            "            ]" + ("," if i < len(missing_bc) - 1 else "") + "\n"
        )

    seeder_content += """        ];
    }
}
"""
    return seeder_content


def split_and_generate_seeders(
    matched_fields, output_path_base, max_entries_per_file=2500
):
    """Split the matched fields into multiple files with a maximum number of entries per file."""
    # No longer filter out fields without a business component ID - include all fields
    valid_fields = matched_fields

    if not valid_fields:
        print("No fields found. No seeders generated.")
        return

    total_fields = len(valid_fields)
    total_files = math.ceil(total_fields / max_entries_per_file)

    print(f"Total fields: {total_fields}")
    print(
        f"Will generate {total_files} seeder files with max {max_entries_per_file} entries per file"
    )

    # Get the base filename and extension
    base_path, extension = os.path.splitext(output_path_base)

    file_paths = []
    for file_number in range(1, total_files + 1):
        start_idx = (file_number - 1) * max_entries_per_file
        end_idx = min(file_number * max_entries_per_file, total_fields)

        fields_batch = valid_fields[start_idx:end_idx]

        # Generate filename: either original name for first file or with number suffix
        if file_number == 1 and total_files == 1:
            file_path = output_path_base
        else:
            file_path = f"{base_path}{file_number}{extension}"

        seeder_content = generate_seeder_file(
            fields_batch, file_number, max_entries_per_file
        )

        print(
            f"Writing seeder {file_number} with {len(fields_batch)} entries to: {file_path}"
        )
        with open(file_path, "w", encoding="utf-8") as file:
            file.write(seeder_content)

        file_paths.append(file_path)

    # Generate a master seeder if we have multiple files
    if total_files > 1:
        generate_master_seeder(base_path + extension, total_files)

    return file_paths


def generate_master_seeder(base_path, total_files):
    """Generate a master seeder that calls all the individual seeders."""
    master_content = """<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;

class SiebelFieldsTableSeeder extends Seeder
{
    /**
     * Master seeder that calls all the individual seeders.
     *
     * @return void
     */
    public function run()
    {
        // Call each batch seeder to optimize memory usage
"""

    # Add calls to each individual seeder
    for i in range(1, total_files + 1):
        class_name = f"SiebelFieldsTableSeeder{i}"
        master_content += f"        $this->call({class_name}::class);\n"

    master_content += """    }
}
"""

    print(f"Writing master seeder to: {base_path}")
    with open(base_path, "w", encoding="utf-8") as file:
        file.write(master_content)


def main():
    if len(sys.argv) < 3:
        print(
            "Usage: python generate_siebel_fields_seeder.py <ICM_FIELDS.csv> <siebel_business_components.csv> [output_file] [max_entries_per_file] [s_buscomp_file.csv] [siebel_tables_file.csv]"
        )
        sys.exit(1)

    icm_field_template_path = sys.argv[1]
    business_components_path = sys.argv[2]
    output_path = sys.argv[3] if len(sys.argv) > 3 else "SiebelFieldsTableSeeder.php"
    max_entries_per_file = int(sys.argv[4]) if len(sys.argv) > 4 else 50000
    s_buscomp_path = sys.argv[5] if len(sys.argv) > 5 else "./s_buscomp.csv"
    tables_path = sys.argv[6] if len(sys.argv) > 6 else "./siebel_tables.csv"

    # Determine output directory from output_path
    output_dir = os.path.dirname(output_path)
    if not output_dir:
        output_dir = "."

    print(f"Reading ICM field template from: {icm_field_template_path}")
    field_templates = read_csv_file(icm_field_template_path)

    print(f"Reading business components from: {business_components_path}")
    business_components = read_csv_file(business_components_path)

    print(f"Reading business components source data from: {s_buscomp_path}")
    buscomp_data = read_buscomp_file(s_buscomp_path)
    if buscomp_data:
        print(f"Found {len(buscomp_data)} business components in source file")
    else:
        print("No business components source data available or file not found")

    print(f"Reading Siebel tables data from: {tables_path}")
    tables_data = read_tables_file(tables_path)  # Use the new function here
    if tables_data:
        print(f"Found {len(tables_data)} tables in source file")
    else:
        print("No tables data available or file not found")

    print("Matching business components with fields...")
    matched_fields, missing_bc = match_business_components(
        field_templates, business_components, buscomp_data, tables_data
    )

    print(f"Generating seeders with max {max_entries_per_file} entries per file...")
    files = split_and_generate_seeders(
        matched_fields, output_path, max_entries_per_file
    )

    # Generate missing business components seeder if needed
    if missing_bc:
        missing_bc_count = len(missing_bc)
        print(f"Found {missing_bc_count} missing business components")
        missing_bc_seeder = generate_business_components_seeder(missing_bc)
        missing_bc_path = os.path.join(
            output_dir, "SiebelMissingBusinessComponentsSeeder.php"
        )

        with open(missing_bc_path, "w", encoding="utf-8") as file:
            file.write(missing_bc_seeder)

        print(f"Written missing business components seeder to: {missing_bc_path}")
        files.append(missing_bc_path)

    print("All seeder files generated successfully!")
    for file_path in files:
        print(f"- {file_path}")


if __name__ == "__main__":
    main()
