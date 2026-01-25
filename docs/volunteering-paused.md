# Volunteering Status (Paused)

This feature was added, then fully paused. The code was removed, and the DB
column is dropped by the rollback migration. Use this note as a re-enable
checklist.

Re-enable checklist:
- Re-add `PPBase::volunteeringStatus` (varchar 20, nullable).
- Restore `VolunteeringStatus` enum (unknown/possible/confirmed/not_applicable).
- Restore `NormalizedProjectPersister` handling:
  - Read `volunteering_status` from payload.
  - Ignore `confirmed` from AI data.
  - Default to `possible` for JeVeuxAider `/organisations/` pages.
- Restore the 3P "Participer" consultation section (reuse crowdfunding line).
- Restore admin override select in 3P settings (admin only).
- Update prompts to include `volunteering_status`.
- Update JeVeuxAider prompt to set `possible` by default.

Migrations:
- `Version20260122123000` adds the column.
- `Version20260122124500` removes the column (pause).
