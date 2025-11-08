# HB Booking - Modern WordPress Booking Plugin

A modern, clean-coded WordPress booking plugin built with the latest technologies and best practices.

## Features

- **User-Friendly Booking Form** - Simple, responsive form for customers to book appointments
- **Admin Dashboard** - Complete booking management interface
- **Calendar Integration** - Support for Google Calendar and iCal formats
- **Email Notifications** - Automatic emails to customers and admin
- **REST API** - Modern REST API for AJAX submissions
- **Customer Portal** - View booking history (for logged-in users)
- **Responsive Design** - Mobile-first, modern UI
- **Clean Code** - PSR-4 autoloading, SOLID principles, WordPress coding standards

## Installation

1. Download the plugin folder
2. Upload to `wp-content/plugins/` directory
3. Run `composer install` in the plugin directory to generate autoloader
4. Activate the plugin through the WordPress admin
5. Configure settings at **Bookings > Settings**

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Composer (for development)

## Usage

### Display Booking Form

Add this shortcode to any page or post:

```
[hb_booking_form]
```

Optional attributes:
```
[hb_booking_form title="Schedule an Appointment" show_service="true"]
```

### Display Customer Bookings

For logged-in users to view their bookings:

```
[hb_customer_calendar]
```

## File Structure

```
hb-booking/
├── hb-booking.php           # Main plugin file
├── composer.json            # Composer configuration
├── README.md               # Documentation
├── src/                    # Source code (PSR-4)
│   ├── Core/              # Core functionality
│   │   ├── Installer.php  # Database installer
│   │   ├── Database.php   # Database operations
│   │   └── Assets.php     # Asset management
│   ├── Frontend/          # Frontend components
│   │   ├── BookingForm.php
│   │   └── CustomerCalendar.php
│   ├── Admin/             # Admin components
│   │   └── BookingAdmin.php
│   ├── Api/               # REST API
│   │   └── BookingApi.php
│   └── Services/          # Business logic
│       ├── EmailService.php
│       └── CalendarService.php
└── assets/                # Frontend assets
    ├── css/
    │   ├── frontend.css
    │   └── admin.css
    └── js/
        ├── frontend.js
        └── admin.js
```

## Architecture

### Design Patterns

- **Singleton Pattern** - For service classes
- **Repository Pattern** - Database layer abstraction
- **Service Layer** - Business logic separation
- **PSR-4 Autoloading** - Modern PHP autoloading

### Code Quality

- **SOLID Principles** - Single Responsibility, Open/Closed, etc.
- **WordPress Coding Standards** - Following official standards
- **Security** - Data sanitization, validation, nonce verification
- **Modern PHP** - Type declarations, null safety operators
- **Clean Code** - Readable, maintainable, documented

## API Endpoints

### REST API

Base URL: `/wp-json/hb-booking/v1`

#### Create Booking
```
POST /bookings
```

#### Get All Bookings (Admin)
```
GET /bookings
```

#### Get Single Booking
```
GET /bookings/{id}
```

#### Update Booking (Admin)
```
PUT /bookings/{id}
```

#### Delete Booking (Admin)
```
DELETE /bookings/{id}
```

#### Check Availability
```
GET /check-availability?date=YYYY-MM-DD&time=HH:MM
```

## Database Schema

### Table: `wp_hb_bookings`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| customer_name | VARCHAR(255) | Customer full name |
| customer_email | VARCHAR(255) | Customer email |
| customer_phone | VARCHAR(50) | Customer phone |
| booking_date | DATE | Appointment date |
| booking_time | TIME | Appointment time |
| service | VARCHAR(255) | Service type |
| notes | TEXT | Additional notes |
| status | VARCHAR(20) | pending/confirmed/cancelled/completed |
| google_event_id | VARCHAR(255) | Calendar event ID |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

## Customization

### Hooks and Filters

#### Filters

**Modify services list:**
```php
add_filter('hb_booking_services', function($services) {
    $services['custom'] = 'Custom Service';
    return $services;
});
```

**Modify time slots:**
```php
add_filter('hb_booking_start_hour', function() { return 8; });
add_filter('hb_booking_end_hour', function() { return 18; });
add_filter('hb_booking_interval', function() { return 60; }); // minutes
```

## Calendar Integration

### iCal Format

Automatically generates `.ics` files for each booking that can be imported into any calendar application. To enable:

1. Go to **Bookings > Settings**
2. Select "iCal" from the Calendar Integration dropdown
3. Save settings

### Google Calendar

Built-in Google Calendar integration with OAuth2 authentication. To set up:

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a new project or select an existing one
3. Enable the Google Calendar API
4. Create OAuth 2.0 credentials (Web application type)
5. Add your WordPress site URL to authorized redirect URIs: `https://yoursite.com/wp-admin/admin.php`
6. Copy the Client ID and Client Secret
7. In WordPress, go to **Bookings > Settings**
8. Select "Google Calendar" from Calendar Integration dropdown
9. Paste your Client ID and Client Secret
10. Set your Calendar ID (use "primary" for your main calendar)
11. Save settings and click "Authorize with Google"
12. Complete the OAuth authorization flow

Once connected, all new bookings will automatically be added to your Google Calendar with:
- Event title with service name
- Customer details in description
- Customer and admin as attendees
- Email and popup reminders

The integration also supports:
- Updating events when bookings are modified
- Removing events when bookings are cancelled or deleted

## Email Notifications

The plugin sends HTML emails for:

- **Customer Confirmation** - Booking details after submission
- **Admin Notification** - New booking alert
- **Status Updates** - When booking status changes

Templates are customizable in `src/Services/EmailService.php`

## Security

- All data is sanitized and validated
- REST API uses WordPress nonces for authentication
- SQL queries use prepared statements
- XSS protection with proper escaping
- CSRF protection with nonce verification

## Browser Support

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)

## Development

### Setup

```bash
# Install dependencies
composer install

# For static analysis (optional)
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse src
```

### Code Style

Follow WordPress Coding Standards:
- PHP: WordPress-Core
- JavaScript: WordPress JavaScript Coding Standards
- CSS: WordPress CSS Coding Standards

## Support

For issues and feature requests, please contact the plugin author.

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Booking form with validation
- Admin dashboard
- Email notifications
- Calendar integration (iCal)
- REST API endpoints
- Customer booking portal

## Credits

Developed with WordPress best practices and modern PHP standards.
