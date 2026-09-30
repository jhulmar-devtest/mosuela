# Mosuela Dental Clinic Management System

A web-based dental clinic management and appointment scheduling system built with PHP and MySQL.

This repository is the public portfolio version of the project. It preserves the application source code, UI assets, database structure, and major workflows while excluding private credentials, local machine configuration, dependency files, and real/development database records.

## Features

- Patient registration and authentication
- Google sign-in integration
- Appointment booking and cancellation
- Dentist scheduling and availability management
- Dental service management
- Patient records
- Secretary workflow
- Dentist dashboard and patient records
- Administrator dashboard
- Appointment and schedule management
- Reports
- User preferences/settings
- Role-based access for administrators, dentists, secretaries, and patients

## Technology Stack

- PHP
- MySQL
- HTML / CSS
- JavaScript
- Composer
- Google API Client

## Project Structure

```text
admin/          Administrator pages and management workflows
dentist/        Dentist dashboard and patient workflows
patient/        Patient dashboard, records, and appointments
secretary/      Secretary appointment and scheduling workflows
includes/       Shared authentication, database, and helper code
assets/         Front-end styles/assets
image/          Project and clinic imagery
sql/            Public database schema
uploads/        Runtime user-upload directory
composer.json   PHP dependency definition
```

## Database

`sql/mosuela_db.sql` contains the database **schema only**. Real/development users, email addresses, password hashes, appointments, login history, IP addresses, and other records were removed before publishing.

## Configuration

No private credentials are stored in this repository.

Copy `.env.example` to `.env` for local development and configure the database and Google OAuth values for your own environment.

The `vendor/` directory is intentionally excluded. Run:

```bash
composer install
```

to install the dependencies defined in `composer.json`.

## Public Repository Notes

This repository is intended as a portfolio demonstration of the project's source code, application structure, database design, and implementation work. It is not intended to expose production credentials or private patient/user information.

## Author

Developed as a software development / academic portfolio project.
