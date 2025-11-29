<?php
/**
 * Admin dashboard
 * Handles admin interface for managing bookings
 */

namespace HB\Booking\Admin;

use HB\Booking\Core\Database;
use HB\Booking\Services\DateConverter;

class BookingAdmin
{
    private static ?BookingAdmin $instance = null;
    private Database $database;
    private DateConverter $dateConverter;

    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->dateConverter = DateConverter::getInstance();

        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_init', [$this, 'handleGoogleAuth']);
    }

    public static function getInstance(): BookingAdmin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add admin menu pages
     */
    public function addAdminMenu(): void
    {
        add_menu_page(
            __('HB Booking', 'hb-booking'),
            __('Bookings', 'hb-booking'),
            'manage_options',
            'hb-booking',
            [$this, 'renderBookingsPage'],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'hb-booking',
            __('All Bookings', 'hb-booking'),
            __('All Bookings', 'hb-booking'),
            'manage_options',
            'hb-booking',
            [$this, 'renderBookingsPage']
        );

        add_submenu_page(
            'hb-booking',
            __('Calendar View', 'hb-booking'),
            __('Calendar View', 'hb-booking'),
            'manage_options',
            'hb-booking-calendar',
            [$this, 'renderCalendarPage']
        );

        add_submenu_page(
            'hb-booking',
            __('Settings', 'hb-booking'),
            __('Settings', 'hb-booking'),
            'manage_options',
            'hb-booking-settings',
            [$this, 'renderSettingsPage']
        );

        add_submenu_page(
            'hb-booking',
            __('Debug Logs', 'hb-booking'),
            __('Debug Logs', 'hb-booking'),
            'manage_options',
            'hb-booking-debug',
            [$this, 'renderDebugPage']
        );

        add_submenu_page(
            'hb-booking',
            __('Test Reminders', 'hb-booking'),
            __('Test Reminders', 'hb-booking'),
            'manage_options',
            'hb-booking-test-reminders',
            [$this, 'renderTestRemindersPage']
        );
    }

    /**
     * Register plugin settings
     */
    public function registerSettings(): void
    {
        register_setting('hb_booking_settings', 'hb_booking_admin_email');
        register_setting('hb_booking_settings', 'hb_booking_calendar_type');
        register_setting('hb_booking_settings', 'hb_booking_time_format');
        register_setting('hb_booking_settings', 'hb_booking_date_format');
        register_setting('hb_booking_settings', 'hb_booking_enable_notifications');
        register_setting('hb_booking_settings', 'hb_booking_calendar_integration');
        register_setting('hb_booking_settings', 'hb_booking_google_client_id');
        register_setting('hb_booking_settings', 'hb_booking_google_client_secret');
        register_setting('hb_booking_settings', 'hb_booking_google_calendar_id');
        register_setting('hb_booking_settings', 'hb_booking_google_refresh_token');
    }

    /**
     * Render bookings list page
     */
    public function renderBookingsPage(): void
    {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $bookings = $this->database->getBookings($status_filter ? ['status' => $status_filter] : []);

        $statuses = [
            'all' => __('All', 'hb-booking'),
            'pending' => __('Pending', 'hb-booking'),
            'confirmed' => __('Confirmed', 'hb-booking'),
            'cancelled' => __('Cancelled', 'hb-booking'),
            'completed' => __('Completed', 'hb-booking'),
        ];

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Bookings', 'hb-booking'); ?></h1>
            <hr class="wp-header-end">

            <ul class="subsubsub">
                <?php
                $first = true;
                foreach ($statuses as $status => $label) {
                    $url = add_query_arg(['page' => 'hb-booking', 'status' => $status === 'all' ? '' : $status], admin_url('admin.php'));
                    $active = ($status === 'all' && empty($status_filter)) || $status === $status_filter;
                    ?>
                    <?php if (!$first): ?>|<?php endif; ?>
                    <li class="<?php echo esc_attr($status); ?>">
                        <a href="<?php echo esc_url($url); ?>" <?php echo $active ? 'class="current"' : ''; ?>>
                            <?php echo esc_html($label); ?>
                        </a>
                    </li>
                    <?php
                    $first = false;
                }
                ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Customer', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Contact', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Date & Time', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Business Status', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Target Country', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Status', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Actions', 'hb-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">
                                <?php esc_html_e('No bookings found.', 'hb-booking'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo esc_html($booking->id); ?></td>
                                <td><strong><?php echo esc_html($booking->customer_name); ?></strong></td>
                                <td>
                                    <?php echo esc_html($booking->customer_email); ?><br>
                                    <?php echo esc_html($booking->customer_phone); ?>
                                </td>
                                <td>
                                    <?php
                                    echo esc_html($this->dateConverter->formatDate($booking->booking_date));
                                    echo '<br>';
                                    echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->booking_time)));
                                    ?>
                                </td>
                                <td><?php echo esc_html($booking->business_status ?: '-'); ?></td>
                                <td><?php echo esc_html($booking->target_country ?: '-'); ?></td>
                                <td>
                                    <span class="hb-status-badge hb-status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo esc_html(ucfirst($booking->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small hb-view-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                                        <?php esc_html_e('View', 'hb-booking'); ?>
                                    </button>
                                    <button type="button" class="button button-small hb-delete-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                                        <?php esc_html_e('Delete', 'hb-booking'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render calendar view page
     */
    public function renderCalendarPage(): void
    {
        $bookings = $this->database->getBookings();
        $is_jalali = $this->dateConverter->isJalali();

        // Prepare events for calendar
        $events = [];
        foreach ($bookings as $booking) {
            // For Jalali calendar, we need both Gregorian (for FullCalendar) and Jalali (for display)
            if ($is_jalali) {
                $jalali_date = $this->dateConverter->toJalali($booking->booking_date, 'Y/m/d');
                $display_title = $booking->customer_name .
                    ($booking->target_country ? " - {$booking->target_country}" : '') .
                    " ({$jalali_date})";
            } else {
                $display_title = $booking->customer_name . ($booking->target_country ? " - {$booking->target_country}" : '');
            }

            $events[] = [
                'id' => $booking->id,
                'title' => $display_title,
                'start' => $booking->booking_date . 'T' . $booking->booking_time,
                'backgroundColor' => $this->getStatusColor($booking->status),
                'borderColor' => $this->getStatusColor($booking->status),
                'extendedProps' => [
                    'email' => $booking->customer_email,
                    'phone' => $booking->customer_phone,
                    'status' => $booking->status,
                    'business_status' => $booking->business_status,
                    'target_country' => $booking->target_country,
                    'team_size' => $booking->team_size,
                    'services' => $booking->services,
                    'notes' => $booking->notes,
                    'gregorian_date' => $booking->booking_date,
                    'jalali_date' => $is_jalali ? $jalali_date : null,
                ]
            ];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Calendar', 'hb-booking'); ?></h1>
            <div id="hb-booking-calendar"
                 data-events='<?php echo esc_attr(json_encode($events)); ?>'
                 data-calendar-type='<?php echo esc_attr($is_jalali ? 'jalali' : 'gregorian'); ?>'></div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Settings', 'hb-booking'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('hb_booking_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hb_booking_admin_email">
                                <?php esc_html_e('Admin Email', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="hb_booking_admin_email"
                                name="hb_booking_admin_email"
                                value="<?php echo esc_attr(get_option('hb_booking_admin_email', get_option('admin_email'))); ?>"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('Email address to receive booking notifications', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="hb_booking_calendar_type">
                                <?php esc_html_e('Calendar Type', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="hb_booking_calendar_type" name="hb_booking_calendar_type">
                                <option value="gregorian" <?php selected(get_option('hb_booking_calendar_type', 'gregorian'), 'gregorian'); ?>>
                                    <?php esc_html_e('Gregorian', 'hb-booking'); ?>
                                </option>
                                <option value="jalali" <?php selected(get_option('hb_booking_calendar_type'), 'jalali'); ?>>
                                    <?php esc_html_e('Jalali (Persian)', 'hb-booking'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose calendar system for date display and input', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="hb_booking_enable_notifications">
                                <?php esc_html_e('Email Notifications', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    id="hb_booking_enable_notifications"
                                    name="hb_booking_enable_notifications"
                                    value="1"
                                    <?php checked(get_option('hb_booking_enable_notifications', true), true); ?>
                                />
                                <?php esc_html_e('Enable email notifications for new bookings', 'hb-booking'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="hb_booking_calendar_integration">
                                <?php esc_html_e('Calendar Integration', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="hb_booking_calendar_integration" name="hb_booking_calendar_integration">
                                <option value="none" <?php selected(get_option('hb_booking_calendar_integration'), 'none'); ?>>
                                    <?php esc_html_e('None', 'hb-booking'); ?>
                                </option>
                                <option value="ical" <?php selected(get_option('hb_booking_calendar_integration'), 'ical'); ?>>
                                    <?php esc_html_e('iCal', 'hb-booking'); ?>
                                </option>
                                <option value="google" <?php selected(get_option('hb_booking_calendar_integration'), 'google'); ?>>
                                    <?php esc_html_e('Google Calendar', 'hb-booking'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose calendar integration method', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title" id="google-calendar-settings" style="<?php echo get_option('hb_booking_calendar_integration') === 'google' ? '' : 'display:none;'; ?>">
                    <?php esc_html_e('Google Calendar Settings', 'hb-booking'); ?>
                </h2>
                <p class="description" style="<?php echo get_option('hb_booking_calendar_integration') === 'google' ? '' : 'display:none;'; ?>">
                    <?php echo wp_kses_post(
                        sprintf(
                            __('To set up Google Calendar integration: <br>1. Go to <a href="%s" target="_blank">Google Cloud Console</a><br>2. Create a new project or select an existing one<br>3. Enable the Google Calendar API<br>4. Create OAuth 2.0 credentials (Web application)<br>5. Add your WordPress site URL to authorized redirect URIs<br>6. Copy the Client ID and Client Secret below', 'hb-booking'),
                            'https://console.cloud.google.com/apis/credentials'
                        )
                    ); ?>
                </p>
                <table class="form-table google-calendar-settings" style="<?php echo get_option('hb_booking_calendar_integration') === 'google' ? '' : 'display:none;'; ?>">
                    <tr>
                        <th scope="row">
                            <label for="hb_booking_google_client_id">
                                <?php esc_html_e('Google Client ID', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="hb_booking_google_client_id"
                                name="hb_booking_google_client_id"
                                value="<?php echo esc_attr(get_option('hb_booking_google_client_id', '')); ?>"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('OAuth 2.0 Client ID from Google Cloud Console', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="hb_booking_google_client_secret">
                                <?php esc_html_e('Google Client Secret', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="hb_booking_google_client_secret"
                                name="hb_booking_google_client_secret"
                                value="<?php echo esc_attr(get_option('hb_booking_google_client_secret', '')); ?>"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('OAuth 2.0 Client Secret from Google Cloud Console', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="hb_booking_google_calendar_id">
                                <?php esc_html_e('Google Calendar ID', 'hb-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="hb_booking_google_calendar_id"
                                name="hb_booking_google_calendar_id"
                                value="<?php echo esc_attr(get_option('hb_booking_google_calendar_id', 'primary')); ?>"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('Calendar ID (use "primary" for your main calendar, or find it in Google Calendar settings)', 'hb-booking'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Authorization Status', 'hb-booking'); ?>
                        </th>
                        <td>
                            <?php if (get_option('hb_booking_google_refresh_token')): ?>
                                <span style="color: green;">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('Connected', 'hb-booking'); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e('Your Google Calendar is connected and ready to use.', 'hb-booking'); ?>
                                </p>
                            <?php else: ?>
                                <span style="color: orange;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('Not connected', 'hb-booking'); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e('Please save your Client ID and Client Secret, then click "Authorize with Google" below.', 'hb-booking'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if (get_option('hb_booking_google_client_id') && get_option('hb_booking_google_client_secret')): ?>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <?php if (!get_option('hb_booking_google_refresh_token')): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking-settings&action=google-auth')); ?>" class="button button-primary">
                                    <?php esc_html_e('Authorize with Google', 'hb-booking'); ?>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking-settings&action=google-disconnect')); ?>" class="button button-secondary">
                                    <?php esc_html_e('Disconnect Google Calendar', 'hb-booking'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Shortcodes', 'hb-booking'); ?>
                        </th>
                        <td>
                            <p><code>[hb_booking_form]</code> - <?php esc_html_e('Display booking form', 'hb-booking'); ?></p>
                            <p><code>[hb_customer_calendar]</code> - <?php esc_html_e('Display customer bookings', 'hb-booking'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get status color for calendar
     */
    private function getStatusColor(string $status): string
    {
        $colors = [
            'pending' => '#f39c12',
            'confirmed' => '#27ae60',
            'cancelled' => '#e74c3c',
            'completed' => '#95a5a6',
        ];

        return $colors[$status] ?? '#3498db';
    }

    /**
     * Render debug logs page
     */
    public function renderDebugPage(): void
    {
        // Get WordPress debug log
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $logs = [];

        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $lines = explode("\n", $log_content);

            // Get only HB Booking related logs (last 100)
            $logs = array_filter($lines, function($line) {
                return strpos($line, 'HB Booking:') !== false;
            });
            $logs = array_slice(array_reverse($logs), 0, 100);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Debug Logs', 'hb-booking'); ?></h1>

            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Google Calendar Integration Status:', 'hb-booking'); ?></strong><br>
                    <?php esc_html_e('Calendar Integration:', 'hb-booking'); ?>
                    <strong><?php echo esc_html(get_option('hb_booking_calendar_integration', 'none')); ?></strong><br>

                    <?php esc_html_e('Google Client ID:', 'hb-booking'); ?>
                    <?php echo get_option('hb_booking_google_client_id') ? '<span style="color:green;">✓ Set</span>' : '<span style="color:red;">✗ Not set</span>'; ?><br>

                    <?php esc_html_e('Google Client Secret:', 'hb-booking'); ?>
                    <?php echo get_option('hb_booking_google_client_secret') ? '<span style="color:green;">✓ Set</span>' : '<span style="color:red;">✗ Not set</span>'; ?><br>

                    <?php esc_html_e('Google Refresh Token:', 'hb-booking'); ?>
                    <?php echo get_option('hb_booking_google_refresh_token') ? '<span style="color:green;">✓ Connected</span>' : '<span style="color:red;">✗ Not connected</span>'; ?><br>

                    <?php esc_html_e('Calendar ID:', 'hb-booking'); ?>
                    <strong><?php echo esc_html(get_option('hb_booking_google_calendar_id', 'primary')); ?></strong>
                </p>
                <p>
                    <em><?php esc_html_e('This page displays WordPress debug logs. Enable WP_DEBUG_LOG in wp-config.php to see detailed logging for troubleshooting.', 'hb-booking'); ?></em>
                </p>
            </div>

            <h2><?php esc_html_e('Recent Logs (Most Recent First)', 'hb-booking'); ?></h2>

            <?php if (empty($logs)): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No logs found. Make sure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php', 'hb-booking'); ?></p>
                    <p>
                        <code>define('WP_DEBUG', true);</code><br>
                        <code>define('WP_DEBUG_LOG', true);</code><br>
                        <code>define('WP_DEBUG_DISPLAY', false);</code>
                    </p>
                </div>
            <?php else: ?>
                <div style="background: #f5f5f5; padding: 15px; border: 1px solid #ccc; max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                    <?php foreach ($logs as $log): ?>
                        <div style="padding: 5px 0; border-bottom: 1px solid #ddd;">
                            <?php echo esc_html($log); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking-debug')); ?>" class="button button-primary">
                        <?php esc_html_e('Refresh Logs', 'hb-booking'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Test Reminders page
     */
    public function renderTestRemindersPage(): void
    {
        // Handle manual trigger
        $triggered = false;
        if (isset($_POST['trigger_reminders']) && check_admin_referer('hb_trigger_reminders')) {
            $service = \HB\Booking\Services\ReminderService::getInstance();
            $service->sendReminders();
            $triggered = true;
        }

        // Get data for display
        $db = $this->database;
        $bookings_24h = $db->getBookingsNeeding24HourReminders();
        $bookings_30min = $db->getBookingsNeeding30MinReminders();
        $next_run = wp_next_scheduled('hb_booking_send_reminders');

        // Time calculations (must match Database.php logic)
        $now = current_time('timestamp');
        $reminder_24h_start = $now + (23 * HOUR_IN_SECONDS);
        $reminder_24h_end = $now + (25 * HOUR_IN_SECONDS);
        $reminder_30min_start = $now + (25 * MINUTE_IN_SECONDS);
        $reminder_30min_end = $now + (35 * MINUTE_IN_SECONDS);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Test Reminder System', 'hb-booking'); ?></h1>

            <?php if ($triggered): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php esc_html_e('Reminder check triggered successfully!', 'hb-booking'); ?></strong></p>
                    <p><?php esc_html_e('Check the Debug Logs page for details about sent emails.', 'hb-booking'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Cron Status -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('1. Cron Job Status', 'hb-booking'); ?></h2>
                <?php if ($next_run): ?>
                    <?php
                    // Compare in UTC: wp_next_scheduled returns UTC timestamp, time() also returns UTC
                    $current_utc_timestamp = time();
                    $is_overdue = $next_run < $current_utc_timestamp;

                    // Convert to WordPress timezone for display
                    $next_run_local = $next_run + (get_option('gmt_offset') * HOUR_IN_SECONDS);
                    ?>
                    <p>✓ <strong style="color: green;"><?php esc_html_e('Cron job is scheduled', 'hb-booking'); ?></strong></p>
                    <p><?php esc_html_e('Next run:', 'hb-booking'); ?> <code><?php echo esc_html(date('Y-m-d H:i:s', $next_run_local)); ?></code></p>
                    <p><?php esc_html_e('Current time:', 'hb-booking'); ?> <code><?php echo esc_html(current_time('mysql')); ?></code></p>
                    <?php if ($is_overdue): ?>
                        <p style="color: orange;">⚠ <strong><?php esc_html_e('Cron is overdue - WordPress cron only runs when someone visits the site.', 'hb-booking'); ?></strong></p>
                        <p><?php esc_html_e('This page visit should trigger it. Refresh in a few seconds to see if it ran.', 'hb-booking'); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>✗ <strong style="color: red;"><?php esc_html_e('Cron job is NOT scheduled', 'hb-booking'); ?></strong></p>
                    <p><?php esc_html_e('Try deactivating and reactivating the plugin.', 'hb-booking'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Time Windows -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('2. Reminder Time Windows', 'hb-booking'); ?></h2>
                <p><?php esc_html_e('Current time:', 'hb-booking'); ?> <code><?php echo esc_html(current_time('mysql')); ?></code></p>
                <p>
                    <strong><?php esc_html_e('24-hour reminder window (23-25 hours from now):', 'hb-booking'); ?></strong><br>
                    <code><?php echo esc_html(gmdate('Y-m-d H:i:s', $reminder_24h_start)); ?></code>
                    <?php esc_html_e('to', 'hb-booking'); ?>
                    <code><?php echo esc_html(gmdate('Y-m-d H:i:s', $reminder_24h_end)); ?></code>
                    <span style="color: #666;">(2-hour window)</span>
                </p>
                <p>
                    <strong><?php esc_html_e('30-minute reminder window (25-35 minutes from now):', 'hb-booking'); ?></strong><br>
                    <code><?php echo esc_html(gmdate('Y-m-d H:i:s', $reminder_30min_start)); ?></code>
                    <?php esc_html_e('to', 'hb-booking'); ?>
                    <code><?php echo esc_html(gmdate('Y-m-d H:i:s', $reminder_30min_end)); ?></code>
                    <span style="color: #666;">(10-minute window)</span>
                </p>
            </div>

            <!-- 24-Hour Reminders -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('3. Bookings Needing 24-Hour Reminders', 'hb-booking'); ?></h2>
                <p><?php esc_html_e('Found:', 'hb-booking'); ?> <strong><?php echo count($bookings_24h); ?></strong> <?php esc_html_e('bookings', 'hb-booking'); ?></p>

                <?php if (!empty($bookings_24h)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Customer Name', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Email', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Date', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Time', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Reminder Sent', 'hb-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings_24h as $booking): ?>
                                <tr>
                                    <td><?php echo esc_html($booking->id); ?></td>
                                    <td><?php echo esc_html($booking->customer_name); ?></td>
                                    <td><?php echo esc_html($booking->customer_email); ?></td>
                                    <td><?php echo esc_html($this->dateConverter->formatDate($booking->booking_date)); ?></td>
                                    <td><?php echo esc_html($booking->booking_time); ?></td>
                                    <td><?php echo esc_html($booking->status); ?></td>
                                    <td><?php echo $booking->reminder_sent_24h ? '✓' : '✗'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><em><?php esc_html_e('No bookings found in the 24-hour reminder window.', 'hb-booking'); ?></em></p>
                <?php endif; ?>
            </div>

            <!-- 30-Minute Reminders -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('4. Bookings Needing 30-Minute Reminders', 'hb-booking'); ?></h2>
                <p><?php esc_html_e('Found:', 'hb-booking'); ?> <strong><?php echo count($bookings_30min); ?></strong> <?php esc_html_e('bookings', 'hb-booking'); ?></p>

                <?php if (!empty($bookings_30min)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Customer Name', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Email', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Date', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Time', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'hb-booking'); ?></th>
                                <th><?php esc_html_e('Reminder Sent', 'hb-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings_30min as $booking): ?>
                                <tr>
                                    <td><?php echo esc_html($booking->id); ?></td>
                                    <td><?php echo esc_html($booking->customer_name); ?></td>
                                    <td><?php echo esc_html($booking->customer_email); ?></td>
                                    <td><?php echo esc_html($this->dateConverter->formatDate($booking->booking_date)); ?></td>
                                    <td><?php echo esc_html($booking->booking_time); ?></td>
                                    <td><?php echo esc_html($booking->status); ?></td>
                                    <td><?php echo $booking->reminder_sent_30min ? '✓' : '✗'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><em><?php esc_html_e('No bookings found in the 30-minute reminder window.', 'hb-booking'); ?></em></p>
                <?php endif; ?>
            </div>

            <!-- Email Settings -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('5. Email Settings', 'hb-booking'); ?></h2>
                <?php $notifications_enabled = get_option('hb_booking_enable_notifications', true); ?>
                <p>
                    <?php esc_html_e('Notifications enabled:', 'hb-booking'); ?>
                    <?php if ($notifications_enabled): ?>
                        <strong style="color: green;">✓ <?php esc_html_e('Yes', 'hb-booking'); ?></strong>
                    <?php else: ?>
                        <strong style="color: red;">✗ <?php esc_html_e('No', 'hb-booking'); ?></strong>
                        <br><em><?php esc_html_e('Enable notifications in Settings to send reminder emails.', 'hb-booking'); ?></em>
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Admin email:', 'hb-booking'); ?> <code><?php echo esc_html(get_option('admin_email')); ?></code></p>
            </div>

            <!-- Manual Trigger -->
            <div class="card" style="max-width: 100%;">
                <h2><?php esc_html_e('6. Manual Test', 'hb-booking'); ?></h2>
                <p><?php esc_html_e('Click the button below to manually trigger the reminder check. This will send emails to any bookings that fall within the current reminder windows.', 'hb-booking'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('hb_trigger_reminders'); ?>
                    <button type="submit" name="trigger_reminders" class="button button-primary button-large">
                        <?php esc_html_e('Trigger Reminder Check Now', 'hb-booking'); ?>
                    </button>
                </form>
                <p><em><?php esc_html_e('Note: Check the Debug Logs page after triggering to see the results.', 'hb-booking'); ?></em></p>
            </div>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking-debug')); ?>" class="button">
                    <?php esc_html_e('View Debug Logs', 'hb-booking'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking-settings')); ?>" class="button">
                    <?php esc_html_e('Go to Settings', 'hb-booking'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle Google Calendar OAuth
     */
    public function handleGoogleAuth(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'hb-booking-settings') {
            return;
        }

        // Handle disconnect
        if (isset($_GET['action']) && $_GET['action'] === 'google-disconnect') {
            delete_option('hb_booking_google_refresh_token');
            wp_redirect(admin_url('admin.php?page=hb-booking-settings&message=google-disconnected'));
            exit;
        }

        // Handle OAuth callback
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $this->exchangeCodeForToken($code);
            wp_redirect(admin_url('admin.php?page=hb-booking-settings&message=google-connected'));
            exit;
        }

        // Handle OAuth initiation
        if (isset($_GET['action']) && $_GET['action'] === 'google-auth') {
            $this->initiateGoogleAuth();
        }
    }

    /**
     * Initiate Google OAuth flow
     */
    private function initiateGoogleAuth(): void
    {
        $client_id = get_option('hb_booking_google_client_id');
        $redirect_uri = admin_url('admin.php?page=hb-booking-settings');

        if (!$client_id) {
            wp_die(__('Please configure your Google Client ID first.', 'hb-booking'));
        }

        $auth_url = add_query_arg([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], 'https://accounts.google.com/o/oauth2/v2/auth');

        wp_redirect($auth_url);
        exit;
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchangeCodeForToken(string $code): void
    {
        $client_id = get_option('hb_booking_google_client_id');
        $client_secret = get_option('hb_booking_google_client_secret');
        $redirect_uri = admin_url('admin.php?page=hb-booking-settings');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['refresh_token'])) {
            update_option('hb_booking_google_refresh_token', $body['refresh_token']);
        }
    }
}
