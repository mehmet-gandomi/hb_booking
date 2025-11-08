# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **⚠️ IMPORTANT**: Before adding any new code, features, or making changes to this project, **READ THIS FILE FIRST** to understand where code should be placed and what patterns to follow.

## Project Overview

HB Booking is a WordPress plugin for appointment booking with calendar integration. It follows modern PHP practices (PHP 8.0+) with PSR-4 autoloading, SOLID principles, and WordPress coding standards.

## Setup & Installation

```bash
# Install Composer dependencies (generates PSR-4 autoloader)
composer install

# Optional: Static analysis
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse src
```

After installation, activate the plugin in WordPress admin and configure at **Bookings > Settings**.

## File Organization & Where to Add Code

### JavaScript Files
- **`assets/js/frontend.js`** - Frontend booking form logic, customer-facing interactions
- **`assets/js/admin.js`** - Admin panel JavaScript (BookingManager, CalendarManager, SettingsManager classes)
  - Use class-based architecture with `constructor()` and methods
  - NEVER add inline `<script>` tags in PHP files - always use this file
  - Example: Settings toggles, admin form handlers, calendar initialization

### CSS Files
- **`assets/css/frontend.css`** - Styles for booking form and customer calendar
- **`assets/css/admin.css`** - Admin panel styles (table layouts, status badges, etc.)

### PHP Structure
- **`src/Core/`** - Database access, installer, asset loading
  - Add new core functionality here (e.g., cache layer, logging)
- **`src/Admin/`** - Admin interface and settings
  - `BookingAdmin.php` - Add new admin pages, settings fields, menu items
  - OAuth flows and admin-only actions go here
- **`src/Frontend/`** - Public-facing shortcodes
  - Add new shortcodes or customer-facing features
- **`src/Api/`** - REST API endpoints
  - Extend `BookingApi.php` for new CRUD operations
- **`src/Services/`** - Business logic services
  - `EmailService.php` - Email templates and sending logic
  - `CalendarService.php` - Calendar integration (Google, iCal)
  - Add new services here (e.g., PaymentService, NotificationService)

### Key Rules
1. **JavaScript**: Always add to `assets/js/admin.js` or `assets/js/frontend.js`, NEVER inline in PHP
2. **CSS**: Always add to respective CSS files, avoid inline styles except for dynamic values
3. **Settings**: Register in `BookingAdmin::registerSettings()`, render in `BookingAdmin::renderSettingsPage()`
4. **Database**: All queries go through `Database` class methods, never direct SQL
5. **Assets**: Enqueue via `Assets::enqueue()` method, localize data with `wp_localize_script()`

## Architecture

### Plugin Initialization Flow

1. **hb-booking.php** - Entry point that defines constants and initializes the singleton `Plugin` class
2. **Plugin::init()** - Orchestrates initialization of all components in this order:
   - Core (Database, Assets)
   - Admin (only on `is_admin()`)
   - Frontend (BookingForm, CustomerCalendar)
   - REST API (BookingApi)
   - Services (EmailService, CalendarService)

All major classes use the **Singleton pattern** with `getInstance()` methods.

### Layered Architecture

**Core Layer** (`src/Core/`)
- `Installer.php` - Database table creation on plugin activation
- `Database.php` - Repository pattern for all booking CRUD operations
- `Assets.php` - Enqueues CSS/JS with localized data for REST API

**Frontend Layer** (`src/Frontend/`)
- `BookingForm.php` - Shortcode `[hb_booking_form]` renders customer-facing form
- `CustomerCalendar.php` - Shortcode `[hb_customer_calendar]` shows logged-in user bookings

**Admin Layer** (`src/Admin/`)
- `BookingAdmin.php` - Admin menu pages (All Bookings, Calendar View, Settings)

**API Layer** (`src/Api/`)
- `BookingApi.php` - REST endpoints at `/wp-json/hb-booking/v1/bookings`
  - Extends `WP_REST_Controller`
  - Handles CRUD operations via REST API
  - Permission checks require `manage_options` capability for admin routes

**Service Layer** (`src/Services/`)
- `EmailService.php` - HTML email templates for confirmations, admin notifications, status updates
- `CalendarService.php` - Calendar integration (iCal generation + Google Calendar hooks)

