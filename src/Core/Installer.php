<?php
/**
 * Plugin installer
 * Handles database table creation and initial setup
 */

namespace HB\Booking\Core;

class Installer
{
    /**
     * Install plugin
     */
    public function install(): void
    {
        $this->createTables();
        $this->setDefaultOptions();
    }

    /**
     * Create database tables
     */
    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'hb_bookings';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            service varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            google_event_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_email (customer_email),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Store database version
        update_option('hb_booking_db_version', HB_BOOKING_VERSION);
    }

    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void
    {
        $defaults = [
            'hb_booking_admin_email' => get_option('admin_email'),
            'hb_booking_time_format' => 'H:i',
            'hb_booking_date_format' => 'Y-m-d',
            'hb_booking_enable_notifications' => true,
            'hb_booking_calendar_integration' => 'google',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
