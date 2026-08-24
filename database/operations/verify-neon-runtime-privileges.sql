\set ON_ERROR_STOP on

SELECT current_database() = :'database_name' AS correct_database \gset
\if :correct_database
\else
    \echo 'The runtime connection points to an unexpected database.'
    \quit 3
\endif

SELECT current_user = :'runtime_role' AS correct_role \gset
\if :correct_role
\else
    \echo 'The runtime connection uses an unexpected role.'
    \quit 4
\endif

SELECT (
    NOT rolsuper
    AND NOT rolcreatedb
    AND NOT rolcreaterole
    AND NOT rolreplication
    AND NOT rolbypassrls
    AND NOT EXISTS (
        SELECT 1
        FROM pg_auth_members memberships
        JOIN pg_roles granted_role ON granted_role.oid = memberships.roleid
        WHERE memberships.member = pg_roles.oid
          AND granted_role.rolname = 'neon_superuser'
    )
) AS safe_role_attributes
FROM pg_roles
WHERE rolname = current_user
\gset

\if :safe_role_attributes
\else
    \echo 'The runtime role has unsafe role attributes or memberships.'
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
    \echo 'The runtime role has an unexpected database or schema privilege.'
    \quit 6
\endif

SELECT COALESCE(bool_and(
    has_table_privilege(current_user, c.oid, 'SELECT')
    AND has_table_privilege(current_user, c.oid, 'INSERT')
    AND has_table_privilege(current_user, c.oid, 'UPDATE')
    AND has_table_privilege(current_user, c.oid, 'DELETE')
    AND NOT pg_has_role(current_user, c.relowner, 'MEMBER')
), false) AS application_tables_are_limited
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p')
  AND c.relname <> 'migrations'
\gset

\if :application_tables_are_limited
\else
    \echo 'The runtime role is missing DML privileges or owns an application table.'
    \quit 7
\endif

SELECT (
    NOT has_table_privilege(current_user, 'public.migrations', 'SELECT')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'INSERT')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'UPDATE')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'DELETE')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'TRUNCATE')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'REFERENCES')
    AND NOT has_table_privilege(current_user, 'public.migrations', 'TRIGGER')
) AS migrations_are_protected
\gset

\if :migrations_are_protected
\else
    \echo 'The runtime role can modify the migrations table.'
    \quit 8
\endif

SELECT COALESCE(bool_and(
    has_sequence_privilege(current_user, c.oid, 'USAGE')
    AND has_sequence_privilege(current_user, c.oid, 'SELECT')
), true) AS sequences_are_usable
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind = 'S'
\gset

\if :sequences_are_usable
\else
    \echo 'The runtime role cannot use every application sequence.'
    \quit 9
\endif

\echo 'Runtime role verification passed.'
