-- Package Body: Anonymization Data: Ages
-- Valid age values (18-99)
-- Data Count: 82

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_AGES AS

  -- Data array type
  TYPE T_ANON_AGES IS TABLE OF VARCHAR2(3) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_AGES;
  C_COUNT CONSTANT NUMBER := 82;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := '18';
    g_data(2) := '19';
    g_data(3) := '20';
    g_data(4) := '21';
    g_data(5) := '22';
    g_data(6) := '23';
    g_data(7) := '24';
    g_data(8) := '25';
    g_data(9) := '26';
    g_data(10) := '27';
    g_data(11) := '28';
    g_data(12) := '29';
    g_data(13) := '30';
    g_data(14) := '31';
    g_data(15) := '32';
    g_data(16) := '33';
    g_data(17) := '34';
    g_data(18) := '35';
    g_data(19) := '36';
    g_data(20) := '37';
    g_data(21) := '38';
    g_data(22) := '39';
    g_data(23) := '40';
    g_data(24) := '41';
    g_data(25) := '42';
    g_data(26) := '43';
    g_data(27) := '44';
    g_data(28) := '45';
    g_data(29) := '46';
    g_data(30) := '47';
    g_data(31) := '48';
    g_data(32) := '49';
    g_data(33) := '50';
    g_data(34) := '51';
    g_data(35) := '52';
    g_data(36) := '53';
    g_data(37) := '54';
    g_data(38) := '55';
    g_data(39) := '56';
    g_data(40) := '57';
    g_data(41) := '58';
    g_data(42) := '59';
    g_data(43) := '60';
    g_data(44) := '61';
    g_data(45) := '62';
    g_data(46) := '63';
    g_data(47) := '64';
    g_data(48) := '65';
    g_data(49) := '66';
    g_data(50) := '67';
    g_data(51) := '68';
    g_data(52) := '69';
    g_data(53) := '70';
    g_data(54) := '71';
    g_data(55) := '72';
    g_data(56) := '73';
    g_data(57) := '74';
    g_data(58) := '75';
    g_data(59) := '76';
    g_data(60) := '77';
    g_data(61) := '78';
    g_data(62) := '79';
    g_data(63) := '80';
    g_data(64) := '81';
    g_data(65) := '82';
    g_data(66) := '83';
    g_data(67) := '84';
    g_data(68) := '85';
    g_data(69) := '86';
    g_data(70) := '87';
    g_data(71) := '88';
    g_data(72) := '89';
    g_data(73) := '90';
    g_data(74) := '91';
    g_data(75) := '92';
    g_data(76) := '93';
    g_data(77) := '94';
    g_data(78) := '95';
    g_data(79) := '96';
    g_data(80) := '97';
    g_data(81) := '98';
    g_data(82) := '99';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_AGES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_hash RAW(32);
    v_idx NUMBER;
  BEGIN
    v_hash := DBMS_CRYPTO.HASH(
      UTL_RAW.CAST_TO_RAW(NVL(p_seed, 'NULL')),
      DBMS_CRYPTO.HASH_SH256
    );
    v_idx := MOD(TO_NUMBER(SUBSTR(RAWTOHEX(v_hash), 1, 8), 'XXXXXXXX'), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 3));
  END GET_AGES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_AGES_RANDOM(
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 3));
  END GET_AGES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_AGES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 3
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 3));
  END GET_AGES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
END PKG_ANON_AGES;
/