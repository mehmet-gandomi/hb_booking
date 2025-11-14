# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HB Booking is a WordPress plugin providing a modern booking system with calendar integration. The plugin is built with PHP 8.0+ and follows PSR-4 autoloading standards via Composer.

**Key Features:**
- Booking management (CRUD operations)
- Persian (Jalali) and Gregorian calendar support
- Google Calendar integration with OAuth 2.0
- iCal event generation
- Email notifications
- REST API endpoints
- Admin dashboard with calendar view

## Architecture

### Namespace Structure

The plugin uses the `HB\Booking` namespace with PSR-4 autoloading:

```
HB\Booking\
├── Core\          - Core infrastructure (Database, Installer, Assets)
├── Admin\         - Admin dashboard and settings
├── Frontend\      - Public-facing forms and calendars
├── Api\           - REST API endpoints
└── Services\      - Business logic (Email, Calendar, DateConverter)
```

### Singleton Pattern

All major classes use singleton pattern via `getInstance()`. Components are initialized in [hb-booking.php:92-113](hb-booking.php#L92-L113) within the main `Plugin::init()` method.

### Database Layer

The `Database` class ([src/Core/Database.php](src/Core/Database.php)) is the single point of access for all database operations:
- Uses WordPress `$wpdb` for database queries
- Table name: `{$wpdb->prefix}hb_bookings`
- All data is sanitized via `sanitizeBookingData()` method
- Availability checking via `isTimeSlotAvailable()` ensures no double-bookings

**Important:** All dates are stored in Gregorian format (YYYY-MM-DD) in the database, regardless of the calendar type setting. Use `DateConverter` service for conversion between display and storage formats.

### Calendar System

The `DateConverter` service ([src/Services/DateConverter.php](src/Services/DateConverter.php)) handles bidirectional conversion between Gregorian and Jalali (Persian) calendars:

- **From User Input to Database:** Use `prepareForDatabase($date)` - converts Jalali to Gregorian if needed
- **From Database to Display:** Use `formatDate($gregorian_date)` - converts based on `hb_booking_calendar_type` setting
- Uses the `morilog/jalali` package for Jalali calendar operations
- Calendar type is controlled by the `hb_booking_calendar_type` WordPress option ('gregorian' or 'jalali')

### Google Calendar Integration

OAuth 2.0 flow handled in [src/Admin/BookingAdmin.php:561-643](src/Admin/BookingAdmin.php#L561-L643):
1. Admin initiates auth at Settings page
2. Redirects to Google OAuth with proper scope
3. Exchange code for refresh token
4. Store refresh token in WordPress options

Calendar operations in [src/Services/CalendarService.php](src/Services/CalendarService.php):
- Access tokens obtained on-demand via refresh token
- Events include 1-hour duration by default
- Event IDs stored in `google_event_id` column for updates/deletes

### REST API

API endpoints defined in [src/Api/BookingApi.php](src/Api/BookingApi.php) extend `WP_REST_Controller`:

- **Namespace:** `hb-booking/v1`
- **Public endpoints:** POST `/bookings` (create), GET `/check-availability`
- **Admin-only endpoints:** GET `/bookings`, GET/PUT/DELETE `/bookings/{id}`
- Uses WordPress REST API authentication
- Public access controlled via `allowPublicAccess()` filter on line 408

## Development Commands

### Dependencies

```bash
# Install Composer dependencies
composer install

# Update dependencies
composer update
```

### PHP Syntax Check

```bash
# Check specific file
php -l src/Core/Database.php

# Check all PHP files
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

### WordPress Development

This plugin runs within WordPress environment. Testing requires:
- WAMP/XAMPP or similar local server with PHP 8.0+
- WordPress 6.0+
- WordPress debug mode enabled for development

Enable debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

View logs at Admin > Bookings > Debug Logs or check `wp-content/debug.log`

## Code Patterns

### Adding a New Database Field

1. Update schema in [src/Core/Installer.php:30-47](src/Core/Installer.php#L30-L47)
2. Add sanitization in [Database::sanitizeBookingData()](src/Core/Database.php#L172-L213)
3. Update REST API args in [BookingApi::getCreateArgs()](src/Api/BookingApi.php#L348-L386)
4. Update admin table display in [BookingAdmin::renderBookingsPage()](src/Admin/BookingAdmin.php#L109-L202)

### Creating a New Service

1. Create class in `src/Services/` directory
2. Implement singleton pattern with `getInstance()`
3. Initialize in [Plugin::init()](hb-booking.php#L92-L113)
4. Inject into dependent classes via constructor

### Working with Dates

**Always** use the `DateConverter` service when working with dates:

```php
$dateConverter = DateConverter::getInstance();

// User input → Database
$gregorian = $dateConverter->prepareForDatabase($user_date);

// Database → Display
$display = $dateConverter->formatDate($db_date);

// Validation
$valid = $dateConverter->isValidDate($user_input);
```

## Important Constants

Defined in [hb-booking.php:25-28](hb-booking.php#L25-L28):
- `HB_BOOKING_VERSION` - Plugin version
- `HB_BOOKING_PLUGIN_DIR` - Absolute path to plugin directory
- `HB_BOOKING_PLUGIN_URL` - URL to plugin directory
- `HB_BOOKING_PLUGIN_FILE` - Main plugin file path

## WordPress Options

Settings stored in `wp_options` table:
- `hb_booking_admin_email` - Notification recipient
- `hb_booking_calendar_type` - 'gregorian' or 'jalali'
- `hb_booking_calendar_integration` - 'none', 'ical', or 'google'
- `hb_booking_google_client_id` - OAuth client ID
- `hb_booking_google_client_secret` - OAuth client secret
- `hb_booking_google_calendar_id` - Target calendar (default: 'primary')
- `hb_booking_google_refresh_token` - OAuth refresh token
- `hb_booking_enable_notifications` - Boolean for email notifications
- `hb_booking_db_version` - Database schema version

## Shortcodes

- `[hb_booking_form]` - Renders booking form (Frontend\BookingForm)
- `[hb_customer_calendar]` - Renders customer's bookings (Frontend\CustomerCalendar)

## Security Notes

- All user input sanitized via WordPress functions (`sanitize_text_field`, `sanitize_email`, etc.)
- Database queries use `$wpdb->prepare()` for SQL injection prevention
- Nonces used for admin actions
- REST API endpoints have permission callbacks
- OAuth tokens stored in WordPress options (encrypted at rest by WordPress)

## Complete Project Structure

### Directory Layout

```
hb-booking/
├── hb-booking.php          # Main plugin file (entry point)
├── composer.json           # Composer dependencies
├── composer.lock
├── CLAUDE.md              # Documentation for Claude Code
├── vendor/                # Composer dependencies
│   ├── morilog/jalali/    # Persian calendar library
│   ├── nesbot/carbon/     # Date/time library
│   └── ...
├── src/                   # PHP source code (PSR-4)
│   ├── Core/
│   │   ├── Database.php       # Database operations layer
│   │   ├── Installer.php      # Plugin installation/setup
│   │   └── Assets.php         # CSS/JS enqueue manager
│   ├── Admin/
│   │   └── BookingAdmin.php   # Admin dashboard & settings
│   ├── Frontend/
│   │   ├── BookingForm.php        # Public booking form
│   │   └── CustomerCalendar.php   # Customer bookings view
│   ├── Api/
│   │   └── BookingApi.php     # REST API endpoints
│   └── Services/
│       ├── EmailService.php       # Email notifications
│       ├── CalendarService.php    # Google/iCal integration
│       └── DateConverter.php      # Jalali ↔ Gregorian
└── assets/
    ├── js/
    │   ├── frontend.js            # Frontend booking logic
    │   ├── admin.js               # Admin dashboard logic
    │   ├── persian-datepicker.min.js
    │   ├── persian-date.min.js
    │   └── moment.min.js
    └── css/
        ├── frontend.css
        ├── admin.css
        └── persian-datepicker.min.css
```

### Plugin Initialization Flow

```
hb-booking.php
  └── Plugin::getInstance()
      └── Plugin::init() [triggered on 'plugins_loaded']
          ├── Database::getInstance()
          ├── Assets::getInstance()
          ├── BookingAdmin::getInstance() [if is_admin()]
          ├── BookingForm::getInstance()
          ├── CustomerCalendar::getInstance()
          ├── BookingApi::getInstance()
          ├── EmailService::getInstance()
          └── CalendarService::getInstance()
```

### Data Flow for Creating a Booking

```
Frontend Form (BookingForm.php)
  ↓ (JavaScript: frontend.js)
POST /wp-json/hb-booking/v1/bookings
  ↓
BookingApi::createItem()
  ├── Validate data
  ├── DateConverter::prepareForDatabase() [Jalali → Gregorian if needed]
  ├── Database::isTimeSlotAvailable()
  ├── Database::createBooking()
  ├── CalendarService::addToCalendar() [Google Calendar/iCal]
  ├── EmailService::sendBookingConfirmation()
  └── EmailService::sendAdminNotification()
```

### Calendar System Data Flow

```
User Input (any calendar type)
  ↓
DateConverter::prepareForDatabase()
  ↓
Database (stores in Gregorian YYYY-MM-DD)
  ↓
DateConverter::formatDate()
  ↓
Display (in selected calendar type)
```

## Component Responsibilities

| Class | Purpose | Singleton | Dependencies |
|-------|---------|-----------|--------------|
| **Plugin** | Main orchestrator | ✅ | All components |
| **Database** | WPDB wrapper for bookings table | ✅ | None |
| **Installer** | Database schema creation | ❌ | None |
| **Assets** | Enqueue CSS/JS files | ✅ | DateConverter |
| **BookingAdmin** | Admin dashboard UI | ✅ | Database, DateConverter |
| **BookingForm** | Frontend form shortcode | ✅ | DateConverter |
| **CustomerCalendar** | User bookings display | ✅ | Database, DateConverter |
| **BookingApi** | REST API endpoints | ✅ | Database, EmailService, CalendarService, DateConverter |
| **EmailService** | HTML email templates | ✅ | DateConverter |
| **CalendarService** | Google/iCal integration | ✅ | None |
| **DateConverter** | Jalali ↔ Gregorian conversion | ✅ | morilog/jalali |

## REST API Endpoints

| Method | Endpoint | Permission | Purpose |
|--------|----------|------------|---------|
| GET | `/hb-booking/v1/bookings` | Admin | List all bookings |
| POST | `/hb-booking/v1/bookings` | Public | Create booking |
| GET | `/hb-booking/v1/bookings/{id}` | Admin | Get single booking |
| PUT | `/hb-booking/v1/bookings/{id}` | Admin | Update booking |
| DELETE | `/hb-booking/v1/bookings/{id}` | Admin | Delete booking |
| GET | `/hb-booking/v1/check-availability` | Public | Check time slot |

## Date Handling Pattern

**Critical:** All dates are stored in Gregorian format in the database, regardless of display format.

```php
// Frontend → Database
$user_input = "1403-08-15"; // Jalali
$gregorian = $dateConverter->prepareForDatabase($user_input); // "2024-11-05"
$database->createBooking(['booking_date' => $gregorian]);

// Database → Display
$db_date = "2024-11-05"; // Always Gregorian in DB
$display = $dateConverter->formatDate($db_date); // "1403/08/15" or "2024-11-05"
```

## Frontend JavaScript Architecture

### frontend.js (ES6 Class-based)
- `BookingForm` class handles form submission
- Async/await for REST API calls
- Real-time availability checking via `/check-availability` endpoint
- Persian datepicker initialization for Jalali calendar
- Form validation and user feedback with message display

### admin.js (ES6 Class-based)
- `AdminBookingManager` - Edit/delete operations via REST API
- `CalendarManager` - FullCalendar integration for calendar view with Jalali/Gregorian support
  - Detects calendar type from `data-calendar-type` attribute
  - Converts FullCalendar title to Jalali month names when in Jalali mode
  - Uses Persian date library for accurate Gregorian to Jalali conversion
  - Displays Persian UI text (buttons, labels) in Jalali mode
  - RTL layout automatically enabled for Jalali calendar
- `SettingsManager` - Settings page UI interactions
- Conditional datepicker (jQuery UI for Gregorian vs Persian for Jalali)

## WordPress Hooks & Filters

### Available Filters
- `hb_booking_services` - Customize service options in booking form
- `hb_booking_start_hour` - Change booking start time (default: 9)
- `hb_booking_end_hour` - Change booking end time (default: 17)
- `hb_booking_interval` - Time slot interval in minutes (default: 30)

## External Dependencies

### PHP (via Composer)
- `morilog/jalali` ^3.4 - Persian calendar operations
- `nesbot/carbon` - Date/time manipulation (dependency of jalali)

### JavaScript
- **FullCalendar** 6.1.10 (CDN) - Admin calendar view
- **Persian Datepicker** 1.2.0 (local) - Jalali date selection
- **Persian Date** 1.1.0 (local) - Jalali date library
- **Moment.js** 2.29.4 (local) - Date parsing
- **jQuery UI Datepicker** (WordPress core) - Gregorian dates

## Database Schema

**Table:** `wp_hb_bookings`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) UNSIGNED | Primary key, auto-increment |
| customer_name | varchar(255) | Customer full name |
| customer_email | varchar(255) | Email address (indexed) |
| customer_phone | varchar(50) | Phone number |
| booking_date | date | Date in Gregorian format (indexed) |
| booking_time | time | Time in 24-hour format (HH:MM:SS) |
| service | varchar(255) | Service type (nullable) |
| notes | text | Customer notes (nullable) |
| status | varchar(20) | pending/confirmed/cancelled/completed (indexed) |
| google_event_id | varchar(255) | Google Calendar event ID (nullable) |
| created_at | datetime | Auto-generated timestamp |
| updated_at | datetime | Auto-update timestamp |

**Indexes:**
- PRIMARY KEY on `id`
- KEY on `customer_email` for filtering user bookings
- KEY on `booking_date` for date range queries
- KEY on `status` for status filtering
