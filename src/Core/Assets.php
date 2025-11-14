<?php
/**
 * Assets manager
 * Handles CSS and JavaScript enqueuing
 */

namespace HB\Booking\Core;

use HB\Booking\Services\DateConverter;

class Assets
{
    private static ?Assets $instance = null;
    private DateConverter $date_converter;

    private function __construct()
    {
        $this->date_converter = DateConverter::getInstance();
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public static function getInstance(): Assets
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style(
            'hb-booking-frontend',
            HB_BOOKING_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            HB_BOOKING_VERSION
        );

        // Enqueue Persian datepicker if Jalali calendar is enabled
        if ($this->date_converter->isJalali()) {
            wp_enqueue_style(
                'persian-datepicker',
                HB_BOOKING_PLUGIN_URL . 'assets/css/persian-datepicker.min.css',
                [],
                '1.2.0'
            );

            // Load dependencies for Persian datepicker from local files
            // Load moment.js first
            wp_enqueue_script(
                'moment',
                HB_BOOKING_PLUGIN_URL . 'assets/js/moment.min.js',
                [],
                '2.29.4',
                true
            );

            // Then persian-date (depends on moment)
            wp_enqueue_script(
                'persian-date',
                HB_BOOKING_PLUGIN_URL . 'assets/js/persian-date.min.js',
                ['moment'],
                '1.1.0',
                true
            );

            // Then persian-datepicker (depends on jQuery, moment, and persian-date)
            wp_enqueue_script(
                'persian-datepicker',
                HB_BOOKING_PLUGIN_URL . 'assets/js/persian-datepicker.min.js',
                ['jquery', 'moment', 'persian-date'],
                '1.2.0',
                true
            );
        }

        // Set proper dependencies for frontend.js
        $frontend_deps = ['jquery'];
        if ($this->date_converter->isJalali()) {
            $frontend_deps = ['jquery', 'moment', 'persian-date', 'persian-datepicker'];
        }

        wp_enqueue_script(
            'hb-booking-frontend',
            HB_BOOKING_PLUGIN_URL . 'assets/js/frontend.js',
            $frontend_deps,
            HB_BOOKING_VERSION,
            true
        );

        wp_localize_script('hb-booking-frontend', 'hbBooking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('hb-booking/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'dateConfig' => $this->date_converter->getDatepickerConfig(),
            'i18n' => [
                'pleaseWait' => __('Please wait...', 'hb-booking'),
                'error' => __('An error occurred', 'hb-booking'),
                'success' => __('Booking submitted successfully!', 'hb-booking'),
            ]
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'hb-booking') === false) {
            return;
        }

        // FullCalendar library for calendar view
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            [],
            '6.1.10'
        );

        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            [],
            '6.1.10',
            true
        );

        wp_enqueue_style(
            'hb-booking-admin',
            HB_BOOKING_PLUGIN_URL . 'assets/css/admin.css',
            ['fullcalendar'],
            HB_BOOKING_VERSION
        );

        // Enqueue datepicker based on calendar type
        $dependencies = ['jquery', 'fullcalendar'];

        if ($this->date_converter->isJalali()) {
            // Enqueue Persian datepicker for Jalali calendar
            wp_enqueue_style(
                'persian-datepicker',
                HB_BOOKING_PLUGIN_URL . 'assets/css/persian-datepicker.min.css',
                [],
                '1.2.0'
            );

            // Load dependencies for Persian datepicker from local files
            wp_enqueue_script(
                'moment',
                HB_BOOKING_PLUGIN_URL . 'assets/js/moment.min.js',
                [],
                '2.29.4',
                true
            );

            wp_enqueue_script(
                'persian-date',
                HB_BOOKING_PLUGIN_URL . 'assets/js/persian-date.min.js',
                ['moment'],
                '1.1.0',
                true
            );

            wp_enqueue_script(
                'persian-datepicker',
                HB_BOOKING_PLUGIN_URL . 'assets/js/persian-datepicker.min.js',
                ['jquery', 'moment', 'persian-date'],
                '1.2.0',
                true
            );

            $dependencies[] = 'moment';
            $dependencies[] = 'persian-date';
            $dependencies[] = 'persian-datepicker';
        } else {
            // Enqueue jQuery UI datepicker for Gregorian calendar
            wp_enqueue_style('jquery-ui-datepicker');
            $dependencies[] = 'jquery-ui-datepicker';
        }

        wp_enqueue_script(
            'hb-booking-admin',
            HB_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
            $dependencies,
            HB_BOOKING_VERSION,
            true
        );

        wp_localize_script('hb-booking-admin', 'hbBookingAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('hb-booking/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'dateConfig' => $this->date_converter->getDatepickerConfig(),
        ]);
    }
}
