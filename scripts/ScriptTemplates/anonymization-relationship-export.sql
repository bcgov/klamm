-- Export every column from every table you can query in the current session.
-- Default behavior captures all schemas; adjust OWNER_FILTER to narrow results.
-- Run with F5 (script mode) so the entire result streams to the spool file.
-- However, you may want to run the statement standalone to preview results
-- and export directly if the spool does not include all results.

DEFINE OWNER_FILTER = '%'
DEFINE OUTPUT_FILE = 'all_columns.csv'

SET TERMOUT OFF
SET FEEDBACK OFF
SET VERIFY OFF
SET HEADING ON
SET PAGESIZE 0
SET LINESIZE 32767
SET LONG 1000000

SPOOL &OUTPUT_FILE

PROMPT DATABASE_NAME,SCHEMA_NAME,OBJECT_TYPE,TABLE_NAME,COLUMN_NAME,COLUMN_ID,DATA_TYPE,DATA_LENGTH,DATA_PRECISION,DATA_SCALE,NULLABLE,CHAR_LENGTH,COLUMN_COMMENT,TABLE_COMMENT,RELATED_COLUMNS

with params as (
   select sys_context(
      'USERENV',
      'DB_NAME'
   ) as database_name,
          nvl(
             nullif(
                trim(upper('&OWNER_FILTER')),
                ''
             ),
             '%'
          ) as owner_filter
     from dual
),relationship_details as (
   select child.owner as column_owner,
          child.table_name as column_table,
          child.column_name as column_name,
          'OUTBOUND -> '
          || parent.owner
          || '.'
          || parent.table_name
          || '.'
          || parent.column_name
          || ' via '
          || cons.constraint_name as related_descriptor
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
   union all
   select parent.owner as column_owner,
          parent.table_name as column_table,
          parent.column_name as column_name,
          'INBOUND <- '
          || child.owner
          || '.'
          || child.table_name
          || '.'
          || child.column_name
          || ' via '
          || cons.constraint_name as related_descriptor
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
),relationship_agg as (
   select column_owner,
          column_table,
          column_name,
          rtrim(
             replace(
                replace(
                   xmlserialize(content xmlagg(xmlelement(
                      e,
              related_descriptor || '; '
                   )
                       order by related_descriptor
                   ) no indent),
                   '<E>',
                   ''
                ),
                '</E>',
                ''
             ),
             '; '
          ) as related_columns
     from relationship_details
    group by column_owner,
             column_table,
             column_name
)
select p.database_name,
       c.owner as schema_name,
       nvl(
          o.object_type,
          'TABLE'
       ) as object_type,
       c.table_name,
       c.column_name,
       c.column_id,
       c.data_type,
       c.data_length,
       c.data_precision,
       c.data_scale,
       c.nullable,
       c.char_length,
       col.comments as column_comment,
       tab.comments as table_comment,
       rel.related_columns
  from all_tab_columns c
 cross join params p
  left join relationship_agg rel
on rel.column_owner = c.owner
   and rel.column_table = c.table_name
   and rel.column_name = c.column_name
  left join all_objects o
on o.owner = c.owner
   and o.object_name = c.table_name
   and o.object_type in ( 'TABLE',
                          'VIEW',
                          'MATERIALIZED VIEW' )
  left join all_col_comments col
on col.owner = c.owner
   and col.table_name = c.table_name
   and col.column_name = c.column_name
  left join all_tab_comments tab
on tab.owner = c.owner
   and tab.table_name = c.table_name
 where p.owner_filter = '%'
    or upper(c.owner) like p.owner_filter escape '\'
 order by c.owner,
          c.table_name,
          c.column_id;

SPOOL OFF

SET TERMOUT ON
SET FEEDBACK ON
SET VERIFY ON
SET PAGESIZE 50
SET LINESIZE 200
SET TRIMSPOOL OFF
   set sqlformat ansiconsole
PROMPT Column export written to &OUTPUT_FILE