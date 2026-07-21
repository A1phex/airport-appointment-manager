# Dortmund Handling Services GmbH — Internal Appointment Scheduler

An internal PHP/MySQL scheduling tool for ground handling staff. The manager publishes available meeting times; employees request an appointment with a brief description of the topic, and the manager (or an admin) approves or declines each request.

This is an internal-only tool with three roles: **Admin**, **Manager**, and **Employee**. There is no public or passenger-facing booking.

## Features

### Admin
- Full oversight of appointments across all managers: create and edit available times, approve or decline requests, cancel appointments, delete slots
- Employee account management (add, edit, delete)
- Own profile and password settings

### Manager
- Publish, edit, and delete available meeting times (with a duration per slot)
- See incoming appointment requests with the employee's name and topic
- Approve or decline requests; cancel approved appointments (the employee is notified on their dashboard)
- Add approved appointments to Google Calendar or download them as `.ics`
- Manage employee accounts
- Own profile and password settings

### Employee
- Browse open slots in a week calendar and request an appointment with a topic
- Withdraw pending requests; cancel approved appointments
- See a notice when a request is declined or an appointment is cancelled
- Add approved appointments to Google Calendar or download them as `.ics` (works with Outlook / Apple Calendar / Google Calendar)
- Edit their own profile and password

### Appointment lifecycle

`open → pending` (employee requests with a topic) `→ approved` (manager or admin approves) — or back to `open` when the request is declined, the employee withdraws, or either side cancels. The first request locks a slot; declined slots become available again.

## Views

Both the manager/admin and employee dashboards default to a **week calendar** with prev/next navigation, and offer a **list** toggle for a flat, sortable table. The interface is responsive and works on mobile.

## Stack

- **PHP 8.2** (Apache) — server-rendered, no JavaScript framework
- **MariaDB / MySQL** — accessed exclusively through prepared statements
- **Docker Compose** for local development

Database configuration is read from environment variables (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`), with sensible defaults for local development.

### Security notes

- Passwords are stored as bcrypt hashes (`password_hash`); there is no self-registration.
- Sessions use `HttpOnly` / `SameSite` cookies, marked `Secure` automatically when served over HTTPS, and the session ID is regenerated on login.
- All user input is validated and escaped; role checks guard every page.

## Running locally

1. Install Docker Desktop.
2. From the project root, run:
   ```
   docker compose up -d --build
   ```
3. The app is available at http://localhost:8080. The database schema is imported automatically on first start from `schema.sql`.

For local development the schema seeds a small set of demo accounts (one per role) so you can log in immediately. These are for local use only — see `schema.sql` for details. There is no self-registration; employee accounts are created by a manager or admin from the **Employees** page.

## Image credits

`img/hero-apron.jpg` — [Airport Apron at CDG](https://commons.wikimedia.org/wiki/File:Airport_Apron_at_CDG.jpg) by DiscoA340, CC BY-SA 4.0.
`img/hero-groundhandling.jpg` — [Airport baggage vehicle returns to its lost luggage](https://commons.wikimedia.org/wiki/File:Airport_baggage_vehicle_returns_to_its_lost_luggage.jpg) by Anidaat, CC BY-SA 4.0.

## Origin

This project began as a fork of [edoc-doctor-appointment-system](https://github.com/HashenUdara/edoc-doctor-appointment-system), a doctor/patient booking system, repurposed for internal ground handling scheduling.
