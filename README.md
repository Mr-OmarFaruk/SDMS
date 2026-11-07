# IUS Student Document Management System (IUS SDMS)

Simple PHP + MySQL application for IUS student document submissions and academic review. It uses one PHP front controller, PDO, Bootstrap 5, and Bootstrap Icons.

## Setup

1. For XAMPP, start Apache and MySQL. For InfinityFree, create a MySQL database and user in its hosting control panel first.
2. Open `http://localhost/sdmsius/setup.php` locally, or your hosted `/setup.php` page, and enter the connection details. Use the exact MySQL hostname, database name, username, and password shown by your host; shared hosts commonly assign prefixed database and username values. The installer connects directly to that existing database, installs the tables, and saves credentials in `config.local.php`.
3. Open the project URL shown by Apache or your hosting provider to use the application.

Environment variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` override values saved by the installer. To rerun setup, remove `storage/setup.lock` first. For manual installation, create and select a database in phpMyAdmin before importing `database/schema.sql`. The PHP `pdo_mysql` extension must be enabled by the host.

The schema seeds demo accounts, all using password `password`:

- Admin: `admin@sdms.test`
- Teacher: `teacher@sdms.test`
- Student: `student@sdms.test`

## Included workflows

- Role-based student, teacher, and admin authentication.
- Student application creation, attachment upload, editing while pending, and status tracking.
- Teacher/admin acceptance or rejection with reviewer messages.
- Admin dashboard, user directory, date-range reporting, and browser print output.

Uploaded files are stored in `storage/documents`. The application accepts PDF, DOC, DOCX, JPG, JPEG, and PNG files up to 5 MB.
