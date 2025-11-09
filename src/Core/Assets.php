<?php
/**
 * Assets manager
 * Handles CSS and JavaScript enqueuing
 */

namespace HB\Booking\Core;

class Assets
{
    private static ?Assets $instance = null;

    private function __construct()
    {
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

        wp_enqueue_script(
            'hb-booking-frontend',
            HB_BOOKING_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            HB_BOOKING_VERSION,
            true
        );

        wp_localize_script('hb-booking-frontend', 'hbBooking', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('hb-booking/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
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

        wp_enqueue_script(
            'hb-booking-admin',
            HB_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-datepicker', 'fullcalendar'],
            HB_BOOKING_VERSION,
            true
        );

        wp_enqueue_style('jquery-ui-datepicker');

        wp_localize_script('hb-booking-admin', 'hbBookingAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('hb-booking/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
