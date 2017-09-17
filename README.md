Standalone workaround for IRRE relations in Workspaces
======================================================

This TYPO3 extension exists for a sole purpose: to fix the bug reported on https://forge.typo3.org/issues/82221

Strategy
--------

The workaround is implemented by hooking into DataHandler to perform the following logic when in a draft workspace:

1. Catching copies of records that contain `flex` type fields in tables that are workspaces enabled
2. Parsing the DataSource for each field to detect any `inline` field types
3. Copying the `flex` column value from the record that has the correct value to the one that doesn't (see issue) 
4. Reading the related records for the draft version of the parent record
5. Updating all relation-specific column values from the record that has the right values to the other (see issue)

The result is a corrected database record structure for both the draft and placeholder records, which prevents the
described duplication issue when the workspace is published.
