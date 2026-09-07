# Re-filing round of 7 September 2026

Inputs and helpers of the round that re-filed the 55 imported work-plan
activities and normalised 52 codes (see COMMANDS.md, "Re-filing imported
activities, and vetting the result").

- `dump-awp.php` - the catalogue and the imported rows as the judges saw them (run inside the app container).
- `merge-decisions.py` - two judge passes + the matcher -> one decision per activity with a confidence.
- `decisions.json` - the 55 placements; `decisions-codes.json` - the 52 code fixes (ids 130, 213, 214 flagged for a check).
- `apply-undone.php` - how the nine proposals put back during vetting were settled afterwards.
- `programme-codes.sql` - eight programme codes brought to the "N.n PRG" form, for the live database.

Nothing here runs on the server by itself: `tools/allocate-imported.php` applies decisions locally, `tools/export-allocations.php` produces the live SQL.
