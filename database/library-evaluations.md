# Library evaluations

Enable the tables and the single form controls with the targeted migrations:

```sh
php artisan migrate --path=database/migrations/2026_09_21_000001_create_library_evaluation_tables.php
php artisan migrate --path=database/migrations/2026_09_21_000002_make_library_evaluation_single_form.php
php artisan migrate --path=database/migrations/2026_09_22_000001_add_library_evaluation_sections.php
```

The FTP deployment workflow does not run migrations. On that deployment, run
`database/sql/library_evaluations.sql`, then run
`database/sql/library_evaluation_single_form.sql`, then
`database/sql/library_evaluation_sections.sql` once against the application's
database. The second and third scripts are also required when upgrading existing evaluation
tables. Both tables use InnoDB, independently of the older MyISAM tables.

The upgrade preserves the current form and all its responses. If an older
installation has multiple forms, the most recently published form (or newest
draft when none are published) becomes the single managed form. Other old rows
remain inactive in the database; they are not deleted or offered in the interface.

In the Office Portal, accounts whose role is `library` can open **Library
Evaluation**, create one draft, edit its statements, and publish it. A unique
database slot prevents a second form, including simultaneous creation attempts.
Each statement
uses 5 Strongly Agree, 4 Agree, 3 Neutral, 2 Disagree, and 1 Strongly Disagree.
Publishing makes that form the required evaluation. The librarian can edit the
published form. Any change to the title, instructions, question sets, or questions deletes all
responses in the same transaction and increments the form revision. The form
stays published and students must answer again. An unchanged or invalid save
preserves responses. Stale student submissions and stale staff edits are rejected.

**Download responses CSV** exports every submitted response, independent of the
completion filters or pagination. Each row includes the completion timestamp,
student ID, name, program, year, section, evaluation title, and one column for each
question with its numeric rating and label. The download uses UTF-8 with a BOM for
Excel, quotes CSV fields, and treats possible spreadsheet formulas as text.
The export reads questions and answers together, so edits cannot mix revisions.
Question headers include the set title when provided.

The editor supports up to 10 question sets and 50 questions in total. Each set
has an optional title and description/instructions, displayed above its questions
in the preview, student form, and response overlay. Existing forms appear as one
unnamed set, with their answers preserved. The rating scale stays above the editor.
Changing set instructions also resets responses; saving unchanged sets does not.

Published forms show four clickable summary cards: total students, completed,
not completed, and average rating. These open completion, rating distribution,
pending-by-program, and per-question average charts in the same overlay used for
individual responses. Statistics use all registered students and submitted answers
for the current form, regardless of table search, filters, or pagination.
The overall average weights every answered question equally. Empty charts explain
when data will appear; summaries reset when the form is changed.

The editor offers CSV download before saving changes. Edit and delete controls
explain the response deletion and use the portal's confirmation dialog. Deleting
the form also deletes its responses and allows the librarian to create a new form.
The librarian opens the form preview and its edit, export, publish, and delete
controls from a compact **View evaluation form** button. The preview uses the same
overlay as response details and summary charts, including on draft forms.

Students answer through **Library Evaluation** or the Library clearance card.
All statements require a rating. Each response belongs to the signed-in student
and the form they answered. Completing the form unlocks their library request and
document upload; the student still submits the clearance request separately. The
librarian sees completion badges and timestamps in clearance requests, and a
searchable completion list with individual responses on the evaluation page.

The evaluation also gates single and bulk library approval. Editing the form
requires a fresh response for pending library clearances and future submissions;
existing approved clearance records stay approved. Clearance reset does not clear
evaluation responses. Deleting a student removes that student's responses.

Until a form is published (including after deleting it), the existing library
clearance flow remains available.
If a database has no evaluation tables, the librarian page displays a setup notice.
