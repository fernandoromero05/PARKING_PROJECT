# CampusPark

A parking management web app for university campuses. Students can check spot availability, reserve or claim a parking space, and file violation reports. Enforcers patrol lots and can force-release spots and attach photo evidence to reports. Admins manage users and monitor the system through a dedicated dashboard.

---

## Stack

- **Frontend** — HTML5, CSS3, vanilla JS (ES6 modules)
- **Backend** — PHP 8 with PDO (session-based auth)
- **Database** — MySQL
- **Validation service** — Python 3 / FastAPI (optional, for QR spot code validation)

---

## Project layout

```
campuspark/
├── frontend/        HTML pages, CSS, JS
├── backend/         PHP API endpoints + config
├── database/        SQL schema + seed data
└── python_api/      FastAPI validation service
```

---

## Getting it running

### Requirements

- XAMPP (or any stack with Apache + PHP 8+ + MySQL)
- Python 3.8+ 

---

### 1. Database

Open phpMyAdmin (or the MySQL CLI) and run the contents of:

```
campuspark/database/schema_mysql.sql
```

This creates the `campuspark` database, all tables, and seeds three parking lots plus two default accounts.

**Default accounts** (password for both: `password`):

| Username | Role |
|----------|------|
| `admin` | ADMIN |
| `enforcer1` | ENFORCER |

---

### 2. Backend config

Open `campuspark/backend/config/config.php` and set your MySQL credentials:

```php
'db' => [
  'host'    => 'localhost',
  'name'    => 'campuspark',
  'user'    => 'root',
  'pass'    => '',
  'charset' => 'utf8mb4',
],
```

---

### 3. Web server (XAMPP)

Copy or move the `campuspark` folder into your XAMPP `htdocs` directory, then start Apache and MySQL from the XAMPP control panel.

The app will be at:

```
http://localhost/campuspark/frontend/index.html
```

If you put the folder somewhere else, update `API_BASE` in `campuspark/frontend/js/api.js` to match.

---

### 4. Python validation service (optional)

```bash
cd campuspark/python_api
python -m venv venv
venv\Scripts\activate       # Windows
# source venv/bin/activate  # macOS / Linux
pip install -r requirements.txt
uvicorn app:app --reload --port 8000
```

---

## Roles

| Role | What they can do |
|------|-----------------|
| **Student** | View lots, claim/reserve/release spots, file text reports |
| **Enforcer** | Everything above + enforcer dashboard, force-release spots, reports with photo evidence |
| **Admin** | Everything above + user management, system stats, ban/unban users |

---

## Main features

- Interactive parking map per lot with colour-coded spots (available, occupied, reserved, EV-only, yours)
- Reserve a spot for 5 minutes; auto-expires if not claimed
- Token system — penalties for violations, auto-ban at 0 tokens
- Violation reporting with optional photo evidence (enforcers)
- Enforcers can attach a photo to an existing student report instead of filing a duplicate
- Enforcer dashboard: live spot overview across all lots, force-release action
- Admin dashboard: report stats, monthly breakdown, top offenders, user stats
- Admin user management: search, filter by role, ban/unban with immediate effect
- Banned users see a live countdown on the login page
