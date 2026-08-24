\set ON_ERROR_STOP on

SELECT current_database() = :'database_name' AS correct_database \gset
\if :correct_database
\else
    \echo 'The maintenance connection points to an unexpected database.'
    \quit 3
\endif

SELECT current_user = :'maintenance_role' AS correct_role \gset
\if :correct_role
\else
    \echo 'The maintenance connection uses an unexpected role.'
    \quit 4
\endif

SELECT (
    rolcanlogin
    AND NOT rolsuper
    AND NOT rolcreatedb
    AND NOT rolcreaterole
    AND NOT rolinherit
    AND NOT rolreplication
    AND NOT rolbypassrls
    AND NOT EXISTS (
        SELECT 1
        FROM pg_auth_members memberships
        WHERE memberships.member = pg_roles.oid
    )
) AS safe_role_attributes
FROM pg_roles
WHERE rolname = current_user
\gset

\if :safe_role_attributes
\else
    \echo 'The maintenance role has unsafe role attributes or memberships.'
    \quit 5
\endif

SELECT (
    has_database_privilege(current_user, current_database(), 'CONNECT')
    AND NOT has_database_privilege(current_user, current_database(), 'CREATE')
    AND NOT has_database_privilege(current_user, current_database(), 'TEMPORARY')
    AND has_schema_privilege(current_user, 'public', 'USAGE')
    AND NOT has_schema_privilege(current_user, 'public', 'CREATE')
) AS safe_database_privileges
\gset

\if :safe_database_privileges
\else
    \echo 'The maintenance role has an unexpected database or schema privilege.'
    \quit 6
\endif

SELECT (
    has_table_privilege(current_user, 'public.password_reset_tokens', 'DELETE')
    AND has_column_privilege(current_user, 'public.password_reset_tokens', 'created_at', 'SELECT')
    AND NOT has_column_privilege(current_user, 'public.password_reset_tokens', 'email', 'SELECT')
    AND NOT has_column_privilege(current_user, 'public.password_reset_tokens', 'token', 'SELECT')
    AND NOT has_table_privilege(current_user, 'public.password_reset_tokens', 'INSERT')
    AND NOT has_table_privilege(current_user, 'public.password_reset_tokens', 'UPDATE')
    AND NOT has_table_privilege(current_user, 'public.password_reset_tokens', 'TRUNCATE')
    AND NOT has_table_privilege(current_user, 'public.password_reset_tokens', 'REFERENCES')
    AND NOT has_table_privilege(current_user, 'public.password_reset_tokens', 'TRIGGER')
) AS reset_token_privileges_are_limited
\gset

\if :reset_token_privileges_are_limited
\else
    \echo 'The maintenance role does not have the exact password reset cleanup privileges.'
    \quit 7
\endif

SELECT COALESCE(bool_and(
    NOT has_table_privilege(current_user, c.oid, 'SELECT')
    AND NOT has_table_privilege(current_user, c.oid, 'INSERT')
    AND NOT has_table_privilege(current_user, c.oid, 'UPDATE')
    AND NOT has_table_privilege(current_user, c.oid, 'DELETE')
    AND NOT has_table_privilege(current_user, c.oid, 'TRUNCATE')
    AND NOT has_table_privilege(current_user, c.oid, 'REFERENCES')
    AND NOT has_table_privilege(current_user, c.oid, 'TRIGGER')
), true) AS other_tables_are_inaccessible
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p')
  AND c.relname <> 'password_reset_tokens'
\gset

\if :other_tables_are_inaccessible
\else
    \echo 'The maintenance role can access an unrelated table.'
    \quit 8
\endif

SELECT COALESCE(bool_and(
    NOT has_sequence_privilege(current_user, c.oid, 'USAGE')
    AND NOT has_sequence_privilege(current_user, c.oid, 'SELECT')
    AND NOT has_sequence_privilege(current_user, c.oid, 'UPDATE')
), true) AS sequences_are_inaccessible
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind = 'S'
\gset

\if :sequences_are_inaccessible
\else
    \echo 'The maintenance role can access an application sequence.'
    \quit 9
\endif

\echo 'Maintenance role verification passed.'
