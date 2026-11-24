# EduPulse: portfolio blurb

Copy the text below onto your portfolio page.

---

**EduPulse: learning management system (PHP, MySQL, Docker)**

A web app where instructors run courses and students hand in work. Instructors create courses and modules, upload materials, set assignments with due dates and points, grade submissions with feedback and post announcements. Students browse and enroll in courses, submit and resubmit files, and follow their grades and progress.

I started it as a semester project at COMSATS University Islamabad, then rebuilt it for production quality:

- **Security hardening:** CSRF protection on every form, rate-limited logins, uploads checked by content and served only to authorised users, and permission checks on every action.
- **Runs with one command:** Docker Compose starts the app and a seeded database with demo accounts.
- **Tested:** 130+ PHPUnit tests run against the real app in CI on every push.

**My role:** sole developer: back end, database design, security, tests and CI, Docker setup and interface design.

Stack: PHP 8.2 · MySQL/MariaDB · Bootstrap 5 · Docker · PHPUnit · GitHub Actions
