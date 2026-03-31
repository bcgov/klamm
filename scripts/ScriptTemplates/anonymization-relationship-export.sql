-- Export anonymization metadata from an Oracle / Siebel database.
-- Run with F5 (script mode) so the full result set is written to the spool file.
--
-- Parameters (set before running):
--   SBL_REPO_ID : Siebel repository row-id (required for Siebel metadata enrichment)
--   OUTPUT_FILE : spool destination filename
--
-- The query auto-detects the DB instance name via USERENV and captures all schemas visible to the connected user
-- (uses ALL_* views so no DBA role is required). Uncomment the WHERE filters below to narrow by owner or table.

DEFINE SBL_REPO_ID  = '%'
DEFINE OUTPUT_FILE  = 'anonymization_columns.csv'

SET TERMOUT OFF
SET FEEDBACK OFF
SET VERIFY OFF
SET HEADING ON
SET PAGESIZE 0
SET LINESIZE 32767
SET LONG 1000000
   set sqlformat csv

SPOOL &OUTPUT_FILE

with params as (
   select sys_context(
      'USERENV',
      'DB_NAME'
   ) as db_instance,
          '&SBL_REPO_ID' as sbl_repo_id
     from dual
),scols as (
   select p.db_instance,
          dt.owner,
          dt.table_name,
          dt.num_rows,
          st.name tab_name,
          st.type tab_type,
          st.stat_cd tab_stat_cd,
          st.user_name tab_user_name,
          st.desc_text tab_desc_text,
          sc.name column_name,
          sc.type col_type,
          sc.stat_cd col_stat_cd,
          sc.user_name sbl_user_name,
          sc.desc_text sbl_desc_text,
          sc.length col_length,
          sc.nullable col_nullable,
          sc.pr_key,
          rt.name ref_tab_name
     from siebel.s_column sc
     join params p
   on 1 = 1
     join siebel.s_table st
   on sc.tbl_id = st.row_id
      and st.repository_id = p.sbl_repo_id
     join all_tables dt
   on dt.owner = 'SIEBEL'
      and dt.table_name = st.name
     left join siebel.s_table rt
   on sc.fkey_tbl_id = rt.row_id
),siebel_fks as (
   -- Siebel-defined foreign keys (via S_COLUMN.FKEY_TBL_ID), independent of scols join
   select 'SIEBEL' as owner,
          st.name as table_name,
          sc.name as column_name,
          rt.name as siebel_ref_tab
     from siebel.s_column sc
     join siebel.s_table st
   on sc.tbl_id = st.row_id
     join siebel.s_table rt
   on sc.fkey_tbl_id = rt.row_id
    where sc.fkey_tbl_id is not null
),siebel_pks as (
   -- Siebel-defined primary keys (via S_COLUMN.PR_KEY), independent of scols join
   select 'SIEBEL' as owner,
          st.name as table_name,
          sc.name as column_name,
          'PKEY' as sbl_pk_flag
     from siebel.s_column sc
     join siebel.s_table st
   on sc.tbl_id = st.row_id
    where sc.pr_key = 'Y'
),oracle_fks as (
   -- Foreign-key relationships detected from Oracle constraints
   select child.owner,
          child.table_name,
          child.column_name,
          listagg(parent.table_name,
                  '; ') within group(
           order by parent.table_name) as fk_references
     from all_constraints cons
     join all_cons_columns child
   on cons.owner = child.owner
      and cons.constraint_name = child.constraint_name
     join all_constraints pk
   on cons.r_owner = pk.owner
      and cons.r_constraint_name = pk.constraint_name
     join all_cons_columns parent
   on pk.owner = parent.owner
      and pk.constraint_name = parent.constraint_name
      and parent.position = child.position
    where cons.constraint_type = 'R'
    group by child.owner,
             child.table_name,
             child.column_name
),oracle_pks as (
   -- Primary-key columns detected from Oracle constraints
   select distinct cols.owner,
                   cols.table_name,
                   cols.column_name,
                   'PKEY' as pk_flag
     from all_constraints cons
     join all_cons_columns cols
   on cons.owner = cols.owner
      and cons.constraint_name = cols.constraint_name
    where cons.constraint_type = 'P'
)
select p.db_instance,
       owner,
       table_name
       || '.'
       || column_name qualfield,
       column_id,
       table_name,
       column_name,
       '' as anon_rule,
       '' as anon_note,
       coalesce(
          pk_flag,
          sbl_pk_flag,
          case
             when pr_key = 'Y' then
                   'PKEY'
          end
       ) pr_key,
       coalesce(
          fk_references,
          siebel_ref_tab,
          ref_tab_name
       ) ref_tab_name,
       num_distinct,
       dt.num_rows - num_nulls num_not_null,
       num_nulls,
       dt.num_rows,
       dtc.data_type,
       dtc.data_length,
       dtc.data_precision,
       dtc.data_scale,
       comments,
       sbl_user_name,
       sbl_desc_text,
       nullable
  from all_tab_cols dtc
  join params p
on 1 = 1
  join all_tables dt
using ( owner,
        table_name )
  join all_col_comments dcc
using ( owner,
        table_name,
        column_name )
  left join scols
using ( owner,
        table_name,
        column_name )
  left join oracle_fks
using ( owner,
        table_name,
        column_name )
  left join oracle_pks
using ( owner,
        table_name,
        column_name )
  left join siebel_fks
using ( owner,
        table_name,
        column_name )
  left join siebel_pks
using ( owner,
        table_name,
        column_name )
 where 1 = 1
--  Uncomment the lines below to filter by owner or specific tables:
--  and owner = 'SIEBEL'
--  and table_name in ('S_CASE', 'S_CASE_BNFTPLAN', 'S_CONTACT', 'S_ORG_EXT')
 order by owner,
          table_name,
          column_name;

SPOOL OFF

SET TERMOUT ON
SET FEEDBACK ON
SET VERIFY ON
SET PAGESIZE 50
SET LINESIZE 200
SET TRIMSPOOL OFF
   set sqlformat ansiconsole

PRO Column export written to &OUTPUT_FILE