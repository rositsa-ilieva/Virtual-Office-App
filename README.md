# Virtual Office Queue System

A web-based queue management system for virtual office hours and consultations.

## Setup Instructions

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser
- PHP 8.0 or higher

### Installation Steps

1. Install XAMPP:
   - Download XAMPP from https://www.apachefriends.org/
   - Run the installer and follow the installation wizard
   - Make sure to select Apache, MySQL, and PHP components

2. Clone or download this repository to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\Virtual-Office-App
   ```

3. Start XAMPP Control Panel:
   - Open XAMPP Control Panel
   - Start Apache and MySQL services
   - Verify both services are running (green status)

4. Set up the database:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Click "New" in the left sidebar
   - Enter "virtual_office" as the database name
   - Click "Create"
   - Select the "virtual_office" database
   - Click the "Import" tab
   - Click "Choose File" and select the `init.sql` file from the project
   - Click "Go" to import

5. Access the application:
   - Open your web browser
   - Navigate to: http://localhost/Virtual-Office-App

### Example Login Accounts

#### Teacher Accounts
1. Maria Ivanova
   - Email: maria@gmail.com
   - Password: 12345678

2. Peter Petrov
   - Email: peter@gmail.com
   - Password: 12345678

#### Student Accounts
1. Ivan Georgiev
   - Email: ivan@gmail.com
   - Password: 12345678

2. Anna Petrova
   - Email: anna@gmail.com
   - Password: 12345678

3. Georgi Dimitrov
   - Email: georgi@gmail.com
   - Password: 12345678

### Features
- Queue management for virtual office hours
- Real-time queue updates
- Student-teacher messaging
- Queue statistics and analytics
- Position swapping between students
- Meeting scheduling and management
- Notifications system
- Role-based access control

### Database Configuration
The database connection settings can be modified in `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'virtual_office');
```

### Troubleshooting
1. If you see a database connection error:
   - Verify MySQL is running in XAMPP Control Panel
   - Check if the database name matches in config.php
   - Ensure the database user has proper permissions

2. If the page doesn't load:
   - Check if Apache is running
   - Verify the project is in the correct htdocs folder
   - Check Apache error logs in XAMPP Control Panel

3. If you can't log in:
   - Verify the database was imported correctly
   - Check if the users table has the test data
   - Try clearing your browser cache
