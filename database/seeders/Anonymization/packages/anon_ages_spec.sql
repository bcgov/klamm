-- Package Specification: Anonymization Data: Ages
-- Valid age values (18-99)
-- Data Count: 82

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_AGES AS
  /*
   * Ages
   * Valid age values (18-99)
   *
   * Contains 82 unique entries.
   * Maximum length: 3 characters.
   * Compatible with: NUMBER, VARCHAR2, CHAR
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_AGES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_AGES_RANDOM(
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_AGES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_AGES;
/