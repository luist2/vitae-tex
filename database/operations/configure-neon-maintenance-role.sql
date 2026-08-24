\set ON_ERROR_STOP on

SELECT current_database() = :'database_name' AS correct_database \gset
\if :correct_database
\else
    \echo 'Refusing to configure the maintenance role in an unexpected database.'
    \quit 3
\endif

SELECT current_user = :'owner_role' AS correct_owner \gset
\if :correct_owner
\else
    \echo 'Refusing to configure the maintenance role without the expected migration owner.'
    \quit 4
\endif

SELECT format(
    'CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS',
    :'maintenance_role'
)
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_roles
    WHERE rolname = :'maintenance_role'
) \gexec

SELECT (
    rolcanlogin
    AND NOT rolsuper
    AND NOT rolcreatedb
    AND NOT rolcreaterole
    AND NOT rolinherit
    AND NOT rolreplication
    AND NOT rolbypassrls
) AS safe_role_attributes
FROM pg_roles
WHERE rolname = :'maintenance_role'
\gset

\if :safe_role_attributes
\else
    \echo 'The existing maintenance role has unsafe attributes. Review it instead of altering it automatically.'
    \quit 5
\endif

SELECT NOT EXISTS (
    SELECT 1
    FROM pg_auth_members memberships
    JOIN pg_roles granted_role ON granted_role.oid = memberships.roleid
    JOIN pg_roles member_role ON member_role.oid = memberships.member
    WHERE member_role.rolname = :'maintenance_role'
) AS standalone_role \gset
\if :standalone_role
\else
    \echo 'The maintenance role inherits another role. Drop it and recreate it through this procedure.'
    \quit 6
\endif

SELECT to_regclass('public.password_reset_tokens') IS NOT NULL AS reset_tokens_table_exists \gset
\if :reset_tokens_table_exists
\else
    \echo 'The password_reset_tokens table does not exist. Run the application migrations first.'
    \quit 7
\endif

\getenv maintenance_password NEON_MAINTENANCE_PASSWORD

\if :{?maintenance_password}
\else
    \echo 'NEON_MAINTENANCE_PASSWORD was not provided by the interactive wrapper.'
    \quit 8
\endif

BEGIN;

ALTER ROLE :"maintenance_role" PASSWORD :'maintenance_password';
\unset maintenance_password

REVOKE CREATE, TEMPORARY ON DATABASE :"database_name" FROM PUBLIC;
REVOKE ALL PRIVILEGES ON DATABASE :"database_name" FROM :"maintenance_role";
GRANT CONNECT ON DATABASE :"database_name" TO :"maintenance_role";

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE ALL PRIVILEGES ON SCHEMA public FROM :"maintenance_role";
GRANT USAGE ON SCHEMA public TO :"maintenance_role";

REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM :"maintenance_role";
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM :"maintenance_role";

GRANT DELETE ON TABLE public.password_reset_tokens TO :"maintenance_role";
GRANT SELECT (created_at) ON TABLE public.password_reset_tokens TO :"maintenance_role";

COMMIT;
