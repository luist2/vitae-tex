\set ON_ERROR_STOP on

SELECT current_database() = :'database_name' AS correct_database \gset
\if :correct_database
\else
    \echo 'Refusing to grant privileges in an unexpected database.'
    \quit 3
\endif

SELECT current_user = :'owner_role' AS correct_owner \gset
\if :correct_owner
\else
    \echo 'Refusing to grant privileges without the expected migration owner.'
    \quit 4
\endif

SELECT EXISTS (
    SELECT 1
    FROM pg_roles
    WHERE rolname = :'runtime_role'
) AS runtime_role_exists \gset
\if :runtime_role_exists
\else
    \echo 'The runtime role does not exist. Run create-neon-runtime-role.sql first.'
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
    \echo 'Refusing to configure a runtime role that inherits neon_superuser.'
    \quit 6
\endif

BEGIN;

REVOKE CREATE, TEMPORARY ON DATABASE :"database_name" FROM PUBLIC;
REVOKE ALL PRIVILEGES ON DATABASE :"database_name" FROM :"runtime_role";
GRANT CONNECT ON DATABASE :"database_name" TO :"runtime_role";

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE ALL PRIVILEGES ON SCHEMA public FROM :"runtime_role";
GRANT USAGE ON SCHEMA public TO :"runtime_role";

REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM :"runtime_role";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"runtime_role";

REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM :"runtime_role";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"runtime_role";

REVOKE ALL PRIVILEGES ON TABLE public.migrations FROM :"runtime_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    REVOKE ALL PRIVILEGES ON TABLES FROM :"runtime_role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"runtime_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    REVOKE ALL PRIVILEGES ON SEQUENCES FROM :"runtime_role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"runtime_role";

COMMIT;
