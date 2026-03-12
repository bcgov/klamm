-- Package Specification: Anonymization Data: Phone Numbers
-- Synthetic phone numbers using reserved exchanges
-- Data Count: 10000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_PHONES AS
  /*
   * Phone Numbers
   * Synthetic phone numbers using reserved exchanges
   *
   * Contains 10000 unique entries.
   * Maximum length: 40 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_PHONES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 40
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_PHONES_RANDOM(
    p_max_len IN NUMBER DEFAULT 40
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_PHONES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 40
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_PHONES;
/