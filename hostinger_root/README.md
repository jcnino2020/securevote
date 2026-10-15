# Hostinger Dynamic Workspace Hub & Portal

An auto-discovering, creative workspace portal for your Hostinger root domain (`https://darkblue-hummingbird-464264.hostingersite.com/`).

Crafted following the **Impeccable Craft Standard**:
- **Automatic Subfolder Detection**: Whenever you upload or create a new subfolder in `public_html/` (e.g., `/securevote/`, `/aims/`, or any future application), it appears automatically without touching any code.
- **Rich Metadata Extraction**: Automatically parses project titles, documentation (`PRODUCT.md`, `README.md`), tech stacks (PHP, MySQL, Node, Static), entry points (`index.php`, `index.html`), and icons/favicons.
- **Customizable**: Drop an optional `meta.json` into any subfolder to customize badges, titles, and tags.
- **Impeccable UI/UX**:
  - Live real-time search with instant keyboard shortcut (`/` or `Cmd+K`).
  - Grid View & Dense List View toggle.
  - Interactive direct launch and "Copy URL" button with toast notification.
  - Live server telemetry (PHP engine, domain status, active count).
  - Deep space aesthetic with high-contrast typography, harmonic monogram emblems, and zero clutter.

---

## How to Install on Hostinger

### Step 1: Open Hostinger File Manager
1. Log into your **Hostinger hPanel** (`https://hpanel.hostinger.com`).
2. Go to **Websites** &rarr; select `darkblue-hummingbird-464264.hostingersite.com` &rarr; click **File Manager**.
3. Navigate into the **`public_html/`** folder.

### Step 2: Upload Files
Upload the following files directly into `public_html/`:
1. `default.php` (replaces Hostinger's default placeholder page)
2. `index.php` (identical copy so it works under any LiteSpeed / Apache server index order)
3. `.htaccess` (ensures `DirectoryIndex default.php index.php` is active)

### Step 3: Your Subfolders Structure
In `public_html/`, your directory structure will look like this:
```text
public_html/
├── default.php                     <-- The dynamic hub!
├── index.php                       <-- The dynamic hub!
├── .htaccess                       <-- Server configuration
│
├── securevote/                     <-- https://darkblue-hummingbird-464264.hostingersite.com/securevote/
│   ├── index.php
│   ├── meta.json (optional)
│   └── ...
│
├── aims/                           <-- https://darkblue-hummingbird-464264.hostingersite.com/aims/
│   ├── index.php
│   ├── meta.json (optional)
│   └── ...
│
└── any-new-folder/                 <-- Auto-detected immediately upon upload!
```

---

## (Optional) Customizing Project Cards with `meta.json`

If you want custom badges, descriptions, or tags for any project, drop a `meta.json` file inside that project's folder.

Example for `public_html/aims/meta.json`:
```json
{
  "title": "AIMS Portal",
  "description": "Academic Information Management System for student records, enrollment tracking, and faculty evaluations.",
  "badge": "Core System",
  "accent": "#10b981",
  "tags": ["Academic", "Student Records", "PHP", "Database"]
}
```

Even without `meta.json`, the hub will format the name (e.g., `aims` &rarr; `AIMS`, `securevote` &rarr; `SecureVote`), detect the tech stack, generate a monogram emblem with a unique color hue, and compute the last modified timestamp!
