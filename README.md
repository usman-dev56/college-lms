Future Vision College — Learning Management System

A modern Learning Management System (LMS) for an intermediate college in Pakistan, covering Grades 11–12, academic management, student enrollment, attendance, timetables, admissions, and role-based portals.

Where Vision Meets Excellence

Features

Role-based authentication: Admin, Teacher, Student

Academic sessions, streams, subjects, classes, and sections

Teacher and student management

Class-subject-teacher assignments

Weekly timetable management with conflict detection

Student batches, profiles, admissions, and enrollment

Student portal with timetable and attendance

Period-wise student attendance marking

Attendance reports, trends, and defaulter lists

Admin dashboard with academic and attendance insights

Public landing page and online admissions

Responsive and print-friendly timetable views

Academic Streams

Pre-Medical · Pre-Engineering · ICS · Commerce · Humanities

Tech Stack

Layer

Technology

Backend

Laravel 13, PHP 8.5

Frontend

React 19, TypeScript

Bridge

Inertia.js 2

Styling

Tailwind CSS

Database

PostgreSQL 17

Charts

Recharts

Build

Vite

Authentication

Laravel Breeze

Testing

PHPUnit

Requirements

PHP 8.5+

Composer 2.x

Node.js 22+

PostgreSQL 17+

Git

Installation

1. Clone

git clone https://github.com/usman-dev56/college-lms.git
cd college-lms

2. Install dependencies

composer install
npm install

3. Configure environment

copy .env.example .env
php artisan key:generate

Update .env with your PostgreSQL credentials:

APP_NAME="Future Vision College"
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=college_lms_dev
DB_USERNAME=postgres
DB_PASSWORD=your_password

4. Create database and seed data

psql -U postgres -c "CREATE DATABASE college_lms_dev;"
php artisan migrate
php artisan db:seed

5. Run the application

Terminal 1:

npm run dev

Terminal 2:

php artisan serve

Open http://127.0.0.1:8000

Demo Credentials

Role

Email

Password

Admin

admin@college.test

Configured in .env

Teacher

ahmed.khan@college.test

teacher123

Student

ahmed.khan.16@college.test

student123

Change demo credentials before production deployment.

Project Structure

college-lms/
├── app/              # Controllers, Models, Services
├── database/         # Migrations and Seeders
├── resources/        # React, Inertia, Views
├── routes/           # Application routes
├── tests/            # Feature and application tests
└── docs/screenshots/ # Project screenshots

Screenshots

Project screenshots are stored in docs/screenshots/ and include the landing page, login, dashboards, timetable, attendance, admissions, and student portal.

Testing

npx tsc --noEmit
php artisan test

Roadmap

Foundation & Authentication

Academic Structure

Students & Enrollment

Attendance

Assessments & Report Cards

Fees & Challans

Board Registration & Results

License

Educational Use License — intended for educational institutions and learning purposes. Commercial use requires written permission.

Credits

Developed for Future Vision College
Where Vision Meets Excellence.