### Data Flow for Booking Creation

1. Customer submits form via `assets/js/frontend.js` (Fetch API)
2. POST to `/wp-json/hb-booking/v1/bookings`
3. `BookingApi::createItem()` validates and sanitizes data
4. `Database::createBooking()` inserts to `wp_hb_bookings` table
5. `CalendarService::addToCalendar()` generates iCal or calls Google Calendar hooks
6. `EmailService` sends confirmation to customer and notification to admin
7. Returns booking object with ID to frontend

### Autoloading

PSR-4 autoloader in `vendor/autoload.php` maps:
- `HB\Booking\` namespace → `src/` directory
- Example: `HB\Booking\Core\Database` → `src/Core/Database.php`

## Database

Single table: `{$wpdb->prefix}hb_bookings`

Key fields:
- `status` - enum: pending, confirmed, cancelled, completed
- `google_event_id` - stores calendar event ID for updates/deletions
- Indexed on: `customer_email`, `booking_date`, `status`

Access via `Database::getInstance()` methods (never direct SQL):
- `createBooking($data)` - returns insert ID
- `getBooking($id)` - returns single object
- `getBookings($filters)` - returns array with optional filtering
- `updateBooking($id, $data)` - returns bool
- `isTimeSlotAvailable($date, $time)` - prevents double-booking

## REST API

Base: `/wp-json/hb-booking/v1`

Public endpoints:
- `POST /bookings` - Create booking
- `GET /check-availability?date=...&time=...` - Check time slot

Admin-only (requires nonce + `manage_options`):
- `GET /bookings` - List all
- `GET /bookings/{id}` - Single booking
- `PUT /bookings/{id}` - Update
- `DELETE /bookings/{id}` - Delete

Frontend JavaScript expects localized object `hbBooking` with `restUrl` and `nonce`.

## Customization Hooks

**Service Types:**
```php
add_filter('hb_booking_services', function($services) {
    return ['service_key' => 'Service Name'];
});
```

**Time Slots:**
```php
add_filter('hb_booking_start_hour', fn() => 9);   // Default: 9
add_filter('hb_booking_end_hour', fn() => 17);    // Default: 17
add_filter('hb_booking_interval', fn() => 30);    // Minutes, default: 30
```


## Security

All user input goes through:
1. `sanitize_text_field()`, `sanitize_email()`, `sanitize_textarea_field()`
2. WordPress prepared statements in `Database` class
3. `wp_nonce_field()` / `wp_create_nonce()` for form submissions
4. Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`

REST API uses WordPress nonce authentication via `X-WP-Nonce` header.

## Shortcodes

`[hb_booking_form title="Custom Title" show_service="true"]`
- Renders booking form
- Attributes optional

`[hb_customer_calendar]`
- Shows bookings for current logged-in user
- Requires authentication

## Email Templates

Located in `EmailService` private methods:
- `getConfirmationEmailTemplate()` - Sent to customer
- `getAdminNotificationTemplate()` - Sent to admin
- `getStatusUpdateTemplate()` - Sent on status change

All use HTML format via `wp_mail()` with inline styles.

## Calendar Integration

**iCal:** Generated automatically, saved to `wp-content/uploads/hb-booking-icals/booking-{id}.ics`. Enabled by selecting "iCal" in Settings.

**Google Calendar:** Full OAuth2 integration built-in. Configure via **Bookings > Settings**:
- Requires Google Cloud Console OAuth2 credentials (Client ID, Client Secret)
- Supports "primary" or specific calendar IDs
- Stores refresh token in WordPress options
- Automatically creates, updates, and deletes calendar events
- Uses `CalendarService::getAccessToken()` to refresh access tokens via OAuth2
- Events include customer details, attendees, and reminders

## WordPress Requirements

- WordPress 6.0+
- PHP 8.0+ (uses typed properties, union types, null coalescing)
- Composer for autoloading

## Code Style

- WordPress Coding Standards for PHP/JS/CSS
- Type hints on all methods
- Return types declared
- Strict comparison operators
- DocBlocks on all public methods
