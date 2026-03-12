-- Package Body: Anonymization Data: Status Values
-- Common Siebel status values
-- Data Count: 20

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_STATUSES AS

  -- Data array type
  TYPE T_ANON_STATUSES IS TABLE OF VARCHAR2(30) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_STATUSES;
  C_COUNT CONSTANT NUMBER := 20;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := 'Active';
    g_data(2) := 'Approved';
    g_data(3) := 'Archived';
    g_data(4) := 'Cancelled';
    g_data(5) := 'Closed';
    g_data(6) := 'Completed';
    g_data(7) := 'Converted';
    g_data(8) := 'Draft';
    g_data(9) := 'Expired';
    g_data(10) := 'In Progress';
    g_data(11) := 'Inactive';
    g_data(12) := 'New';
    g_data(13) := 'On Hold';
    g_data(14) := 'Open';
    g_data(15) := 'Pending';
    g_data(16) := 'Qualified';
    g_data(17) := 'Rejected';
    g_data(18) := 'Submitted';
    g_data(19) := 'Suspended';
    g_data(20) := 'Unqualified';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_STATUSES(
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
  END GET_STATUSES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_STATUSES_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_STATUSES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_STATUSES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_STATUSES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
END PKG_ANON_STATUSES;
/