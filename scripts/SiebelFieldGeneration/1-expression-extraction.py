import pandas as pd
import re
import os


def extract_field_references(expression):
    """Extract all field references in square brackets from an expression."""
    if pd.isna(expression):
        return []

    # Standard pattern for field references in square brackets
    pattern1 = r"\[([^\]]+)\]"
    matches1 = re.findall(pattern1, str(expression))

    # Pattern for field references in ParentFieldValue calls
    pattern2 = r'ParentFieldValue\s*\(\s*["\']([^"\']+)["\']'
    matches2 = re.findall(pattern2, str(expression))

    # Remove duplicates while preserving order
    return list(dict.fromkeys(matches1 + matches2))


def extract_lookup_values(expression):
    """Extract all lookup values (uppercase strings in quotes) from an expression."""
    if pd.isna(expression):
        return []

    # Pattern to match LookupName, LookupValue, or LookupExpr functions with their arguments
    lookup_pattern = r'(?:LookupName|LookupValue|LookupExpr)\s*\(\s*"([A-Z0-9_]+)"\s*,'
    matches = re.findall(lookup_pattern, str(expression))

    # Remove duplicates while preserving order
    return list(dict.fromkeys(matches))


def extract_functions(expression):
    """Extract all function names from an expression."""
    if pd.isna(expression):
        return []

    # Pattern to match function names
    function_pattern = r"([A-Za-z0-9_]+)\s*\("
    matches = re.findall(function_pattern, str(expression))

    # Filter out common lookup functions
    filtered_matches = [
        match
        for match in matches
        if match not in ["LookupName", "LookupValue", "LookupExpr"]
    ]

    # Remove duplicates while preserving order
    return list(dict.fromkeys(filtered_matches))


def extract_business_components(expression):
    """Extract all business component references from an expression."""
    if pd.isna(expression):
        return []

    # Collection of patterns to match different business component references
    patterns = [
        # ParentBCName() = "BC Name" or ParentBCName()="BC Name"
        r'ParentBCName\(\)\s*=\s*["\']([^"\']+)["\']',
        # GetBusComp("BC Name")
        r'GetBusComp\s*\(\s*["\']([^"\']+)["\']',
        # BCHasRows("BC Name")
        r'BCHasRows\s*\(\s*["\']([^"\']+)["\']',
        # GetNumBCRows("BC Name")
        r'GetNumBCRows\s*\(\s*["\']([^"\']+)["\']',
        # FindRecord("BC Name")
        r'FindRecord\s*\(\s*["\']([^"\']+)["\']',
        # FirstRecord("BC Name")
        r'FirstRecord\s*\(\s*["\']([^"\']+)["\']',
        # NextRecord("BC Name")
        r'NextRecord\s*\(\s*["\']([^"\']+)["\']',
        # Business Component referenced directly (e.g. in ParentBCName() calls)
        r'ParentBCName\(\)\s*=\s*["\']([^"\']+)["\']',
    ]

    all_matches = []
    for pattern in patterns:
        matches = re.findall(pattern, str(expression))
        all_matches.extend(matches)

    # Get BC name from ParentBCName() without comparison
    # For cases where the BC name is determined dynamically
    if "ParentBCName()" in str(expression) and not any(
        "ParentBCName() =" in str(expression) for pattern in patterns
    ):
        all_matches.append("Dynamic BC (ParentBCName)")

    # Remove duplicates while preserving order
    return list(dict.fromkeys(all_matches))


def extract_operators(expression):
    """Extract operators used in expressions."""
    if pd.isna(expression):
        return []

    # Common operators
    operators = [
        "+",
        "-",
        "*",
        "/",
        "=",
        "<>",
        ">",
        "<",
        ">=",
        "<=",
        "AND",
        "OR",
        "NOT",
        "IS NULL",
        "IS NOT NULL",
        "LIKE",
    ]
    found_operators = []

    for op in operators:
        if op in str(expression):
            found_operators.append(op)

    # Remove duplicates while preserving order
    return list(dict.fromkeys(found_operators))


def extract_constants(expression):
    """Extract constant values like numbers and strings."""
    if pd.isna(expression):
        return []

    # Pattern for quoted strings not part of function calls
    string_pattern = r'(?<![A-Za-z0-9_])"([^"]*)"(?!\s*,)'
    string_matches = re.findall(string_pattern, str(expression))

    # Pattern for numbers
    number_pattern = r"(?<![A-Za-z0-9_])(\d+(?:\.\d+)?)(?![A-Za-z0-9_])"
    number_matches = re.findall(number_pattern, str(expression))

    # Remove duplicates while preserving order
    return list(dict.fromkeys(string_matches + number_matches))


def identify_expression_type(expression):
    """Identify the general type of expression."""
    if pd.isna(expression):
        return "Empty"

    expression = str(expression)

    if "IIF" in expression or "IIf" in expression:
        return "Conditional"
    elif any(op in expression for op in ["+", "-", "*", "/"]):
        return "Arithmetic"
    elif any(
        op in expression for op in ["=", "<>", ">", "<", ">=", "<=", "AND", "OR", "NOT"]
    ):
        return "Logical"
    elif "Lookup" in expression:
        return "Lookup"
    elif "+" in expression and '"' in expression:
        return "String Concatenation"
    elif (
        "ParentBCName()" in expression
        or "GetBusComp" in expression
        or "BCHasRows" in expression
    ):
        return "Business Component Operation"
    elif "ParentFieldValue" in expression:
        return "Parent Field Access"
    else:
        return "Other"


def join_unique_items(items):
    """Join items into a comma-separated string, ensuring no duplicates."""
    if not items:
        return None
    # Convert to strings and remove empties
    items = [str(item) for item in items if item]
    if not items:
        return None
    return ", ".join(list(dict.fromkeys(items)))


def main():
    # Define file paths
    script_dir = os.path.dirname(os.path.abspath(__file__))
    input_file = os.path.join(script_dir, "ICM_FIELDS.csv")
    output_file = os.path.join(script_dir, "ICM_FIELDS_ANALYZED.csv")

    # Read the CSV file
    df = pd.read_csv(input_file)

    # Process expressions in calculated_value column
    df["field_references"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_field_references(x))
    )

    df["list_of_values"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_lookup_values(x))
    )

    df["functions_used"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_functions(x))
    )

    df["operators_used"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_operators(x))
    )

    df["constants"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_constants(x))
    )

    df["referenced_business_components"] = df["calculated_value"].apply(
        lambda x: join_unique_items(extract_business_components(x))
    )

    df["expression_type"] = df["calculated_value"].apply(identify_expression_type)

    # Count the number of field references
    df["field_reference_count"] = df["calculated_value"].apply(
        lambda x: len(extract_field_references(x)) if not pd.isna(x) else 0
    )

    # Save the results
    df.to_csv(output_file, index=False)
    print(f"Analysis complete. Results saved to {output_file}")


if __name__ == "__main__":
    main()
