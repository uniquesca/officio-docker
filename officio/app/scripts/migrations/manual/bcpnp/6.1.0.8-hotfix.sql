-- -------------------------------------------------------------------------------------------------------------------------
-- ID: 1
-- RELEASE: RELEASE 6.1.0.8
-- DATE: 2021-10-26
-- PROBLEM: Wrong dates were set for the recently introduced form versions which leads to improper form versions assigned to
--          submitted cases.
-- SOLUTION: Fix version dates for the recently introduced form versions.
-- INSTRUCTIONS: Run query in the Backend DB
-- EXECUTED: yes
-- --------------------------------------------------------------------------------------------------------------------------
UPDATE `FormVersion`
SET `VersionDate` = '2021-10-20 18:00:00'
WHERE `FormVersionId` IN (16, 17, 18, 19, 20);