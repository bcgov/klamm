-- Package Specification: Anonymization Data: Street Addresses
-- Synthetic street addresses
-- Data Count: 10000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_ADDRESSES AS
  /*
   * Street Addresses
   * Synthetic street addresses
   *
   * Contains 10000 unique entries.
   * Maximum length: 200 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2, CLOB
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_ADDRESSES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 200
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_ADDRESSES_RANDOM(
    p_max_len IN NUMBER DEFAULT 200
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_ADDRESSES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 200
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_ADDRESSES;
/