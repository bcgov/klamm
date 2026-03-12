-- Package Specification: Anonymization Data: Priority Values
-- Common Siebel priority values
-- Data Count: 9

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_PRIORITIES AS
  /*
   * Priority Values
   * Common Siebel priority values
   *
   * Contains 9 unique entries.
   * Maximum length: 20 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_PRIORITIES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_PRIORITIES_RANDOM(
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_PRIORITIES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_PRIORITIES;
/