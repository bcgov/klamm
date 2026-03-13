-- Package Specification: Anonymization Data: Job Titles
-- Synthetic job titles
-- Data Count: 639

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_JOB_TITLES AS
  /*
   * Job Titles
   * Synthetic job titles
   *
   * Contains 639 unique entries.
   * Maximum length: 75 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_JOB_TITLES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 75
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_JOB_TITLES_RANDOM(
    p_max_len IN NUMBER DEFAULT 75
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_JOB_TITLES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 75
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_JOB_TITLES;
/