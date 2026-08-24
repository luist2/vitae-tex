\set ON_ERROR_STOP on

SELECT current_database() = :'database_name' AS correct_database \gset
\if :correct_database
\else
    \echo 'Refusing to create the runtime role in an unexpected database.'
    \quit 3
\endif

SELECT current_user = :'owner_role' AS correct_owner \gset
\if :correct_owner
\else
    \echo 'Refusing to create the runtime role without the expected migration owner.'
    \quit 4
\endif

SELECT format(
    'CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS',
    :'runtime_role'
)
WHERE NOT EXISTS (
    SELECT 1
    FROM pg_roles
    WHERE rolname = :'runtime_role'
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
WHERE rolname = :'runtime_role'
\gset

\if :safe_role_attributes
\else
    \echo 'The existing runtime role has unsafe attributes. It must be reviewed instead of altered automatically.'
    \quit 5
\endif

SELECT NOT EXISTS (
    SELECT 1
    FROM pg_auth_members memberships
    JOIN pg_roles granted_role ON granted_role.oid = memberships.roleid
    JOIN pg_roles member_role ON member_role.oid = memberships.member
    WHERE granted_role.rolname = 'neon_superuser'
      AND member_role.rolname = :'runtime_role'
) AS restricted_role \gset
\if :restricted_role
\else
    \echo 'The runtime role inherits neon_superuser. Drop it and recreate it through this SQL procedure.'
    \quit 6
\endif

\getenv runtime_password NEON_RUNTIME_PASSWORD

\if :{?runtime_password}
\else
    \echo 'NEON_RUNTIME_PASSWORD was not provided by the interactive wrapper.'
    \quit 7
\endif

ALTER ROLE :"runtime_role" PASSWORD :'runtime_password';
\unset runtime_password
