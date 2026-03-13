-- Package Specification: Anonymization Data: States/Provinces
-- US states and Canadian provinces
-- Data Count: 83

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_STATES AS
  /*
   * States/Provinces
   * US states and Canadian provinces
   *
   * Contains 83 unique entries.
   * Maximum length: 30 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_STATES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_STATES_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_STATES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_STATES;
/