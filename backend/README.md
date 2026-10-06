# VEYRA PHP Backend

Enterprise-ready PHP API for the VEYRA business operations platform.

## Requirements

- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.5+
- PDO MySQL extension
- Apache / Nginx (or PHP built-in server for local)

## Setup

### 1. Configure database

Edit `config/config.php`:

```php
'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'veyra',
    'user' => 'root',
    'pass' => 'your_password',
    'charset' => 'utf8mb4',
],
```

Set `app_url` to your frontend origin and `app_debug` to `false` in production.

### 2. Create schema

```bash
mysql -u root -p < sql/schema.sql
```

### 3. Seed demo data

```bash
cd backend
php seed.php
```

Demo credentials:

| Field    | Value            |
|----------|------------------|
| Email    | demo@veyra.com   |
| Password | veyra2026        |

### 4. Run locally

From the `backend` directory:

```bash
php -S localhost:8080
```

API base: `http://localhost:8080/api`

Or point Apache/Nginx document root at the parent folder and route `/api/*` to `backend/api/`.

---

## API Endpoints

All JSON endpoints accept `Content-Type: application/json` or form-encoded bodies.  
Authenticated routes require an active PHP session cookie (`VEYRA_SESSION`).

### Auth

| Method | Endpoint                         | Description              |
|--------|----------------------------------|--------------------------|
| POST   | `/api/auth/login.php`            | Sign in                  |
| POST   | `/api/auth/register.php`         | Create account           |
| POST   | `/api/auth/logout.php`           | Sign out                 |
| GET    | `/api/auth/me.php`               | Current user             |
| POST   | `/api/auth/forgot-password.php`  | Request reset link       |
| POST   | `/api/auth/reset-password.php`   | Set new password         |

#### Login body

```json
{
  "email": "demo@veyra.com",
  "password": "veyra2026",
  "remember": true
}
```

#### Register body

```json
{
  "first_name": "James",
  "last_name": "Wilson",
  "email": "james@company.com",
  "company": "Acme Corp",
  "role": "founder",
  "password": "securepass1",
  "confirm_password": "securepass1",
  "terms": true
}
```

### Resources (require auth)

| Method | Endpoint              | Description                          |
|--------|-----------------------|--------------------------------------|
| GET    | `/api/customers.php`  | List (+ `?q=` `&status=`)            |
| POST   | `/api/customers.php`  | Create                               |
| PUT    | `/api/customers.php`  | Update (`id` in body)                |
| DELETE | `/api/customers.php?id=` | Delete                            |
| GET    | `/api/projects.php`   | List                                 |
| POST   | `/api/projects.php`   | Create                               |
| PUT    | `/api/projects.php`   | Update                               |
| DELETE | `/api/projects.php?id=` | Delete                             |
| GET    | `/api/tasks.php`      | List (`?status=` `&priority=` `&q=`) |
| POST   | `/api/tasks.php`      | Create                               |
| PUT    | `/api/tasks.php`      | Update                               |
| DELETE | `/api/tasks.php?id=`  | Delete                               |

### Response shape

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

Errors:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "This email is already registered."
  }
}
```

---

## Frontend integration

Point your HTML/JS pages at the API base URL:

```js
const API = 'http://localhost:8080/api';

// Login example
const res = await fetch(`${API}/auth/login.php`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  credentials: 'include', // send session cookie
  body: JSON.stringify({ email, password, remember: true }),
});
const json = await res.json();
if (json.success) {
  window.location.href = 'dashboard.html';
}
```

CORS is configured in `config/config.php` for local origins.  
For production, serve the frontend and API from the same domain, or tighten CORS.

---

## Folder structure

```
backend/
├── config/
│   └── config.php          # App + DB settings
├── includes/
│   ├── bootstrap.php       # Loader, CORS, session
│   ├── Database.php        # PDO singleton
│   ├── Response.php        # JSON helpers
│   └── Auth.php            # Register, login, reset
├── api/
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   ├── me.php
│   │   ├── forgot-password.php
│   │   └── reset-password.php
│   ├── customers.php
│   ├── projects.php
│   └── tasks.php
├── sql/
│   └── schema.sql
├── seed.php
└── README.md
```

---

## Security notes

- Passwords hashed with `password_hash()` (bcrypt)
- Prepared statements only (PDO)
- Session regenerate on login
- Reset tokens single-use, time-limited
- Forgot-password always returns the same message (no email enumeration)
- Set `app_debug => false` and `session.secure => true` on HTTPS production
