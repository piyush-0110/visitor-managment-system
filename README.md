# Visitor Management System (VMS)

A full-stack web application designed to digitize, track, and secure visitor entry and exit workflows for colleges, corporate offices, and institutions—replacing manual paper registers with role-based access control, automated QR code passes, real-time webcam check-in/check-out, and Python-powered analytics.

---

## Tech Stack & Architecture

| Layer | Technology | Role in Application |
| :--- | :--- | :--- |
| **Frontend Structure** | HTML5 | Semantic forms, tables, dashboards, and printable visitor badges |
| **Styling & Layout** | CSS3 | Responsive grid/flexbox layouts, status badges, and print media queries |
| **Client Interactivity** | JavaScript (ES6+) | Form validation, dynamic "Other" host toggle, `fetch()` AJAX notifications, and live webcam QR decoding (`html5-qrcode`) |
| **Backend Server** | PHP 8.x (PDO) | Session authentication, role-based access control (RBAC), prepared SQL queries, and Python subprocess orchestration |
| **Database** | MySQL | Relational schema (`users`, `visitors`, `appointments`, `visits`, `notifications`, `audit_logs`) with foreign keys |
| **Python Services** | Python 3 (`qrcode`, `pandas`, `reportlab`) | Automated QR visitor pass generation and structured CSV/PDF reporting |

---

## Project Directory Structure

```text
visitor-managment-system/
├── api/
│   ├── mark_notifications.php   # AJAX endpoint to clear host alerts
│   └── scan_qr.php              # AJAX endpoint for webcam QR check-in/out
├── assets/
│   ├── css/style.css            # Global responsive stylesheet
│   ├── js/validation.js         # Client-side registration validation
│   ├── qr_codes/                # Generated visitor pass QR PNGs
│   └── reports/                 # Exported CSV & PDF reports
├── config/
│   └── db.php                   # PDO MySQL database connection
├── includes/
│   └── auth.php                 # Session guards & audit log helper
├── python_services/
│   ├── db_config.py             # Shared MySQL connector for Python
│   ├── make_qr.py               # Generates QR pass images
│   └── generate_report.py       # Compiles pandas DataFrames into CSV/PDF
├── views/
│   ├── dashboard.php            # Live KPI metrics & host notifications
│   ├── register_visitor.php     # Walk-in registration + blacklist check
│   ├── appointments.php         # Pre-scheduled visits & arrival conversion
│   ├── manage_visits.php        # Gate approval, check-in/out & search
│   ├── visitor_pass.php         # Printable QR visitor badge
│   ├── qr_scanner.php           # Live webcam QR scanner & manual fallback
│   ├── blacklist.php            # Admin visitor restriction management
│   ├── users.php                # Admin staff/faculty account management
│   └── reports.php              # Python CSV/PDF export & system audit logs
├── database.sql                 # Complete MySQL schema & seed accounts
├── index.php                    # Role-based login portal
├── logout.php                   # Session teardown
└── README.md