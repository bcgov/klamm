-- Package Specification: Anonymization Data: First Names
-- Synthetic first names for PII masking
-- Data Count: 364

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_FIRST_NAMES AS
  /*
   * First Names
   * Synthetic first names for PII masking
   *
   * Contains 364 unique entries.
   * Maximum length: 50 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_FIRST_NAMES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_FIRST_NAMES_RANDOM(
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_FIRST_NAMES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_FIRST_NAMES;
/