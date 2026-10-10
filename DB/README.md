# Database setup

`schema.sql` creates the application tables and the built-in Admin, Guru, and Siswa roles for a **new, empty database**. It contains no school records, users, or passwords. It uses InnoDB and `utf8mb4_unicode_ci` for compatibility with MySQL and MariaDB on Ubuntu.

## Fresh installation

Create the database and import the schema as a database administrator:

```sh
sudo mysql -e "CREATE DATABASE siakadu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
sudo mysql siakadu < DB/schema.sql
```

Create the first administrator account with a password hash generated on the server. For example:

```sh
php -r '$password = rtrim(fgets(STDIN), "\r\n"); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
```

Use the resulting hash in an `INSERT INTO user (id_user, username, password, email, role_id_role)` statement with `role_id_role` set to `1`. Do not use a plaintext password or commit the generated hash to source control.

The default development connection is `localhost`, database `siakadu`, user `root`, and an empty password. This is convenient for a local Laragon setup. Ubuntu MySQL may not allow the web server to connect as `root`; for a local Ubuntu test server, create a separate account instead:

```sql
CREATE USER 'siakadu_app'@'localhost' IDENTIFIED BY 'choose-a-test-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON siakadu.* TO 'siakadu_app'@'localhost';
```

Import the schema as a database administrator, not as the application account. To override any default connection setting, set the corresponding environment variable in the web server/PHP-FPM environment:

```ini
env[SIAKADU_DB_HOST] = localhost
env[SIAKADU_DB_PORT] = 3306
env[SIAKADU_DB_NAME] = siakadu
env[SIAKADU_DB_USER] = siakadu_app
env[SIAKADU_DB_PASSWORD] = choose-a-test-password
```

After changing PHP-FPM settings, restart the matching PHP-FPM service. These credentials are for development/testing only, not for a public production deployment.

## Ubuntu runtime

Use a currently security-supported PHP release (PHP 8.3 or newer is recommended; the code requires PHP 8.0+) with `pdo_mysql` enabled for the web SAPI. For Apache, enable its PHP-FPM proxy configuration or use the supported Apache PHP module. For Nginx, configure a PHP-FPM `location ~ \.php$` handler and deny access to database, migration, and other non-public source files. The repository's `.htaccess` is Apache-only and is not read by Nginx.

For public production deployment, configure HTTPS and secure session cookies at PHP/web-server level. The login page already enables strict session mode, HttpOnly and SameSite=Lax cookies, sets Secure when PHP detects HTTPS, and rotates the session ID after successful authentication.

After logging in, create the school's academic year, majors, and application records through the admin pages.

Keep the application source tree read-only to PHP-FPM. Ensure the PHP-FPM service user can write to its configured session save path and PHP error log. Do not make the whole application directory writable by the web process.

## Existing database

Do not import `schema.sql` into a database that already contains application data. The attendance unique key is already included in this fresh-install schema. For an existing installation, inspect duplicate attendance rows first:

```sql
SELECT siswa_nis, jadwal_id_jadwal, tanggal, COUNT(*) AS jumlah
FROM absensi
GROUP BY siswa_nis, jadwal_id_jadwal, tanggal
HAVING COUNT(*) > 1;
```

If duplicates exist, review and resolve them while preserving the correct attendance records and taking a backup. Then apply `migrations/20261010_attendance_unique_key.sql` once. Do not run that migration after importing `schema.sql`; retain a record of applied migrations for the existing installation.
