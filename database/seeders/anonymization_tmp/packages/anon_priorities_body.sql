-- Package Body: Anonymization Data: Priority Values
-- Common Siebel priority values
-- Data Count: 9

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_PRIORITIES AS

  -- Data array type
  TYPE T_ANON_PRIORITIES IS TABLE OF VARCHAR2(20) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_PRIORITIES;
  C_COUNT CONSTANT NUMBER := 9;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := '1-ASAP';
    g_data(2) := '2-High';
    g_data(3) := '3-Medium';
    g_data(4) := '4-Low';
    g_data(5) := 'Critical';
    g_data(6) := 'High';
    g_data(7) := 'Low';
    g_data(8) := 'Medium';
    g_data(9) := 'Urgent';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_PRIORITIES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_hash RAW(32);
    v_idx NUMBER;
  BEGIN
    v_hash := DBMS_CRYPTO.HASH(
      UTL_RAW.CAST_TO_RAW(NVL(p_seed, 'NULL')),
      DBMS_CRYPTO.HASH_SH256
    );
    v_idx := MOD(TO_NUMBER(SUBSTR(RAWTOHEX(v_hash), 1, 8), 'XXXXXXXX'), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 20));
  END GET_PRIORITIES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_PRIORITIES_RANDOM(
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 20));
  END GET_PRIORITIES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_PRIORITIES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 20
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 20));
  END GET_PRIORITIES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
END PKG_ANON_PRIORITIES;
/