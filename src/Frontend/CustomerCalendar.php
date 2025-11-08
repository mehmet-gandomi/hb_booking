<?php
/**
 * Customer calendar view
 * Displays customer's bookings in calendar format
 */

namespace HB\Booking\Frontend;

use HB\Booking\Core\Database;

class CustomerCalendar
{
    private static ?CustomerCalendar $instance = null;
    private Database $database;

    private function __construct()
    {
        $this->database = Database::getInstance();
        add_shortcode('hb_customer_calendar', [$this, 'renderCalendar']);
    }

    public static function getInstance(): CustomerCalendar
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Render customer calendar shortcode
     */
    public function renderCalendar(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your bookings.', 'hb-booking') . '</p>';
        }

        $current_user = wp_get_current_user();
        $bookings = $this->database->getBookings([
            'customer_email' => $current_user->user_email
        ]);

        ob_start();
        ?>
        <div class="hb-customer-calendar-wrapper">
            <h2><?php esc_html_e('My Bookings', 'hb-booking'); ?></h2>

            <?php if (empty($bookings)): ?>
                <p class="hb-no-bookings"><?php esc_html_e('You have no bookings yet.', 'hb-booking'); ?></p>
            <?php else: ?>
                <div class="hb-bookings-list">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="hb-booking-item hb-booking-status-<?php echo esc_attr($booking->status); ?>">
                            <div class="hb-booking-date">
                                <span class="hb-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date))); ?></span>
                                <span class="hb-time"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->booking_time))); ?></span>
                            </div>
                            <div class="hb-booking-details">
                                <?php if ($booking->service): ?>
                                    <p class="hb-service"><strong><?php esc_html_e('Service:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->service); ?></p>
                                <?php endif; ?>
                                <?php if ($booking->notes): ?>
                                    <p class="hb-notes"><strong><?php esc_html_e('Notes:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->notes); ?></p>
                                <?php endif; ?>
                                <p class="hb-status">
                                    <strong><?php esc_html_e('Status:', 'hb-booking'); ?></strong>
                                    <span class="hb-status-badge hb-status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo esc_html(ucfirst($booking->status)); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
