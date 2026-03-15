# CampusPark - Intelligent Parking System

CampusPark is a comprehensive parking management platform designed for campus environments. It allows users to view real-time spot availability, reserve spaces, claim occupancy via unique codes, and manage their vehicle profiles.

## Project Structure

- **`frontend/`**: Web-based user interface (HTML/CSS/JS).
- **`backend/`**: PHP API handling business logic, authentication, and database interactions.
- **`python_api/`**: FastAPI service for specialized spot code validation.
- **`database/`**: SQL schema and seed data.

## Prerequisites

- **PHP**: 8.0 or higher.
- **MySQL**: For data storage.
- **Python**: 3.8 or higher (for the validation API).
- **Web Server**: Apache, Nginx, or PHP's built-in server (configured to serve the `frontend` and `backend` directories).

---

## Setup Instructions

### 1. Database Setup
1. Open your MySQL management tool (e.g., phpMyAdmin or MySQL CLI).
2. Create a database named `campuspark`.
3. Import the schema and seed data from `campuspark/database/schema_mysql.sql`.

### 2. Backend Configuration
1. Navigate to `campuspark/backend/config/config.php`.
2. Update the `db` settings with your local MySQL credentials:
   ```php
   'db' => [
     'host' => 'localhost',
     'name' => 'campuspark',
     'user' => 'root',
     'pass' => '',
     'charset' => 'utf8mb4',
   ],
   ```

### 3. Python API Setup
1. Open a terminal and navigate to `campuspark/python_api/`.
2. (Optional) Create a virtual environment:
   ```bash
   python -m venv venv
   source venv/bin/scripts/activate  # On Windows: .\venv\Scripts\activate
   ```
3. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```
4. Run the API:
   ```bash
   uvicorn app:app --reload --port 8000
   ```
   *The Python API must be running for spot claims to work.*

### 4. Running the Web Application
#### Using Apache (e.g., XAMPP)
1. Move or link the `campuspark` folder to your `htdocs` directory.
2. Ensure the URL structure matches `http://localhost/campuspark/frontend/index.html`.
3. If your path differs, update `API_BASE` in `campuspark/frontend/js/api.js`.

#### Using PHP Built-in Server
1. Navigate to the `campuspark` directory.
2. Start the server:
   ```bash
   php -S localhost:8080
   ```
3. Access the app at `http://localhost:8080/frontend/index.html`.
   *Note: If using this method, ensure `API_BASE` in `js/api.js` points correctly to the backend.*

---

## Key Features

- **Real-time Map**: Interactive visualization of parking lots A, B, and C.
- **Spot Types**: Support for Standard and EV-only (Electric/Hybrid) spots.
- **Reservation System**: Book spots in advance with automatic expiration.
- **Claim via Code**: Occupy spots by entering a unique lot/spot code (validated by the Python API).
- **Token Economy**: Users earn tokens for "clean" parking claims.
- **Violation Reporting**: Report issues like poorly parked vehicles or unauthorized use of EV spots.

## Technical Stack

- **Frontend**: JavaScript (ES6 Modules), CSS3, HTML5.
- **Backend**: PHP 8 (PDO for database access, Session-based auth).
- **Validation Engine**: Python 3 (FastAPI).
- **Database**: MySQL.
