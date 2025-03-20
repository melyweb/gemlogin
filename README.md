# GemLogin Scheduler

A web application to manage GemLogin profiles, schedule tasks, and automate browser operations.

## Features

- User authentication system
- Profile management (view, create, edit, delete)
- Script management (view, execute)
- Scheduling system (create, edit, run, stop schedules)
- Profile fingerprint management
- Automated task execution via cron jobs

## Requirements

- PHP 8.0 or higher
- MySQL/MariaDB
- Web server (Apache, Nginx, etc.)
- Composer
- GemLogin API service running (default: http://localhost:1010)

## Installation

1. Clone the repository to your web server:

```bash
git clone <repository-url> /path/to/your/webserver
cd /path/to/your/webserver
```

2. Install PHP dependencies using Composer:

```bash
composer install
```

3. Configure your database settings in `config/database.php` if needed (default uses root with no password).

4. Configure your GemLogin API URL in `config/constants.php` if different from the default.

5. Run the setup script:

```bash
php setup.php
```

This will:
- Create the necessary database tables
- Create a default admin user (username: admin, password: admin123)
- Sync profiles and scripts from the GemLogin API
- Provide instructions for setting up the cron job

6. Set up the cron job to run the scheduler script every minute:

```bash
crontab -e
```

Add the following line:

```
* * * * * php /path/to/your/webserver/cron/run_scheduler.php
```

7. Access the application through your web browser:

```
http://your-server/path-to-app/
```

## Usage

### Authentication

- Use the default credentials to log in (username: admin, password: admin123)
- It's recommended to change the password after first login

### Managing Profiles

- View all profiles on the Profiles page
- Create, edit, and delete profiles as needed
- Change fingerprint for multiple profiles at once
- Start profiles directly from the interface

### Working with Scripts

- View available scripts on the Scripts page
- Scripts are managed through the GemLogin API
- View script details including required parameters

### Creating Schedules

1. Go to the Create Schedule page
2. Enter a name for the schedule
3. Select a script to run
4. Set start and end times for the schedule
5. Configure profile and loop delays
6. Select the profiles to run the script on
7. Click Create Schedule

### Managing Schedules

- View all schedules on the Schedules page
- Start, stop, and delete schedules as needed
- View details of schedule runs and logs

## Cronjob Setup

For schedules to run automatically, ensure the cron job is set up properly:

```
* * * * * php /path/to/your/webserver/cron/run_scheduler.php
```

This will:
1. Start pending schedules when their start time is reached
2. Stop running schedules when their end time is reached
3. Execute scripts on profiles based on schedule parameters

## Security Considerations

- Change the default admin password immediately after setup
- Ensure your web server is properly secured
- Consider using HTTPS for production environments
- Limit access to the application to trusted users only

## License

This project is licensed under the MIT License - see the LICENSE file for details.
