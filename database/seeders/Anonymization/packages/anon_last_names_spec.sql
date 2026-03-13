-- Package Specification: Anonymization Data: Last Names
-- Synthetic last names for PII masking
-- Data Count: 1000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_LAST_NAMES AS
  /*
   * Last Names
   * Synthetic last names for PII masking
   *
   * Contains 1000 unique entries.
   * Maximum length: 50 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_LAST_NAMES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_LAST_NAMES_RANDOM(
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_LAST_NAMES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_LAST_NAMES;
/