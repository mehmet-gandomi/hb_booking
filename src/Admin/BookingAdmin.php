<?php
/**
 * Admin dashboard
 * Handles admin interface for managing bookings
 */

namespace HB\Booking\Admin;

use HB\Booking\Core\Database;

class BookingAdmin
{
    private static ?BookingAdmin $instance = null;
    private Database $database;

    private function __construct()
    {
        $this->database = Database::getInstance();

        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
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
    }

    /**
     * Register plugin settings
     */
    public function registerSettings(): void
    {
        register_setting('hb_booking_settings', 'hb_booking_admin_email');
        register_setting('hb_booking_settings', 'hb_booking_time_format');
        register_setting('hb_booking_settings', 'hb_booking_date_format');
        register_setting('hb_booking_settings', 'hb_booking_enable_notifications');
        register_setting('hb_booking_settings', 'hb_booking_calendar_integration');
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
                        <th><?php esc_html_e('Service', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Status', 'hb-booking'); ?></th>
                        <th><?php esc_html_e('Actions', 'hb-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">
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
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date)));
                                    echo '<br>';
                                    echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->booking_time)));
                                    ?>
                                </td>
                                <td><?php echo esc_html($booking->service ?: '-'); ?></td>
                                <td>
                                    <span class="hb-status-badge hb-status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo esc_html(ucfirst($booking->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small hb-edit-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                                        <?php esc_html_e('Edit', 'hb-booking'); ?>
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

        // Prepare events for calendar
        $events = [];
        foreach ($bookings as $booking) {
            $events[] = [
                'id' => $booking->id,
                'title' => $booking->customer_name . ($booking->service ? " - {$booking->service}" : ''),
                'start' => $booking->booking_date . 'T' . $booking->booking_time,
                'backgroundColor' => $this->getStatusColor($booking->status),
                'borderColor' => $this->getStatusColor($booking->status),
                'extendedProps' => [
                    'email' => $booking->customer_email,
                    'phone' => $booking->customer_phone,
                    'status' => $booking->status,
                ]
            ];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Calendar', 'hb-booking'); ?></h1>
            <div id="hb-booking-calendar" data-events='<?php echo esc_attr(json_encode($events)); ?>'></div>
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
}
