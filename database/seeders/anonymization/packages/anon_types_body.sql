-- Package Body: Anonymization Data: Type Values
-- Common Siebel entity type values
-- Data Count: 13

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_TYPES AS

  -- Data array type
  TYPE T_ANON_TYPES IS TABLE OF VARCHAR2(30) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_TYPES;
  C_COUNT CONSTANT NUMBER := 13;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := 'Analyst';
    g_data(2) := 'Competitor';
    g_data(3) := 'Customer';
    g_data(4) := 'Household';
    g_data(5) := 'Individual';
    g_data(6) := 'Investor';
    g_data(7) := 'Organization';
    g_data(8) := 'Other';
    g_data(9) := 'Partner';
    g_data(10) := 'Press';
    g_data(11) := 'Prospect';
    g_data(12) := 'Reseller';
    g_data(13) := 'Vendor';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_TYPES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_hash RAW(32);
    v_idx NUMBER;
  BEGIN
    v_hash := DBMS_CRYPTO.HASH(
      UTL_RAW.CAST_TO_RAW(NVL(p_seed, 'NULL')),
      DBMS_CRYPTO.HASH_SH256
    );
    v_idx := MOD(TO_NUMBER(SUBSTR(RAWTOHEX(v_hash), 1, 8), 'XXXXXXXX'), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_TYPES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_TYPES_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_TYPES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_TYPES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_TYPES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
END PKG_ANON_TYPES;
/