-- Package Body: Anonymization Data: States/Provinces
-- US states and Canadian provinces
-- Data Count: 83

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_STATES AS

  -- Data array type
  TYPE T_ANON_STATES IS TABLE OF VARCHAR2(30) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_STATES;
  C_COUNT CONSTANT NUMBER := 83;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := 'AB';
    g_data(2) := 'AK';
    g_data(3) := 'AL';
    g_data(4) := 'AR';
    g_data(5) := 'AS';
    g_data(6) := 'AZ';
    g_data(7) := 'Alabama';
    g_data(8) := 'Alaska';
    g_data(9) := 'Alberta';
    g_data(10) := 'Arizona';
    g_data(11) := 'BC';
    g_data(12) := 'British Columbia';
    g_data(13) := 'CA';
    g_data(14) := 'CO';
    g_data(15) := 'CT';
    g_data(16) := 'California';
    g_data(17) := 'Colorado';
    g_data(18) := 'DC';
    g_data(19) := 'DE';
    g_data(20) := 'FL';
    g_data(21) := 'Florida';
    g_data(22) := 'GA';
    g_data(23) := 'GU';
    g_data(24) := 'Georgia';
    g_data(25) := 'HI';
    g_data(26) := 'IA';
    g_data(27) := 'ID';
    g_data(28) := 'IL';
    g_data(29) := 'IN';
    g_data(30) := 'Illinois';
    g_data(31) := 'KS';
    g_data(32) := 'KY';
    g_data(33) := 'LA';
    g_data(34) := 'MA';
    g_data(35) := 'MB';
    g_data(36) := 'MD';
    g_data(37) := 'ME';
    g_data(38) := 'MI';
    g_data(39) := 'MN';
    g_data(40) := 'MO';
    g_data(41) := 'MP';
    g_data(42) := 'MS';
    g_data(43) := 'MT';
    g_data(44) := 'NB';
    g_data(45) := 'NC';
    g_data(46) := 'ND';
    g_data(47) := 'NE';
    g_data(48) := 'NH';
    g_data(49) := 'NJ';
    g_data(50) := 'NL';
    g_data(51) := 'NM';
    g_data(52) := 'NS';
    g_data(53) := 'NT';
    g_data(54) := 'NU';
    g_data(55) := 'NV';
    g_data(56) := 'NY';
    g_data(57) := 'New York';
    g_data(58) := 'OH';
    g_data(59) := 'OK';
    g_data(60) := 'ON';
    g_data(61) := 'OR';
    g_data(62) := 'Ontario';
    g_data(63) := 'PA';
    g_data(64) := 'PE';
    g_data(65) := 'PR';
    g_data(66) := 'QC';
    g_data(67) := 'Quebec';
    g_data(68) := 'RI';
    g_data(69) := 'SC';
    g_data(70) := 'SD';
    g_data(71) := 'SK';
    g_data(72) := 'TN';
    g_data(73) := 'TX';
    g_data(74) := 'Texas';
    g_data(75) := 'UT';
    g_data(76) := 'VA';
    g_data(77) := 'VI';
    g_data(78) := 'VT';
    g_data(79) := 'WA';
    g_data(80) := 'WI';
    g_data(81) := 'WV';
    g_data(82) := 'WY';
    g_data(83) := 'YT';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_STATES(
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
  END GET_STATES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_STATES_RANDOM(
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_STATES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_STATES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 30
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 30));
  END GET_STATES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
END PKG_ANON_STATES;
/