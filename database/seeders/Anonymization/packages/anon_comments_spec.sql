-- Package Specification: Anonymization Data: Comment Text
-- Lorem ipsum placeholder text for notes/comments
-- Data Count: 5000

CREATE OR REPLACE PACKAGE ANON_DATA.PKG_ANON_COMMENTS AS
  /*
   * Comment Text
   * Lorem ipsum placeholder text for notes/comments
   *
   * Contains 5000 unique entries.
   * Maximum length: 255 characters.
   * Compatible with: VARCHAR2, CHAR, NVARCHAR2, CLOB
   */

  -- Get a deterministic entry based on a hash seed
  FUNCTION GET_COMMENTS(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 255
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get a random entry (non-deterministic)
  FUNCTION GET_COMMENTS_RANDOM(
    p_max_len IN NUMBER DEFAULT 255
  ) RETURN VARCHAR2;

  -- Get entry by index (1-based)
  FUNCTION GET_COMMENTS_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 255
  ) RETURN VARCHAR2 DETERMINISTIC;

  -- Get total count of entries
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC;

END PKG_ANON_COMMENTS;
/