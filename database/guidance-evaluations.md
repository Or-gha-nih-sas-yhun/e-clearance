# Guidance evaluations

Guidance uses the same evaluation editor, five-point rating scale, response
viewer, CSV export, question sets, and summary charts as Library. Guidance staff
manage their own single form through **Guidance Evaluation** in the Office Portal.
Students answer through **Guidance Evaluation** in their portal or from the
Guidance Office card on the clearance page. The two offices' forms and responses
are stored separately.

Apply the Guidance migration once:

```sh
php artisan migrate --path=database/migrations/2026_09_22_000002_create_guidance_evaluation_tables.php
```

For the FTP deployment that does not run migrations, run
`database/sql/guidance_evaluations.sql` once against the application database.
The migration or SQL script is required before Guidance staff can create a form.

Each office can have one draft or published form. Publishing a Guidance form
requires each student to complete it before submitting a Guidance Office
clearance request or document, and before Guidance staff can approve a pending
request. Students still submit the clearance request separately. Editing any
form content deletes only that office's existing responses, increments its
revision, and requires students to answer the revised form again. Deleting a
form deletes its responses and removes that office's evaluation requirement
until a new form is published. Existing approved clearances stay approved.

See [Library evaluations](library-evaluations.md) for details of the shared
editor, CSV columns, response overlays, and summary charts.
