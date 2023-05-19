-- -------------------------------------------------------------------------------------------------------------------------
-- ID: 4
-- RELEASE: 7.0.0
-- PROBLEM: BCPNP has migrations table name different from main Officio
-- SOLUTION: Rename the table
-- INSTRUCTIONS: Run query in the Backend DB
-- EXECUTED: not yet
-- -------------------------------------------------------------------------------------------------------------------------
ALTER TABLE schema_version RENAME phinx_log;