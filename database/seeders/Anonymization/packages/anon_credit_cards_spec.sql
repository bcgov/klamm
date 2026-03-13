-- Package Specification: Anonymization Data: Credit Card Numbers (Masked)
-- Masked credit card number surrogates (non-functional)
-- Data Count: 10000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_CREDIT_CARDS AS
  /*
   * Credit Card Numbers (Masked)
   * Masked credit card number surrogates (non-functional)
   *
   * Contains 10000 unique entries.
   * Maximum length: 30 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_CREDIT_CARDS(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_CREDIT_CARDS_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_CREDIT_CARDS_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_CREDIT_CARDS;
/