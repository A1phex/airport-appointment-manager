# Dortmund Handling Services GmbH — Internal Appointment Scheduler

An internal PHP/MySQL scheduling tool for ground handling staff. The manager publishes her available meeting times; employees request an appointment with a brief description of the topic, and the manager approves or declines each request.

This is an internal-only tool with three roles: **Admin**, **Manager**, and **Employee**. There is no public/passenger-facing booking.

## Features

### Admin
- Everything appointment-related across all managers: create/edit available times (choosing which manager they belong to), approve/decline requests, cancel appointments, delete slots
- Full employee account management (add, edit, delete)
- Own profile and password settings

### Manager
- Publish, edit, and delete available meeting times (with a duration per slot)
- See incoming appointment requests with the employee's name and topic
- Approve or decline requests; cancel approved appointments (the employee is notified on their dashboard)
- Add approved appointments to Google Calendar or download them as `.ics`
- Create, edit, and delete employee accounts

### Employee
- Browse the manager's open slots in a week calendar and request an appointment with a topic
- Withdraw pending requests; cancel approved appointments
- See a notice when a request was declined or an appointment was cancelled
- Add approved appointments to Google Calendar or download them as `.ics` (works with Outlook/Apple Calendar)
- Edit their own profile and password

### Appointment lifecycle

`open → pending` (employee requests with a topic) `→ approved` (manager approves) — or back to `open` when the manager declines, the employee withdraws, or either side cancels. The first request locks a slot; declined slots become available again.

## Getting started (Docker)

1. Install Docker Desktop.
2. From the project root, run:
   ```
   docker compose up -d --build
   ```
3. The app is available at http://localhost:8080. The database schema and seed data (`schema.sql`) are imported automatically on first start.

### Seeded accounts

All seeded accounts use the password `password123`.

| Role | Email |
| --- | --- |
| Admin | `admin@dortmund-handling.de` |
| Manager | `manager@dortmund-handling.de` |
| Employee | `anna.schmidt@dortmund-handling.de` |
| Employee | `lukas.becker@dortmund-handling.de` |
| Employee | `mia.hoffmann@dortmund-handling.de` |

Manager accounts are seeded via `schema.sql`; there is no self-registration. New employee accounts are created by a manager from the Employees page.

## Stack

PHP 8.2 (Apache), MariaDB 10.5, Docker Compose. The app reads its database
config from environment variables (`DB_HOST`, `DB_PORT`, `DB_USER`,
`DB_PASSWORD`, `DB_NAME`), falling back to Railway's native `MYSQL*` variables
and finally to the local docker-compose defaults.

## Deploy to Railway

1. Push the repo to GitHub.
2. On [railway.com](https://railway.com): **New Project → Deploy from GitHub repo** and pick this repo. Railway detects the `Dockerfile` and builds it.
3. In the same project, **Create → Database → MySQL**.
4. On the web service, open **Variables** and add (the `${{...}}` references resolve over Railway's private network, so the DB never needs public exposure):
   ```
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   ```
5. Import the schema once, using the [Railway CLI](https://docs.railway.com/guides/cli):
   ```
   railway connect MySQL
   ```
   then, inside the MySQL shell: `source schema.sql;` (run the command from the project root). The MySQL plugin's default database is `railway`; `schema.sql` contains no `CREATE DATABASE`/`USE`, so it imports straight into it and `DB_NAME` already points there.
6. Web service → **Settings → Networking → Generate Domain** (target port 80). Your app is now live over HTTPS.
7. **Immediately change the seeded passwords.** Generate a new hash:
   ```
   php -r "echo password_hash('YOUR-NEW-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   then via `railway connect MySQL`:
   ```sql
   UPDATE manager SET password_hash='<hash>' WHERE email='manager@dortmund-handling.de';
   ```
   Delete the seeded demo employees (or update their hashes the same way):
   ```sql
   DELETE FROM slot WHERE booked_by IS NOT NULL;
   DELETE FROM employee;
   DELETE FROM login_directory WHERE role='employee';
   ```
   Then create real employee accounts from the manager's **Employees** page.

## Image credits

`img/hero-apron.jpg` — [Airport Apron at CDG](https://commons.wikimedia.org/wiki/File:Airport_Apron_at_CDG.jpg) by DiscoA340, CC BY-SA 4.0.
`img/hero-groundhandling.jpg` — [Airport baggage vehicle returns to its lost luggage](https://commons.wikimedia.org/wiki/File:Airport_baggage_vehicle_returns_to_its_lost_luggage.jpg) by Anidaat, CC BY-SA 4.0.

## Origin

This project began as a fork of [edoc-doctor-appointment-system](https://github.com/HashenUdara/edoc-doctor-appointment-system), a doctor/patient booking system, repurposed for internal ground handling scheduling.
