# MCC e-Clearance

MCC e-Clearance is a Laravel 13 application for student clearance workflows
across the student, instructor, office, treasurer, registrar, and main-admin
portals.

## Local setup

Requirements include PHP 8.3 or newer, Composer, Node.js, npm, and a configured
MySQL/MariaDB or SQLite database.

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Use `.env.example` for local development defaults. Never commit a populated
`.env` file.

## Development checks

```bash
composer test
composer audit --locked
npm audit
npm run build
```

The application has no npm test script; its automated tests run through PHPUnit
with `composer test` or `php artisan test`.

## Production operations

Do not expose the repository root. The production web-server document root must
be exactly this application's `public` directory, with HTTPS enforced.

- [Production deployment](docs/DEPLOYMENT.md)
- [Backup and restore runbook](docs/BACKUP_AND_RESTORE.md)
- [Apache virtual-host example](deploy/apache/e-clearance.conf.example)
- [Nginx server example](deploy/nginx/e-clearance.conf.example)
- [Production environment template](.env.production.example)

Two operations commands are available:

```bash
php artisan backup:system
php artisan security:preflight --document-root=/absolute/path/to/e-clearance/public
```

The preflight intentionally fails for local/development configuration. A
production launch is blocked until it passes and every reported manual check
has been verified.

## System documentation

- [Entity-relationship diagram](docs/ENTITY_RELATIONSHIP_DIAGRAM.md)
- [Complete DBML ERD source](docs/clearance-db-erd.dbml)
- [Draw.io ERD](docs/clearance-db-erd.drawio)
- [Draw.io context flow diagram](docs/clearance-context-flow-diagram.drawio)
- [Draw.io data flow diagram](docs/clearance-data-flow-diagram-organized.drawio)
- [Draw.io use case diagram](docs/clearance-use-case-diagram.drawio)

## Security reports

Report suspected vulnerabilities privately to the system owner or designated
MCC security contact. Do not include passwords, OTP codes, application keys,
database dumps, student documents, or full authentication tokens in reports or
issue trackers.
