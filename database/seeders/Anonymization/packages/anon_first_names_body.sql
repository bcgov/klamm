-- Package Body: Anonymization Data: First Names
-- Synthetic first names for PII masking
-- Data Count: 364

CREATE OR REPLACE PACKAGE BODY ANON_DATA.PKG_ANON_FIRST_NAMES AS

  -- Data array type
  TYPE T_ANON_FIRST_NAMES IS TABLE OF VARCHAR2(50) INDEX BY PLS_INTEGER;

  -- Embedded data
  g_data T_ANON_FIRST_NAMES;
  C_COUNT CONSTANT NUMBER := 364;

  -- Initialize data array
  PROCEDURE init_data IS
  BEGIN
    g_data(1) := 'Aaron';
    g_data(2) := 'Abbie';
    g_data(3) := 'Abdul';
    g_data(4) := 'Abigail';
    g_data(5) := 'Adam';
    g_data(6) := 'Adrian';
    g_data(7) := 'Aimee';
    g_data(8) := 'Alan';
    g_data(9) := 'Albert';
    g_data(10) := 'Alex';
    g_data(11) := 'Alexander';
    g_data(12) := 'Alexandra';
    g_data(13) := 'Alice';
    g_data(14) := 'Alison';
    g_data(15) := 'Allan';
    g_data(16) := 'Amanda';
    g_data(17) := 'Amber';
    g_data(18) := 'Amelia';
    g_data(19) := 'Amy';
    g_data(20) := 'Andrea';
    g_data(21) := 'Andrew';
    g_data(22) := 'Angela';
    g_data(23) := 'Ann';
    g_data(24) := 'Anna';
    g_data(25) := 'Anne';
    g_data(26) := 'Annette';
    g_data(27) := 'Anthony';
    g_data(28) := 'Antony';
    g_data(29) := 'Arthur';
    g_data(30) := 'Ashleigh';
    g_data(31) := 'Ashley';
    g_data(32) := 'Barbara';
    g_data(33) := 'Barry';
    g_data(34) := 'Ben';
    g_data(35) := 'Benjamin';
    g_data(36) := 'Bernard';
    g_data(37) := 'Beth';
    g_data(38) := 'Bethan';
    g_data(39) := 'Bethany';
    g_data(40) := 'Beverley';
    g_data(41) := 'Billy';
    g_data(42) := 'Bradley';
    g_data(43) := 'Brandon';
    g_data(44) := 'Brenda';
    g_data(45) := 'Brett';
    g_data(46) := 'Brian';
    g_data(47) := 'Bruce';
    g_data(48) := 'Bryan';
    g_data(49) := 'Callum';
    g_data(50) := 'Cameron';
    g_data(51) := 'Carl';
    g_data(52) := 'Carly';
    g_data(53) := 'Carol';
    g_data(54) := 'Carole';
    g_data(55) := 'Caroline';
    g_data(56) := 'Carolyn';
    g_data(57) := 'Catherine';
    g_data(58) := 'Charlene';
    g_data(59) := 'Charles';
    g_data(60) := 'Charlie';
    g_data(61) := 'Charlotte';
    g_data(62) := 'Chelsea';
    g_data(63) := 'Cheryl';
    g_data(64) := 'Chloe';
    g_data(65) := 'Christian';
    g_data(66) := 'Christine';
    g_data(67) := 'Christopher';
    g_data(68) := 'Claire';
    g_data(69) := 'Clare';
    g_data(70) := 'Clifford';
    g_data(71) := 'Clive';
    g_data(72) := 'Colin';
    g_data(73) := 'Connor';
    g_data(74) := 'Conor';
    g_data(75) := 'Craig';
    g_data(76) := 'Dale';
    g_data(77) := 'Damian';
    g_data(78) := 'Damien';
    g_data(79) := 'Daniel';
    g_data(80) := 'Danielle';
    g_data(81) := 'Danny';
    g_data(82) := 'Darren';
    g_data(83) := 'David';
    g_data(84) := 'Dawn';
    g_data(85) := 'Dean';
    g_data(86) := 'Deborah';
    g_data(87) := 'Debra';
    g_data(88) := 'Declan';
    g_data(89) := 'Denis';
    g_data(90) := 'Denise';
    g_data(91) := 'Dennis';
    g_data(92) := 'Derek';
    g_data(93) := 'Diana';
    g_data(94) := 'Diane';
    g_data(95) := 'Dominic';
    g_data(96) := 'Donald';
    g_data(97) := 'Donna';
    g_data(98) := 'Dorothy';
    g_data(99) := 'Douglas';
    g_data(100) := 'Duncan';
  END;

  PROCEDURE init_data_2 IS
  BEGIN
    g_data(101) := 'Dylan';
    g_data(102) := 'Edward';
    g_data(103) := 'Eileen';
    g_data(104) := 'Elaine';
    g_data(105) := 'Eleanor';
    g_data(106) := 'Elizabeth';
    g_data(107) := 'Ellie';
    g_data(108) := 'Elliot';
    g_data(109) := 'Elliott';
    g_data(110) := 'Emily';
    g_data(111) := 'Emma';
    g_data(112) := 'Eric';
    g_data(113) := 'Fiona';
    g_data(114) := 'Frances';
    g_data(115) := 'Francesca';
    g_data(116) := 'Francis';
    g_data(117) := 'Frank';
    g_data(118) := 'Frederick';
    g_data(119) := 'Gail';
    g_data(120) := 'Gareth';
    g_data(121) := 'Garry';
    g_data(122) := 'Gary';
    g_data(123) := 'Gavin';
    g_data(124) := 'Gemma';
    g_data(125) := 'Geoffrey';
    g_data(126) := 'George';
    g_data(127) := 'Georgia';
    g_data(128) := 'Georgina';
    g_data(129) := 'Gerald';
    g_data(130) := 'Geraldine';
    g_data(131) := 'Gerard';
    g_data(132) := 'Gillian';
    g_data(133) := 'Glen';
    g_data(134) := 'Glenn';
    g_data(135) := 'Gordon';
    g_data(136) := 'Grace';
    g_data(137) := 'Graeme';
    g_data(138) := 'Graham';
    g_data(139) := 'Gregory';
    g_data(140) := 'Guy';
    g_data(141) := 'Hannah';
    g_data(142) := 'Harriet';
    g_data(143) := 'Harry';
    g_data(144) := 'Hayley';
    g_data(145) := 'Hazel';
    g_data(146) := 'Heather';
    g_data(147) := 'Helen';
    g_data(148) := 'Henry';
    g_data(149) := 'Hilary';
    g_data(150) := 'Hollie';
    g_data(151) := 'Holly';
    g_data(152) := 'Howard';
    g_data(153) := 'Hugh';
    g_data(154) := 'Iain';
    g_data(155) := 'Ian';
    g_data(156) := 'Irene';
    g_data(157) := 'Jack';
    g_data(158) := 'Jacob';
    g_data(159) := 'Jacqueline';
    g_data(160) := 'Jade';
    g_data(161) := 'Jake';
    g_data(162) := 'James';
    g_data(163) := 'Jamie';
    g_data(164) := 'Jane';
    g_data(165) := 'Janet';
    g_data(166) := 'Janice';
    g_data(167) := 'Jasmine';
    g_data(168) := 'Jason';
    g_data(169) := 'Jay';
    g_data(170) := 'Jayne';
    g_data(171) := 'Jean';
    g_data(172) := 'Jeffrey';
    g_data(173) := 'Jemma';
    g_data(174) := 'Jenna';
    g_data(175) := 'Jennifer';
    g_data(176) := 'Jeremy';
    g_data(177) := 'Jessica';
    g_data(178) := 'Jill';
    g_data(179) := 'Joan';
    g_data(180) := 'Joanna';
    g_data(181) := 'Joanne';
    g_data(182) := 'Jodie';
    g_data(183) := 'Joe';
    g_data(184) := 'Joel';
    g_data(185) := 'John';
    g_data(186) := 'Jonathan';
    g_data(187) := 'Jordan';
    g_data(188) := 'Joseph';
    g_data(189) := 'Josephine';
    g_data(190) := 'Josh';
    g_data(191) := 'Joshua';
    g_data(192) := 'Joyce';
    g_data(193) := 'Judith';
    g_data(194) := 'Julia';
    g_data(195) := 'Julian';
    g_data(196) := 'Julie';
    g_data(197) := 'June';
    g_data(198) := 'Justin';
    g_data(199) := 'Karen';
    g_data(200) := 'Karl';
  END;

  PROCEDURE init_data_3 IS
  BEGIN
    g_data(201) := 'Kate';
    g_data(202) := 'Katherine';
    g_data(203) := 'Kathleen';
    g_data(204) := 'Kathryn';
    g_data(205) := 'Katie';
    g_data(206) := 'Katy';
    g_data(207) := 'Kayleigh';
    g_data(208) := 'Keith';
    g_data(209) := 'Kelly';
    g_data(210) := 'Kenneth';
    g_data(211) := 'Kerry';
    g_data(212) := 'Kevin';
    g_data(213) := 'Kieran';
    g_data(214) := 'Kim';
    g_data(215) := 'Kimberley';
    g_data(216) := 'Kirsty';
    g_data(217) := 'Kyle';
    g_data(218) := 'Laura';
    g_data(219) := 'Lauren';
    g_data(220) := 'Lawrence';
    g_data(221) := 'Leah';
    g_data(222) := 'Leanne';
    g_data(223) := 'Lee';
    g_data(224) := 'Leigh';
    g_data(225) := 'Leon';
    g_data(226) := 'Leonard';
    g_data(227) := 'Lesley';
    g_data(228) := 'Leslie';
    g_data(229) := 'Lewis';
    g_data(230) := 'Liam';
    g_data(231) := 'Linda';
    g_data(232) := 'Lindsey';
    g_data(233) := 'Lisa';
    g_data(234) := 'Lorraine';
    g_data(235) := 'Louis';
    g_data(236) := 'Louise';
    g_data(237) := 'Lucy';
    g_data(238) := 'Luke';
    g_data(239) := 'Lydia';
    g_data(240) := 'Lynda';
    g_data(241) := 'Lynn';
    g_data(242) := 'Lynne';
    g_data(243) := 'Malcolm';
    g_data(244) := 'Mandy';
    g_data(245) := 'Marc';
    g_data(246) := 'Marcus';
    g_data(247) := 'Margaret';
    g_data(248) := 'Maria';
    g_data(249) := 'Marian';
    g_data(250) := 'Marie';
    g_data(251) := 'Marilyn';
    g_data(252) := 'Marion';
    g_data(253) := 'Mark';
    g_data(254) := 'Martin';
    g_data(255) := 'Martyn';
    g_data(256) := 'Mary';
    g_data(257) := 'Mathew';
    g_data(258) := 'Matthew';
    g_data(259) := 'Maureen';
    g_data(260) := 'Maurice';
    g_data(261) := 'Max';
    g_data(262) := 'Megan';
    g_data(263) := 'Melanie';
    g_data(264) := 'Melissa';
    g_data(265) := 'Michael';
    g_data(266) := 'Michelle';
    g_data(267) := 'Mitchell';
    g_data(268) := 'Mohamed';
    g_data(269) := 'Mohammad';
    g_data(270) := 'Mohammed';
    g_data(271) := 'Molly';
    g_data(272) := 'Naomi';
    g_data(273) := 'Natalie';
    g_data(274) := 'Natasha';
    g_data(275) := 'Nathan';
    g_data(276) := 'Neil';
    g_data(277) := 'Nicholas';
    g_data(278) := 'Nicola';
    g_data(279) := 'Nicole';
    g_data(280) := 'Nigel';
    g_data(281) := 'Norman';
    g_data(282) := 'Oliver';
    g_data(283) := 'Olivia';
    g_data(284) := 'Owen';
    g_data(285) := 'Paige';
    g_data(286) := 'Pamela';
    g_data(287) := 'Patricia';
    g_data(288) := 'Patrick';
    g_data(289) := 'Paul';
    g_data(290) := 'Paula';
    g_data(291) := 'Pauline';
    g_data(292) := 'Peter';
    g_data(293) := 'Philip';
    g_data(294) := 'Phillip';
    g_data(295) := 'Rachael';
    g_data(296) := 'Rachel';
    g_data(297) := 'Raymond';
    g_data(298) := 'Rebecca';
    g_data(299) := 'Reece';
    g_data(300) := 'Rhys';
  END;

  PROCEDURE init_data_4 IS
  BEGIN
    g_data(301) := 'Richard';
    g_data(302) := 'Ricky';
    g_data(303) := 'Rita';
    g_data(304) := 'Robert';
    g_data(305) := 'Robin';
    g_data(306) := 'Roger';
    g_data(307) := 'Ronald';
    g_data(308) := 'Rosemary';
    g_data(309) := 'Rosie';
    g_data(310) := 'Ross';
    g_data(311) := 'Roy';
    g_data(312) := 'Russell';
    g_data(313) := 'Ruth';
    g_data(314) := 'Ryan';
    g_data(315) := 'Sally';
    g_data(316) := 'Sam';
    g_data(317) := 'Samantha';
    g_data(318) := 'Samuel';
    g_data(319) := 'Sandra';
    g_data(320) := 'Sara';
    g_data(321) := 'Sarah';
    g_data(322) := 'Scott';
    g_data(323) := 'Sean';
    g_data(324) := 'Shane';
    g_data(325) := 'Shannon';
    g_data(326) := 'Sharon';
    g_data(327) := 'Shaun';
    g_data(328) := 'Sheila';
    g_data(329) := 'Shirley';
    g_data(330) := 'Sian';
    g_data(331) := 'Simon';
    g_data(332) := 'Sophie';
    g_data(333) := 'Stacey';
    g_data(334) := 'Stanley';
    g_data(335) := 'Stephanie';
    g_data(336) := 'Stephen';
    g_data(337) := 'Steven';
    g_data(338) := 'Stewart';
    g_data(339) := 'Stuart';
    g_data(340) := 'Susan';
    g_data(341) := 'Suzanne';
    g_data(342) := 'Sylvia';
    g_data(343) := 'Terence';
    g_data(344) := 'Teresa';
    g_data(345) := 'Terry';
    g_data(346) := 'Thomas';
    g_data(347) := 'Timothy';
    g_data(348) := 'Tina';
    g_data(349) := 'Toby';
    g_data(350) := 'Tom';
    g_data(351) := 'Tony';
    g_data(352) := 'Tracey';
    g_data(353) := 'Tracy';
    g_data(354) := 'Trevor';
    g_data(355) := 'Valerie';
    g_data(356) := 'Vanessa';
    g_data(357) := 'Victor';
    g_data(358) := 'Victoria';
    g_data(359) := 'Vincent';
    g_data(360) := 'Wayne';
    g_data(361) := 'Wendy';
    g_data(362) := 'William';
    g_data(363) := 'Yvonne';
    g_data(364) := 'Zoe';
  END;

  -- Deterministic lookup by seed
  FUNCTION GET_FIRST_NAMES(
    p_seed IN VARCHAR2,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_hash RAW(32);
    v_idx NUMBER;
  BEGIN
    v_hash := DBMS_CRYPTO.HASH(
      UTL_RAW.CAST_TO_RAW(NVL(p_seed, 'NULL')),
      DBMS_CRYPTO.HASH_SH256
    );
    v_idx := MOD(TO_NUMBER(SUBSTR(RAWTOHEX(v_hash), 1, 8), 'XXXXXXXX'), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 50));
  END GET_FIRST_NAMES;

  -- Random lookup (non-deterministic)
  FUNCTION GET_FIRST_NAMES_RANDOM(
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 IS
    v_idx NUMBER;
  BEGIN
    v_idx := TRUNC(DBMS_RANDOM.VALUE(1, C_COUNT + 1));
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 50));
  END GET_FIRST_NAMES_RANDOM;

  -- Index-based lookup
  FUNCTION GET_FIRST_NAMES_BY_INDEX(
    p_index IN NUMBER,
    p_max_len IN NUMBER DEFAULT 50
  ) RETURN VARCHAR2 DETERMINISTIC IS
    v_idx NUMBER;
  BEGIN
    v_idx := MOD(ABS(NVL(p_index, 0)), C_COUNT) + 1;
    RETURN SUBSTR(g_data(v_idx), 1, LEAST(p_max_len, 50));
  END GET_FIRST_NAMES_BY_INDEX;

  -- Count function
  FUNCTION GET_COUNT RETURN NUMBER DETERMINISTIC IS
  BEGIN
    RETURN C_COUNT;
  END GET_COUNT;

BEGIN
  -- Initialize data on package load
  init_data;
  init_data_2;
  init_data_3;
  init_data_4;
END PKG_ANON_FIRST_NAMES;
/