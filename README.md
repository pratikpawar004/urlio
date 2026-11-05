\# URLIO



\### Short links. Simple sharing.



A lightweight URL shortener built with \*\*PHP, MySQL, and a responsive web interface\*\*.



🌐 \*\*Live Website:\*\* https://pawar.rf.gd/urlio/



\---



\## About



\*\*URLIO\*\* is a web-based URL shortener that turns long destination links into short, shareable URLs. Create links as a guest or sign in to manage them from your dashboard.



\## Features



\- Guest link shortening without registration

\- User registration and login

\- Guest-link transfer to user accounts

\- Bulk URL shortening

\- Custom short aliases

\- Link expiry and click limits

\- Optional password protection

\- Click analytics

\- QR code generation

\- CSV export

\- Admin panel

\- Responsive design



\## Tech Stack



\- PHP

\- MySQL / MariaDB

\- PDO

\- HTML, CSS, JavaScript

\- InfinityFree Hosting



\## Live Demo



\*\*https://pawar.rf.gd/urlio/\*\*



\## Project Structure



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



\## Installation



1\. Clone or download this repository.

2\. Create a MySQL database.

3\. Configure database credentials in `includes/config.php`.

4\. Set the correct application URL.

5\. Start Apache and MySQL.

6\. Open the application in your browser.

7\. Complete installation if required.

8\. Remove the installer after setup.



\## Usage



1\. Visit the URLIO website.

2\. Enter a long URL.

3\. Generate your short link.

4\. Copy and share it.



Register to access your dashboard and manage your links.



\## API



\*\*Coming Soon\*\*



API access and public API documentation are planned for a future update.



\## Current Configuration



| Setting | Value |

|---|---:|

| Guest link validity | 30 days |

| Guest creation limit | 10 links/hour/IP |

| Maximum bulk batch | 50 URLs |



\## Security



\- Never publish database credentials or API keys.

\- Keep `includes/config.php` and `includes/secret.key` private.

\- Do not upload user data or database exports.

\- Remove the installer after setup.

\- Use HTTPS.

\- Use strong passwords.



\## Project Status



URLIO is an ongoing personal project.



\- Website: https://pawar.rf.gd/urlio/

\- API: Coming Soon



\## License



No license has currently been specified. All rights remain with the copyright holder. Reuse and redistribution are not automatically permitted.



