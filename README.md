# SecureVote — Secure Online Voting System

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![OAuth 2.0](https://img.shields.io/badge/Auth-Google%20OAuth%202.0-4285F4?logo=google&logoColor=white)](https://developers.google.com/identity/protocols/oauth2)
[![Security](https://img.shields.io/badge/Security-CIA%20Verified%20%2817%2F17%29-10B981?logo=shield&logoColor=white)](#6-cia-security-testing--verification-suite)
[![Dependencies](https://img.shields.io/badge/Dependencies-Zero%20%28Vanilla%20PHP%29-gray)](#2-required-software)

An enterprise-grade, defense-in-depth web voting platform built with **PHP + MySQL/MariaDB**, managed with **phpMyAdmin**, engineered for academic endterm defense (ITP216 Information Assurance & Security). Demonstrates the three pillars of information security (**Confidentiality, Integrity, and Availability**) through running, verifiable code rather than theoretical slides.

---

## Security Pillars at a Glance

| Security Pillar | Technical Implementation & Demonstrable Controls |
| :--- | :--- |
| **Confidentiality** | • **bcrypt** password hashing with work factor cost 12 (`password_hash`, `password_verify`)<br>• True Secret Ballot architecture: voter identity decoupled from vote selections<br>• Hardened sessions (`HttpOnly`, `SameSite=Lax`, `use_strict_mode`, browser fingerprint binding)<br>• Comprehensive HTTP Security Headers (strict CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `COOP`, `Permissions-Policy`)<br>• Role-Based Access Control (RBAC) denying unauthorized routes by default<br>• Context-aware output escaping via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` |
| **Integrity** | • **1-Person-1-Vote** enforced by composite database `UNIQUE` constraints<br>• Atomic ballot casting via InnoDB ACID transactions with row-level locks (`FOR UPDATE`)<br>• Server-side re-validation of all contest rules, election timelines, and candidate eligibility<br>• Cryptographic anti-CSRF token verification (`hash_equals`) rotated on authentication events<br>• Strict parameterized queries across all PDO database interactions (`ATTR_EMULATE_PREPARES = false`)<br>• Append-only, tamper-evident security audit logging with client IP and User-Agent capture |
| **Availability** | • Sub-millisecond database query performance with indexed relational queries<br>• Multi-Method Authentication redundancy (Google OAuth 2.0, Personal Voter Code, and Email/Password)<br>• Graceful error handling and silent logging to prevent system lockups or sensitive data leaks<br>• Per-account brute-force throttling and account lockout mechanisms<br>• Real-time election state machine managing lifecycle without downtime |

---

## Table of Contents

1. [Features & Capabilities](#1-features--capabilities)
2. [Required Software & Prerequisites](#2-required-software--prerequisites)
3. [Installation & Setup](#3-installation--setup)
4. [Accounts & Authentication Architecture](#4-accounts--authentication-architecture)
5. [Database Architecture & Schema](#5-database-architecture--schema)
6. [CIA Security Testing & Verification Suite](#6-cia-security-testing--verification-suite)
7. [Testing Security Controls (Manual Verification)](#7-testing-security-controls-manual-verification)
8. [Project File Structure](#8-project-file-structure)
9. [Voting Workflow & Life of a Ballot](#9-voting-workflow--life-of-a-ballot)
10. [Production Hardening Guidelines](#10-production-hardening-guidelines)
11. [Troubleshooting & FAQ](#11-troubleshooting--faq)
12. [References & Standards](#12-references--standards)

---

## 1. Features & Capabilities

### 🏛️ Civic Welcome Portal (`index.php`)
* **Live Election Status**: Dynamically renders currently open and recently concluded elections with real-time countdown timers.
* **Multi-Method Gateway**: Fast-track buttons for Google Sign-In, Voter Code entry, and account registration.
* **Participation Guide**: 3-step illustrated walkthrough for student voters.
* **Civic Trust Guarantee**: Direct transparency notices detailing ballot secrecy and cryptographic receipt issuance.

### 🗳️ Voter Experience (`voter/`)
* **Personal Dashboard**: Real-time summary of active elections, cast ballots, and participation history.
* **Multi-Option Ballot**: Contested seats with dynamic selection limits (single-choice radio buttons or multi-choice checkboxes with live JS selection counters).
* **Two-Stage Ballot Review**: Confirmation screen displaying server-validated choices before committing to the database.
* **Digital Verification Receipt**: Official receipt reference code (e.g. `SV-2026-9A31F4`) confirming ballot submission without compromising voter choice secrecy.
* **Certified Results View**: Live or post-election tallies gated strictly by the election's `results_visibility` policy (`after_vote`, `after_close`, `hidden`).
* **Profile & Passcode Management**: Self-service profile updates, password changes, and personal Voter Code inspection.

### 🛡️ Administrator Module (`admin/`)
* **Analytics Dashboard**: Real-time turnout metrics, registration statistics, candidate counts, critical security alerts, and live activity streams.
* **Election Lifecycle Management**: Full CRUD operations across 5 distinct states (`draft`, `scheduled`, `open`, `closed`, `archived`) with automated window validation.
* **Position & Contest Control**: Position configuration with custom display order and selection caps (`max_selections`).
* **Candidate Management**: Profiles with party affiliations, platform statements, biographies, and secure image upload with MIME verification.
* **Voter Registry & Eligibility**: Account creation, status management (`active`, `inactive`, `blocked`), password resets, and granular per-election eligibility controls with bulk assignment tools.
* **Live Results & CSV Export**: Real-time vote tallies, leaderboards, tie detection, percentage shares, and 1-click audit-ready CSV exports.
* **Security Audit Trail**: Comprehensive, searchable, and filterable log of system actions (sign-ins, failures, privilege changes, ballot events) with CSV export.
* **Alert Resolution Hub**: Acknowledgment, dismissal, and tracking of security alerts backed by `dismissed_alerts`.
* **CIA Security Verification Suite**: Interactive automated testing harness verifying 17 security controls across Confidentiality, Integrity, and Availability.
* **System Settings & Maintenance**: Administrator profile management, password updates, security posture overview, database backup snapshot downloads, and audit log retention management.

### 📜 Legal & Compliance (`privacy.php`, `terms.php`)
* **Privacy Policy**: Comprehensive compliance disclosure aligned with the Data Privacy Act of 2012 (RA 10173) and international data protection standards.
* **Terms of Service**: Formal electronic voting bylaws, institutional voter code regulations, and fair-use guidelines.

---

## 2. Required Software & Prerequisites

| Requirement | Recommended Version | Details |
| :--- | :--- | :--- |
| **PHP** | **8.1 or newer** (tested on **8.5**) | Uses modern features (`strict_types`, `never`, `match`, `str_contains`, nullsafe operators). Requires extensions: `pdo_mysql`, `mbstring`, `session`, `random`, `json`, `curl`. |
| **MySQL / MariaDB** | **MySQL 5.7+ / MariaDB 10.2+** (tested on **MariaDB 12.3**) | **InnoDB engine is mandatory** for ACID transaction isolation and foreign key integrity. |
| **phpMyAdmin** | Any recent version | Used for database import, inspection, and administration. |
| **Web Browser** | Chromium, Firefox, Safari, Edge | Fully responsive; dark and light modes supported out-of-the-box. |

> **Zero Dependency Guarantee:** SecureVote uses **no Composer packages, no Node.js/NPM builds, and no external CSS/JS frameworks**. Everything is implemented in native PHP, curated Vanilla CSS (Emerald Design System), and standard JavaScript.

---

## 3. Installation & Setup

### Step 3.1: Place Project in Web Root

Clone or copy the project folder into your web server's document root:

* **XAMPP (macOS):** `/Applications/XAMPP/xamppfiles/htdocs/itp216_endterm/`
* **XAMPP (Windows):** `C:\xampp\htdocs\itp216_endterm\`
* **Custom / CLI:** Any directory on your development machine.

The application automatically detects whether it is served from a web server root (`http://localhost:8000/`) or a subdirectory (`http://localhost/itp216_endterm/`) without requiring code changes.

---

### Step 3.2: Start Apache & MySQL

* **XAMPP:** Open the XAMPP Control Panel and start **Apache** and **MySQL**.
* **Terminal Verification:**
  ```bash
  mysql -u root -p -e "SELECT VERSION();"
  ```

---

### Step 3.3: Import Database Schema & Seed Data

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin/`
2. Click the **Import** tab.
3. Click **Choose File** and select `database/secure_voting.sql`.
4. Leave format settings at default and click **Import**.

This single script creates the database (`u184230832_securevote`), sets up the application tables, and inserts demonstration data.

For an existing installation, import `database/migration_site_traffic.sql` once to enable site-wide traffic records. The application also tries to create this table automatically if the database account has `CREATE TABLE` permission; the migration is the reliable route for restricted production accounts.

Administrators can review page views, link clicks, device summaries, and visitor journeys under **Site Traffic**. Traffic records include route paths, destination paths or external hostnames, browser/device details, IP, and the signed-in account when available. Query strings, form fields, ballot selections, and receipts are excluded. The page does not provide city, country, or ISP data without a local geolocation database.

---

### Step 3.4: Configure Environment & Database Credentials

Database credentials are defined in `config/config.php` and can be customized directly or overridden with environment variables:

```php
// config/config.php
define('DB_HOST',   getenv('SV_DB_HOST') ?: 'localhost');
define('DB_PORT',   getenv('SV_DB_PORT') ?: '3306');
define('DB_NAME',   getenv('SV_DB_NAME') ?: 'u184230832_securevote');
define('DB_USER',   getenv('SV_DB_USER') ?: 'root');
define('DB_PASS',   (string) (getenv('SV_DB_PASS') ?: ''));
define('DB_SOCKET', (string) (getenv('SV_DB_SOCKET') ?: ''));
```

*(Note: The default fallback database name in `config.php` is `u184230832_securevote` or `secure_voting` depending on your environment).*

---

### Step 3.5: Run the Application

#### Option A: Using XAMPP / Apache
Open your browser and navigate to:
```text
http://localhost/itp216_endterm/
```

#### Option B: Using PHP Built-In Development Server
From the root of the project directory, run:
```bash
php -S localhost:8000 -t .
```
Then visit: `http://localhost:8000/`

---

## 4. Accounts & Authentication Architecture

SecureVote features a modern **multi-method authentication suite**:

| Authentication Method | How It Works | Target Audience |
| :--- | :--- | :--- |
| **Administrator Account** | Database credentials (`admin` / `Admin@12345`) | System operators and election commissioners |
| **Google OAuth 2.0** | 1-click authentication via institutional or personal Google account | Fast, passwordless sign-in for students |
| **Personal Voter Code** | High-entropy unique alphanumeric token (e.g. `VC-2026-8F3K2Q`) | Direct ballot access for registered voters |
| **Voter Self-Registration** | Public registration at `auth/register.php` with automatic election enrollment | Open voter onboarding |

### 4.1 Demonstration Accounts

| Role | Username / Identifier | Password | Voter Code | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `Admin@12345` | `VC-ADMIN-0001` | Full administrative control, settings, audit trail, and CIA testing suite |
| **Voter Registration** | Direct at `auth/register.php` | User chosen | Auto-generated | Instant voter registration and voting |
| **Google Sign-In** | Any verified Google account | Google auth | Auto-generated | Instant OAuth 2.0 profile provisioning |

---

## 5. Database Architecture & Schema

SecureVote uses **InnoDB** relational tables designed around the **True Secret Ballot** principle, enforcing voter privacy while preserving 100% mathematical verifiability.

```
                    ┌─────────────────────────┐
                    │          users          │
                    │  (Admins, Voters, OAuth)│
                    └────────────┬────────────┘
                                 │
           ┌─────────────────────┼─────────────────────┐
           ▼                     ▼                     ▼
┌───────────────────┐ ┌────────────────────┐ ┌───────────────────┐
│     elections     │ │ voter_eligibility  │ │    audit_logs     │
│  (Lifecycle &     │ │ (1 Row per Voter   │ │ (Immutable Trail  │
│   Results Policy) │ │  per Election)     │ │  with IP & UA)    │
└─────────┬─────────┘ └──────────┬─────────┘ └───────────────────┘
          │                      │
          ├──────────────────────┤
          │                      ▼
          │           ┌────────────────────┐
          │           │      ballots       │
          │           │ (Anonymous Ballot  │
          │           │  Box with Receipt) │
          │           └──────────┬─────────┘
          ▼                      │
┌───────────────────┐            │
│     positions     │            │
└─────────┬─────────┘            │
          │                      │
          ▼                      ▼
┌───────────────────┐ ┌────────────────────┐
│    candidates     │ │       votes        │
│  (Bios & Photos)  │ │ (Choices linked to │
│                   │ │  anonymous ballot) │
└───────────────────┘ └────────────────────┘
```

### 5.1 Tables Overview

| Table Name | Engine | Purpose & Key Constraints |
| :--- | :--- | :--- |
| `users` | InnoDB | User credentials, roles (`admin`, `voter`), statuses, unique `voter_code`, and optional `google_id`. |
| `elections` | InnoDB | Election lifecycle (`draft`, `scheduled`, `open`, `closed`, `archived`) and visibility policies (`hidden`, `after_vote`, `after_close`). |
| `positions` | InnoDB | Contested offices with per-position selection limits (`max_selections`) and display ordering. |
| `candidates` | InnoDB | Candidate profiles, party affiliations, platform statements, and photo filenames. |
| `ballots` | InnoDB | Digital ballot box enforcing strict 1-person-1-vote (`uq_ballots_election_voter`) and unique receipt tracking (`uq_ballots_reference`). |
| `votes` | InnoDB | Individual position and candidate selections tied to `ballots`. Unique compound index prevents duplicate votes on the same candidate. |
| `voter_eligibility` | InnoDB | Tracks voter registration, participation timestamps, and eligibility per election (`uq_voter_eligibility_election_user`). |
| `audit_logs` | InnoDB | Append-only system audit log recording all administrative, authentication, and voting events. |
| `dismissed_alerts` | InnoDB | Tracks acknowledged and dismissed security warnings. |

### 5.2 The 1-Person-1-Vote & Ballot Secrecy Guarantee

1. **Strict 1-Vote Enforcement**:
   ```sql
   UNIQUE KEY `uq_ballots_election_voter` (`election_id`, `voter_id`)
   UNIQUE KEY `uq_voter_eligibility_election_user` (`election_id`, `user_id`)
   ```
   Even if malicious concurrent HTTP requests bypass front-end validation, the database engine physically rejects duplicate ballot insertions.

2. **Decoupled Secret Ballot Architecture**:
   Candidate selections in the `votes` table are linked to `ballots`, while the voter's participation record in `voter_eligibility` only records that a ballot was cast and the receipt code issued. Tally calculations are performed exclusively via aggregate queries (`COUNT(*)` grouped by `candidate_id`), making it mathematically impossible to link a voter to their candidate choices.

---

## 6. CIA Security Testing & Verification Suite

SecureVote includes a dedicated, database-aware **CIA Security Verification Module** accessible at `admin/security-testing.php`. This engine executes **17 automated tests** verifying live compliance with information assurance standards.

```
       ╔═══════════════════════════════════════════════════════════════╗
       ║         SECUREVOTE LIVE CIA VERIFICATION RESULTS             ║
       ╠═══════════════════════════════════════════════════════════════╣
       ║  Confidentiality (C) :  6 / 6 Tests Passed  (100.0% Score)    ║
       ║  Integrity       (I) :  6 / 6 Tests Passed  (100.0% Score)    ║
       ║  Availability    (A) :  5 / 5 Tests Passed  (100.0% Score)    ║
       ║  Overall Status      :  PASS (17 / 17 Total Assertions)       ║
       ╚═══════════════════════════════════════════════════════════════╝
```

### Complete Test Catalog

#### 🔒 Confidentiality (C)
* **`SEC-C01` — Route Authentication Guardrails**: Verifies that all 9 administrative controllers enforce `require_admin()` prior to rendering any markup.
* **`SEC-C02` — Role-Based Access Control (RBAC)**: Validates session role hierarchy and default-deny access policies.
* **`SEC-C03` — Password Hashing Standards**: Confirms that 100% of stored user credentials utilize standard `bcrypt` algorithms (`$2y$12$...`) with zero plaintext exposures.
* **`SEC-C04` — Session Hardening & Security Headers**: Asserts presence of `HttpOnly`, `SameSite=Lax`, strict session modes, and complete HTTP security headers (CSP, HSTS, `X-Frame-Options`).
* **`SEC-C05` — True Secret Ballot & Data Isolation**: Validates decoupling of voter identities from vote selections during aggregation.
* **`SEC-C06` — Sensitive Information Disclosure Protection**: Asserts that `APP_DEBUG` is disabled and error handling suppresses stack traces.

#### 🛡️ Integrity (I)
* **`SEC-I01` — 1-Person-1-Vote Database Constraint**: Validates compound unique constraints (`uq_ballots_election_voter` and `uq_voter_eligibility_election_user`).
* **`SEC-I02` — Atomic Transaction Rollback Defense**: Confirms that multi-table ballot writes use InnoDB transactions with automatic rollback on error.
* **`SEC-I03` — Candidate & Selection Limit Enforcement**: Verifies server-side rejection of over-voting, invalid candidate IDs, and out-of-contest entries.
* **`SEC-I04` — Cryptographic CSRF Defense**: Validates cryptographically secure token generation, per-session validation, and constant-time `hash_equals()` comparison.
* **`SEC-I05` — Parameterized SQL Injection Prevention**: Asserts that PDO prepared statements are enforced with disabled emulation mode (`ATTR_EMULATE_PREPARES = false`).
* **`SEC-I06` — Immutable Security Audit Trail**: Confirms append-only audit logging with client IP and browser agent metadata.

#### ⚡ Availability (A)
* **`SEC-A01` — Database Connectivity & Latency**: Tests database connection speed and response latency (typically `< 2.0 ms`).
* **`SEC-A02` — Relational Schema Integrity**: Asserts existence and operational health of all 9 core database tables.
* **`SEC-A03` — Voting Workflow Pipeline Health**: Checks availability of the end-to-end voting pipeline (`ballot.php`, `review.php`, `confirmation.php`).
* **`SEC-A04` — Brute-Force Rate Limiting & Account Lockout**: Validates per-account brute-force braking (5 failed attempts trigger a 15-minute freeze).
* **`SEC-A05` — Error Logging & Graceful Degradation**: Asserts existence and writeability of `storage/logs/php-error.log`.

---

## 7. Testing Security Controls (Manual Verification)

Every security mechanism can be tested manually:

### 7.1 Authentication & Session Testing

| Test Scenario | Test Action | Expected Result |
| :--- | :--- | :--- |
| **Valid Login** | Sign in as `admin` with `Admin@12345` | Redirects to `admin/dashboard.php`; writes `login_success` to audit log. |
| **Invalid Password** | Submit wrong password for `admin` | Generic error message displayed; writes `login_failure` to audit log. |
| **Account Enumeration** | Submit nonexistent user `fakeuser99` | Identical generic error message returned to prevent user enumeration. |
| **Brute-Force Brake** | Submit 5 consecutive incorrect passwords | 6th attempt is throttled and locked out for 15 minutes; writes `login_blocked`. |
| **Session Hijacking** | Delete session cookie in browser dev tools | Instant session invalidation; redirects to `auth/login.php`. |
| **Session Regeneration** | Inspect session cookie before and after sign-in | Session ID changes immediately upon successful authentication. |

### 7.2 Authorization & Access Control

| Test Scenario | Test Action | Expected Result |
| :--- | :--- | :--- |
| **Voter accessing Admin** | Sign in as a voter, visit `admin/dashboard.php` | HTTP 403 Forbidden; writes `unauthorized_access` to audit log. |
| **Admin accessing Ballot** | Sign in as `admin`, visit `voter/ballot.php?election_id=1` | HTTP 403 Forbidden; admin cannot cast ballots. |
| **Premature Ballot Attempt** | Attempt to open ballot for scheduled election | HTTP 403; message displays start date; writes `ballot_closed_attempt`. |
| **Post-Close Ballot Attempt** | Attempt to open ballot for closed election | HTTP 403; voting window closed. |

### 7.3 Injection & Tampering Testing

| Test Scenario | Test Action | Expected Result |
| :--- | :--- | :--- |
| **SQL Injection (Login)** | Username: `' OR '1'='1' -- ` | Treated as literal string; authentication fails safely. |
| **SQL Injection (Search)** | Search input: `1'; DROP TABLE users; -- ` | Escaped PDO parameter; no data leak or table modification. |
| **Cross-Site Scripting (XSS)** | Candidate name: `<script>alert('XSS')</script>` | Sanitized via `htmlspecialchars`; rendered safely as plain text. |
| **Stored XSS (Biography)** | Bio: `<img src=x onerror=alert(1)>` | Escaped on all voter ballot and review screens. |
| **CSRF Bypass Attempt** | Remove hidden `csrf_token` input and submit form | HTTP 403 Forbidden ("Request blocked: invalid or missing security token"). |
| **Over-Voting Tampering** | Tamper checkbox inputs to select 3 candidates when `max_selections = 2` | Server re-validates and rejects the entire submission with an error. |

---

## 8. Project File Structure

```text
itp216_endterm/
├── index.php                     # Welcome portal & civic gateway
├── privacy.php                   # Data privacy policy (RA 10173 / GDPR aligned)
├── terms.php                     # Terms of service & electronic voting agreement
├── README.md                     # Comprehensive project documentation
├── config/
│   ├── config.php                # System constants, security headers, session hardening
│   └── database.php              # PDO connection factory (prepared statements, exceptions)
├── auth/
│   ├── login.php                 # Multi-method sign-in portal
│   ├── authenticate.php          # POST authentication handler (CSRF, bcrypt, throttling)
│   ├── register.php              # Self-service voter account registration
│   ├── logout.php                # POST-only, CSRF-protected sign-out handler
│   ├── google-login.php          # Google OAuth 2.0 authorization redirector
│   └── google-callback.php       # Google OAuth 2.0 token & profile callback handler
├── admin/
│   ├── dashboard.php             # Turnout metrics, alert center, recent audit activity
│   ├── elections.php             # Election lifecycle management (CRUD & states)
│   ├── positions.php             # Contest positions and selection limits
│   ├── candidates.php            # Candidate profiles and photo upload management
│   ├── voters.php                # Voter registry, status management, eligibility assignment
│   ├── results.php               # Live tallies, certified winners, CSV export
│   ├── audit-logs.php            # Searchable, filterable security audit log with CSV export
│   ├── security-testing.php      # CIA security testing & verification suite (17 automated tests)
│   └── settings.php              # Profile settings, password updates, database snapshots
├── voter/
│   ├── dashboard.php             # Voter dashboard, active elections, participation history
│   ├── elections.php             # Election catalog with status instructions
│   ├── ballot.php                # Interactive voting ballot with live selection limits
│   ├── review.php                # Two-stage ballot review and final submission handler
│   ├── confirmation.php          # Digital receipt display with verification code
│   ├── results.php               # Public and post-election tally inspection
│   └── profile.php               # Personal profile, voter code, and password management
├── includes/
│   ├── bootstrap.php             # Application bootstrap & error handler initialization
│   ├── auth.php                  # Authentication state, session lifecycle, RBAC guards
│   ├── csrf.php                  # Cryptographic CSRF token issue, rotation & verification
│   ├── flash.php                 # Session-persisted flash messaging system
│   ├── functions.php             # Escaping, formatting, sanitization, audit logging helpers
│   ├── voting.php                # Core voting engine: validation, submission, tallying
│   ├── icons.php                 # Accessible inline SVG icon library
│   ├── header.php                # Unified topbar, theme switcher, mobile drawer navigation
│   ├── footer.php                # Unified footer with TLS status & security indicators
│   └── error-page.php            # Branded HTTP 400, 403, 404, 500 exception pages
├── assets/
│   ├── css/
│   │   └── style.css             # Emerald design system (CSS variables, light/dark mode)
│   ├── js/
│   │   └── app.js                # Theme toggle, password visibility toggle, selection limits
│   └── images/
│       ├── logo.svg              # Brand logo mark
│       ├── favicon.svg           # Application favicon
│       └── candidates/           # Uploaded candidate profile images
├── database/
│   ├── secure_voting.sql         # Master database schema, constraints, and seed data
│   ├── migration_dismissed_alerts.sql # Migration for security alert tracking
│   ├── direct_secret_ballot.sql  # Secret ballot schema definitions
│   └── voter_auth.sql            # Voter authentication definitions
└── storage/
    └── logs/
        └── php-error.log         # Server error log destination
```

---

## 9. Voting Workflow & Life of a Ballot

```
 ┌─────────────────┐
 │ 1. Authenticate │  Sign in via Google OAuth, Voter Code, or Email/Password
 └────────┬────────┘
          ▼
 ┌─────────────────┐
 │ 2. Open Ballot  │  Server verifies election window and checks voter eligibility
 └────────┬────────┘
          ▼
 ┌─────────────────┐
 │ 3. Make Choices │  Client UI enforces selection limits; zero selections = abstention
 └────────┬────────┘
          ▼
 ┌─────────────────┐
 │ 4. Session Plan │  Validated selections cached in session ($_SESSION['_ballot_plan'])
 └────────┬────────┘
          ▼
 ┌─────────────────┐
 │ 5. Review Stage │  Voter reviews exact choices before irrevocable submission
 └────────┬────────┘
          ▼
 ┌─────────────────┐
 │ 6. ACID Commit  │  InnoDB transaction begins with row locks (FOR UPDATE):
 └────────┬────────┘  • Re-checks eligibility and duplicate ballot constraint
                      • Inserts anonymous ballot record into `ballots`
                      • Inserts choice rows into `votes`
                      • Updates `voter_eligibility.has_voted = 1`
                      • Appends `ballot_cast` event to `audit_logs`
                      • Commits transaction atomically (or rolls back on error)
          ▼
 ┌─────────────────┐
 │ 7. Confirmation │  Voter receives verifiable receipt code (SV-2026-XXXXXX)
 └─────────────────┘
```

---

## 10. Production Hardening Guidelines

For production environments, follow these hardening steps:

### 10.1 Dedicated Database User
Create a least-privileged database user restricted to data manipulation on the SecureVote schema:

```sql
CREATE USER 'securevote_app'@'localhost' IDENTIFIED BY 'STRONG_SECRET_PASSWORD_HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON `u184230832_securevote`.* TO 'securevote_app'@'localhost';
FLUSH PRIVILEGES;
```

### 10.2 Production Debug Configuration
In `config/config.php`, ensure `APP_DEBUG` is set to `false` to suppress all client-facing diagnostic outputs:

```php
define('APP_DEBUG', false);
```

### 10.3 HTTPS & SSL Enforcement
Deploy behind an SSL/TLS certificate (e.g. Let's Encrypt). The application automatically:
* Enables `Strict-Transport-Security` (HSTS) headers.
* Sets the `Secure` flag on all session cookies.

### 10.4 Web Server Directory Protection
Ensure that `storage/` and `database/` directories are inaccessible via direct URL requests by configuring your web server (e.g. `.htaccess` or Nginx rules).

---

## 11. Troubleshooting & FAQ

| Symptom | Probable Cause | Recommended Fix |
| :--- | :--- | :--- |
| **"Service temporarily unavailable"** | Database server is offline or credentials in `config/config.php` are incorrect. | Verify MySQL is active and check `DB_HOST`, `DB_USER`, and `DB_PASS`. |
| **Missing CSS Styling or Broken Layout** | Web server is serving from an unexpected virtual directory path. | Confirm project is in `itp216_endterm` or check `BASE_URL` calculation in `config/config.php`. |
| **Session Expiring Immediately** | Browser blocking cookies or incorrect permissions on PHP session directory. | Check browser cookie settings and verify `session.save_path` is writable. |
| **"Request blocked: invalid or missing security token"** | CSRF token mismatch due to tab inactivity or expired session. | Refresh the page to obtain a fresh CSRF token and resubmit. |
| **Google Sign-In Error** | Redirect URI mismatch or missing Google API credentials. | Ensure `http://localhost/itp216_endterm/auth/google-callback.php` is listed in Google Cloud Console authorized URIs. |
| **Candidate Photo Upload Fails** | Directory `assets/images/candidates/` is not writable by web server. | Adjust directory write permissions: `chmod 775 assets/images/candidates/`. |

---

## 12. References & Standards

* **OWASP Top 10 Web Application Security Risks**: [https://owasp.org/www-project-top-ten/](https://owasp.org/www-project-top-ten/)
* **OWASP Cross-Site Request Forgery Prevention**: [https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
* **OWASP File Upload Security Cheat Sheet**: [https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
* **PHP PDO Prepared Statements**: [https://www.php.net/manual/en/pdo.prepared-statements.php](https://www.php.net/manual/en/pdo.prepared-statements.php)
* **MySQL InnoDB Transaction Isolation**: [https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-model.html](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-model.html)
* **Republic Act No. 10173 (Data Privacy Act of 2012)**: [https://privacy.gov.ph/data-privacy-act/](https://privacy.gov.ph/data-privacy-act/)

---

*SecureVote is an academic endterm project created for educational demonstration of information assurance, cryptographic integrity, and secure web application development. It is not affiliated with any government electoral body.*

## Election preparation and student support

The site includes seven additional tools in the existing navigation:

- **Compare candidates** (`voter/compare.php`): signed-in voters compare active candidate affiliations, biographies, and full platforms by election and position. Draft elections stay hidden.
- **Announcements** (`announcements.php`, `admin/announcements.php`): administrators create, edit, publish, or withdraw global/election updates. Only published updates for published elections appear publicly. These are website announcements, not email or push notifications.
- **Practice ballot** (`practice.php`): a fictional single-choice and multi-choice ballot with review, edit, abstention, and completion. No practice selections are persisted, no receipt is generated, and eligibility is untouched.
- **Readiness checklist** (`admin/readiness.php`): advisory checks for schedules, positions/candidates, active voter assignments, candidate information, and selection limits. It does not replace server-side ballot validation. The existing “Open now” action automatically enrols active voters; scheduled elections must have eligibility assigned beforehand.
- **CSV import** (`admin/import-voters.php`): UTF-8 CSV with exactly `username,email,first_name,last_name`; maximum 1 MB and 500 rows. Download a header template, choose an election, preview, then confirm within 15 minutes. File duplicates, account conflicts, administrators, and inactive/blocked voters prevent import. Existing accounts require matching username **and** email; profile, credentials, and participation are preserved. New voters receive code-only accounts; distribute their generated voter codes privately through the registry. Imports are transactional and only enrol voters in the selected election.
- **Receipt check** (`verify.php`): POST-only lookup returns recorded/not found and the election title. It does not return identity, choices, or timestamps and does not prove tally correctness. Database-backed IP limits allow 20 checks per 15-minute window. Receipt values are excluded from URLs and audit descriptions; responses disable caching.
- **Help and issue reports** (`help.php`, `admin/support.php`): FAQ, guest/signed-in issue submission, administrator status updates and replies, and account-scoped report history. Guest reporters must follow up with the administrator using their reference. No email is sent. Limits allow five reports per connection per hour. Reporters are instructed not to include credentials, receipt codes, or selections.

### Database setup

For an existing installation, back up the database and run `database/migration_community_features.sql` in the configured database using phpMyAdmin or the MySQL client. It adds `announcements`, `support_tickets`, and `feature_rate_limits` without changing votes. Fresh schema files include these tables. If tables are missing, the new community pages attempt this same additive migration once on first use; installations whose app account lacks CREATE privileges need the manual migration.

Deploy the new PHP routes, `includes/community.php`, `includes/voter-import.php`, `assets/css/community.css`, and updated shared/navigation/authentication files together. Support reports contain personal information; administrators should handle retention/removal according to institutional policy. The application does not automatically purge reports.

### Feature verification

`tests/community-features.php` checks CSV validation, account protection, code-only authentication, import transactions, participation preservation, practice ballot rules, readiness queries, migrations, and persistent rate limits. It refuses to run without `SV_TEST_ALLOW_WRITES=1` and a disposable socket beginning `/tmp/securevote-feature`. Load the primary schema into a temporary MariaDB instance first and set the `SV_DB_*` environment variables. Never point tests at production.

`tests/community-browser.cjs` uses Playwright with installed Chrome against the isolated fixture preview at `http://127.0.0.1:8765`. After loading a fresh disposable primary schema, run `tests/community-fixtures.php` with the same test database environment and `SV_TEST_ALLOW_WRITES=1` to create the fixtures. The browser suite expects fixture accounts `admin` and `browserstudent` with the test-only password `FeatureTest123`, a submitted fixture receipt `SV-2026-ABC123`, and seeded primary election candidates. It exercises all seven flows, CSRF/role checks, student report isolation, and desktop/mobile layouts. Screenshots are written under `/tmp/securevote-feature-screens`. These accounts and credentials are test fixtures only and must never be installed on a deployed site.

### Election engagement features

- `timeline.php` displays registration/campaign dates from `admin/engagement.php`, authoritative voting dates, and an optional results announcement. Unset phases say “Not announced.” Informational phase dates do not enforce registration/campaign access or override results policy.
- `voter/reminders.php` computes in-app opening reminders within 24 hours, current voting reminders, and separate closing reminders within 24 hours for assigned voters without a submitted ballot. Dismissals persist per account and schedule; alerts refresh on page visits, with no email or background push service.
- `candidate-qa.php` shows only administrator-published answers for active candidates in published elections. Administrators enter candidate-supplied answers as draft, publish after review, or withdraw them. No sample answers are installed in production.
- The English/Filipino control translates navigation, new feature pages, and core voter instructions; candidate names, platforms, administrator-authored content, and remaining operational copy retain their supplied language. The language choice stays in the session and changes via CSRF-protected POST.
- Receipt history links select the correct election. Receipts require account ownership, omit login voter codes and candidate choices, and support Print / Save PDF through an external CSP-compatible script.

Apply `database/migration_engagement.sql` once on existing installations; it is additive and repeatable. New routes also attempt setup on first use. Deploy the shared locale helper before the updated bootstrap. No admin two-factor authentication is included.

Checks: `tests/engagement-features.php` uses the same disposable-database guard as the community suite. `tests/engagement-browser.cjs` requires local fixture accounts and a currently open election #1; it creates test answers and schedules only on the isolated localhost preview. Never run the fixture setup on production.

### Recovery, election resources, reports, and reading preferences

Apply `database/migration_next_features.sql` (additive, repeatable) before deploying the updated authentication/shared bootstrap. Deploy `includes/next-features.php` with the migration. The new helpers also attempt first-use setup. Existing sessions stay valid until a password reset changes that account's session version.

Password recovery is available at `auth/forgot-password.php`. It uses generic responses, persistent IP and account limits, 256-bit random tokens stored only as SHA-256 digests, a 30-minute expiry, one-use consumption under database locks, and revocation of previous sessions after reset. Reset links use trusted `SV_PUBLIC_ORIGIN` (defaulting to the configured Hostinger `/securevote` deployment), never the request Host header. PHP `mail()` uses Hostinger's configured transport; `SV_MAIL_FROM` optionally overrides the default `no-reply@darkblue-hummingbird-464264.hostingersite.com`. Configure a sender your hosting provider permits and monitor delivery/bounces. Transport failure invalidates that token and logs a generic diagnostic without the token or email body. Mail acceptance does not verify inbox delivery. Password inputs accept 12–72 bytes, matching the underlying password-hashing limit. Recovery emails are not sent by automated tests; tests inject a local mail callback.

`admin/resources.php` edits organization/department/school-year classifications and publishes or withdraws forum events, debate video links, and campaign materials. `forum.php` displays only published resources for published elections. Resource links require HTTPS and open safely in a separate tab; remote media is not embedded or fetched by the server. Election filters use these explicitly supplied classifications; older elections remain unclassified until edited.

`changes.php` displays forward-only change summaries for schedules, candidate profiles/rosters, classifications, status, and results policy. `election_snapshots` establishes a baseline when enabled; initial import does not manufacture past history. Successful writes through the election, candidate, timeline, and metadata editors record comparisons. Direct SQL edits and external tools bypass these application hooks. Changes expose field categories and timestamps, never administrator identities or private before/after content.

`results-report.php?election_id=ID` generates a paginated PDF with aggregate turnout, tallies, seat outcomes/ties, and generation time. It checks the same results visibility policy before collecting tallies; administrators can export through the Results export menu, and permitted voters through a report link. Live reports are labeled non-final. PDF standard fonts use WinAnsi with transliteration for unsupported characters; full original names remain on the website. No voter selections or identities are exported.

The voter sidebar is reduced to core destinations. `guide.php` groups preparation, updates, and voting help; the dashboard retains a guide link and reminder count. Display & language contains persistent text-size and high-contrast preferences, stored locally in the browser, and session-based language selection. A reset control clears reading preferences.

`tests/next-features.php` and `tests/next-browser.cjs` validate the new features using the disposable feature database and local fixture server only. Neither fixture nor test data is deployed. SecureVote v1 remains an earlier interface; shared account security protections also apply to its existing-account login.
