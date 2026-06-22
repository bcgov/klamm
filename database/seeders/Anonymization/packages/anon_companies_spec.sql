-- Package Specification: Anonymization Data: Company Names
-- Synthetic company/organization names
-- Data Count: 5000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_COMPANIES AS
  /*
   * Company Names
   * Synthetic company/organization names
   *
   * Contains 5000 unique entries.
   * Maximum length: 100 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_COMPANIES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 100
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_COMPANIES_RANDOM(
    p_max_len IN NUMBER DEFAULT 100
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_COMPANIES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 100
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_COMPANIES;
/