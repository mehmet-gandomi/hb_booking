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
        $this->runMigrations();
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
            business_status varchar(255) DEFAULT NULL,
            target_country varchar(255) DEFAULT NULL,
            team_size int DEFAULT NULL,
            services text DEFAULT NULL,
            notes text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            google_event_id varchar(255) DEFAULT NULL,
            reminder_sent_24h tinyint(1) DEFAULT 0,
            reminder_sent_30min tinyint(1) DEFAULT 0,
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
            'hb_booking_calendar_type' => 'gregorian',
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

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hb_bookings';

        // Check if old reminder_sent column exists (for backward compatibility)
        $old_column = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent'",
                DB_NAME,
                $table_name
            )
        );

        // If old column exists, rename it to reminder_sent_30min
        if (!empty($old_column)) {
            $wpdb->query(
                "ALTER TABLE {$table_name}
                CHANGE COLUMN reminder_sent reminder_sent_30min tinyint(1) DEFAULT 0"
            );
        }

        // Check if reminder_sent_24h column exists
        $column_24h = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent_24h'",
                DB_NAME,
                $table_name
            )
        );

        // Add reminder_sent_24h column if it doesn't exist
        if (empty($column_24h)) {
            $wpdb->query(
                "ALTER TABLE {$table_name}
                ADD COLUMN reminder_sent_24h tinyint(1) DEFAULT 0 AFTER google_event_id"
            );
        }

        // Check if reminder_sent_30min column exists
        $column_30min = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent_30min'",
                DB_NAME,
                $table_name
            )
        );

        // Add reminder_sent_30min column if it doesn't exist
        if (empty($column_30min)) {
            $wpdb->query(
                "ALTER TABLE {$table_name}
                ADD COLUMN reminder_sent_30min tinyint(1) DEFAULT 0 AFTER reminder_sent_24h"
            );
        }
    }
}
