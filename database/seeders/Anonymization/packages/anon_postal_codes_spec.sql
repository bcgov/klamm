-- Package Specification: Anonymization Data: Postal Codes
-- Synthetic US ZIP and Canadian postal codes
-- Data Count: 10000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_POSTAL_CODES AS
  /*
   * Postal Codes
   * Synthetic US ZIP and Canadian postal codes
   *
   * Contains 10000 unique entries.
   * Maximum length: 30 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_POSTAL_CODES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_POSTAL_CODES_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_POSTAL_CODES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_POSTAL_CODES;
/