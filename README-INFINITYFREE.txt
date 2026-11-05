URLIO — InfinityFree Deployment Guide
=====================================

Requirements
- InfinityFree hosting account with PHP and MySQL enabled
- A MySQL database created from the InfinityFree Control Panel
- Domain/subdomain pointed to this hosting account

Upload
1. Extract this ZIP on your computer.
2. Upload all project files and folders to the website's htdocs directory.
3. Make sure the root .htaccess file is uploaded. It enables short URLs such as:
   https://urlio.rf.gd/Ab12Xy

Database
1. Create a MySQL database in the InfinityFree Control Panel.
2. Use the database values already configured in includes/config.php:
   Host: sql303.infinityfree.com
   Database: if0_43362753_urlio
   Username: if0_43362753
   Password: the password shown in your InfinityFree control panel.
   If you reset the database password, update DB_PASS in includes/config.php.
3. Update DB_HOST, DB_NAME, DB_USER and DB_PASS in includes/config.php.
4. Keep APP_DEBUG set to false on the live website.
5. APP_URL is set to https://urlio.rf.gd. Change it if your final domain differs.

URLIO features
- Guest shortening without registration
- Maximum 10 guest links per hour per IP address
- Guest links expire 30 days after creation
- Guest links created in the same browser can transfer to an account when the visitor registers or logs in
- Registered accounts can manage links, analytics and advanced options
- Responsive English-language interface

Important hosting notes
- Expired guest links are checked when a short link is opened; a cron job is not required.
- InfinityFree free hosting has resource and feature limitations. Avoid heavy traffic or large bulk jobs.
- PHP mail() and scheduled cron tasks may not be available on the free plan.
- Do not share includes/config.php. It contains database credentials.
- Change the database password if the config file has ever been shared publicly.

Troubleshooting
- Database connection error: verify the MySQL hostname and credentials from the control panel.
- Short links return 404: confirm .htaccess is uploaded and Apache rewrite support is active.
- Wrong generated domain: confirm APP_URL matches your active domain and uses HTTPS.
