# Complaint Management System
### Nepal Digital Banking — Error Management

---

## ✅ WHAT YOU NEED
- XAMPP (any recent version) — **that's it**
- No Composer, no frameworks, no extras

---

## 🚀 SETUP IN 4 STEPS

### Step 1 — Copy files
Place the `complaint-management-system` folder inside:
```
C:\xampp\htdocs\complaint-management-system\
```

### Step 2 — Import the database
1. Start **Apache** and **MySQL** in XAMPP Control Panel
2. Open your browser → `http://localhost/phpmyadmin`
3. Click **Import** tab (top bar)
4. Click **Choose File** → select `database.sql` from this folder
5. Click **Go** at the bottom
6. You should see: *"Import has been successfully finished"*

### Step 3 — Create uploads folder (if missing)
Make sure an `uploads/` folder exists inside the project. It's already included but
if it gets deleted, create it again and give it write permission.

On Windows this is automatic. On Linux/Mac run:
```bash
chmod 777 /opt/lampp/htdocs/complaint-management-system/uploads
```

### Step 4 — Open the system
```
http://localhost/complaint-management-system/
```

---

## 🔑 DEFAULT LOGIN

| Role | Email | Password |
|------|-------|----------|
| **Super Admin** | admin@123.com | OneTwo3! |

Register a new customer account from the login page.

---

## 📁 FILE OVERVIEW

```
complaint-management-system/
│
├── config.php           ← DB connection + all helpers (edit DB password here)
├── layout.php           ← Shared HTML shell (navbar, styles, footer)
├── index.php            ← Entry point / redirect
├── login.php            ← Login + Register (combined)
├── verify.php           ← OTP email verification
├── logout.php
│
├── dashboard.php        ← Role-aware home (customer / bank / admin)
├── submit_complaint.php ← Customer complaint form
├── my_complaints.php    ← Customer ticket list
├── view_ticket.php      ← Full ticket detail + status update
│
├── bank_tickets.php     ← Bank staff/admin ticket list
├── bank_staff.php       ← Bank admin: manage staff
├── bank_analytics.php   ← Bank admin: charts & SLA stats
├── team_chat.php        ← In-memory team chat (not saved to DB)
│
├── admin_banks.php      ← Super admin: manage & verify banks
├── admin_tickets.php    ← Super admin: view all tickets
├── admin_users.php      ← Super admin: user management
├── admin_settings.php   ← Super admin: SMTP + theme
├── admin_logs.php       ← Super admin: activity logs
│
├── database.sql         ← ← ← IMPORT THIS IN phpMyAdmin
└── uploads/             ← Screenshots saved here (auto-created)
```

---

## 🔧 CHANGE DATABASE PASSWORD

If your MySQL root has a password, open `config.php` and change:
```php
$dbpass = '';          // ← put your MySQL password here
```

---

## 📧 GMAIL SMTP (OPTIONAL)

To make OTP emails actually send:

1. Log in as **Super Admin** → go to **Settings**
2. Fill in:
   - SMTP Host: `smtp.gmail.com`
   - SMTP Port: `587`
   - Gmail Address: `your@gmail.com`
   - App Password: (generate at myaccount.google.com → Security → App Passwords)
3. Save settings

> **Without SMTP**, OTPs are skipped — users are redirected straight to verify.php where
> you can see what OTP was set by checking the `users` table in phpMyAdmin.

---

## 👤 HOW TO CREATE BANK ACCOUNTS

1. Log in as **Super Admin**
2. Go to **Banks** → click **"Add Bank Admin"**
3. Select a bank, fill the form → creates a `bank_admin` account
4. The bank admin can then log in and add staff via **Staff** page

Or a bank admin can add staff directly from the **Staff** tab in their dashboard.

---

## ⚡ SLA RULES

| Priority | Deadline |
|----------|----------|
| 🔴 High   | 2 hours  |
| 🟡 Medium | 12 hours |
| 🟢 Low    | 24 hours |

Tickets past their deadline automatically turn **Overdue** (checked on every page load).

---

## 🤖 AUTO-ASSIGN LOGIC

When a customer submits a complaint:
1. **Step 1** — Find the active staff member with the fewest open tickets
2. **Step 2** — If all staff are offline, assign to whoever has the fewest open tickets regardless of status

---

## 🔐 SECURITY FEATURES

- `password_hash()` / `password_verify()` for all passwords
- PDO prepared statements — no SQL injection possible
- CSRF token on every POST form
- Image upload validates extension + `getimagesize()` + 5 MB limit
- Session timeout after 60 minutes of inactivity
- Role-based page access (each page checks role)
- Activity logging for all key actions

---

## 🐛 TROUBLESHOOTING

**"Connection Error"** — MySQL not running or wrong password in `config.php`

**"Import failed"** in phpMyAdmin — try selecting charset UTF-8 before importing

**Blank page / 500 error** — turn on PHP errors temporarily:
Add at the top of `config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**Upload fails** — make sure the `uploads/` folder exists and is writable

**Countdown timers not showing** — JavaScript might be blocked; check browser console
