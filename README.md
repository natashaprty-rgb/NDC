<p align="center"><img src="public/images/logo-horizontal.png" alt="National Data Center Somalia" width="420"></p>

# NDC Somalia Management System

Management Information System for the **National Data Center (NDC) Somalia**:
project management, staff and departments, tasks and workflow, document
management, official correspondence, meetings and calendar, assets and
inventory, procurement and suppliers, budget and expense tracking, reports,
notifications, user and access management, and system administration — in
**English and Somali**.

Built with PHP (Laravel 13), MySQL, HTML5, CSS3 and JavaScript, following
the *System Requirements & Scope of Work, Version 1.0*.

## Quick start (development)

```bash
composer install
cp .env.example .env            # then edit it: see docs/LOCAL_SETUP.md
php artisan key:generate
php artisan migrate --seed      # schema, permissions, roles, reference lists
php artisan ndc:create-admin    # first Super Administrator
php artisan ndc:doctor          # checks the setup and explains any problem
php artisan serve
```

Optional sample data for demonstrations and training (never in production):
`php artisan db:seed --class=DemoDataSeeder`.

Run the tests: `php artisan test`.

## Documentation

| Document | Audience |
| --- | --- |
| [Local setup (Windows / XAMPP)](docs/LOCAL_SETUP.md) | Anyone trying the system on their computer |
| [Installation & deployment](docs/INSTALLATION.md) | Server administrators |
| [Backup & restoration guide](docs/BACKUP_AND_RESTORE.md) | Server administrators |
| [Administrator guide](docs/ADMIN_GUIDE.md) | System administrators |
| [Staff user guide](docs/USER_GUIDE.md) | All staff |
| [Technical architecture](docs/ARCHITECTURE.md) | Developers, IT |
| [Integration API](docs/API.md) | Integrators |
| [Development guide](docs/DEVELOPMENT.md) | Developers |
| [Features, test results & known limitations](docs/STATUS.md) | Management, project team |

## Security

Report security issues privately to the NDC System Administrator. Never
commit `.env`, passwords, keys or production data to this repository.

## Ownership

Source code and documentation are delivered to the National Data Center
Somalia under the agreed ownership and transfer arrangements.
