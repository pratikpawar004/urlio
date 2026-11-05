# URLIO

### Short links. Simple sharing.

A lightweight URL shortener built with **PHP, MySQL, and a responsive web interface**.

🌐 **Live Website:** [https://pawar.rf.gd/urlio/](https://pawar.rf.gd/urlio/)

---

## About

**URLIO** is a web-based URL shortener that transforms long URLs into short, easy-to-share links. Create links as a guest or sign in to manage your links from your personal dashboard.

## Features

- **Guest Shortening** – Create short links without registration.
- **User Accounts** – Register, log in, and manage your links.
- **Guest Link Transfer** – Transfer guest-created links to your account.
- **Bulk Shortening** – Shorten multiple URLs at once.
- **Custom Aliases** – Create memorable custom short links.
- **Link Expiry** – Set link expiration dates.
- **Click Limits** – Configure maximum clicks.
- **Password Protection** – Protect selected short links.
- **Analytics** – View click statistics and visitor information.
- **QR Codes** – Generate QR codes for your links.
- **CSV Export** – Export link and click data.
- **Admin Panel** – Manage users, links, and blocked domains.
- **Responsive Design** – Works on desktop and mobile.

## Tech Stack

| Technology | Purpose |
|---|---|
| PHP | Backend |
| MySQL | Database |
| PDO | Database connection |
| HTML | Structure |
| CSS | Styling |
| JavaScript | Interactivity |
| InfinityFree | Hosting |

## Live Demo

Visit the live website:

**[🚀 Open URLIO](https://pawar.rf.gd/urlio/)**

## Project Structure

```text
urlio/
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
├── includes/
│   ├── bootstrap.php
│   ├── config.php
│   ├── layout.php
│   └── secret.key
├── account.php
├── actions.php
├── admin.php
├── api.php
├── dashboard.php
├── export.php
├── go.php
├── index.php
├── install.php
├── link.php
├── login.php
├── logout.php
└── register.php
```

## Installation

### Requirements

- PHP 8.0 or newer recommended
- MySQL or MariaDB
- PHP PDO MySQL extension
- Apache or another PHP-compatible server

### Setup Instructions

1. Clone or download this repository.
2. Create a MySQL database.
3. Configure your database credentials in `includes/config.php`.
4. Set the correct application URL.
5. Start Apache and MySQL.
6. Open URLIO in your browser.
7. Complete the installation process if required.
8. Remove `install.php` from the public server after installation.

## How to Use

1. Open the [URLIO website](https://pawar.rf.gd/urlio/).
2. Enter your long destination URL.
3. Generate your short link.
4. Copy and share your new short URL.

Create an account to access your dashboard and manage your links.

## API

### Coming Soon 🚧

API access and public API documentation are planned for a future release.

Stay tuned for updates!

## Current Configuration

| Setting | Value |
|---|---:|
| Guest Link Validity | 30 Days |
| Guest Creation Limit | 10 Links / Hour / IP |
| Maximum Bulk Batch | 50 URLs |

*Configuration values may change in future updates.*

## Security

- Never publish database passwords or API keys.
- Keep `includes/config.php` and `includes/secret.key` private.
- Never upload private user data or database exports.
- Remove the installer after setup.
- Always use HTTPS on the live website.
- Use strong passwords.

## Project Status

**URLIO is an ongoing personal project.**

🌐 Website: [https://pawar.rf.gd/urlio/](https://pawar.rf.gd/urlio/)

🔌 API: **Coming Soon**

---

## License

No open-source license has currently been specified. All rights remain with the copyright holder. Reuse and redistribution are not automatically permitted.

---

<div align="center">

**Made with ❤️ using PHP and MySQL**

### URLIO – Short links. Simple sharing.

</div>
