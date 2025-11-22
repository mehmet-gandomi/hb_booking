<?php
/**
 * Reminder service
 * Handles sending reminder emails before bookings
 */

namespace HB\Booking\Services;

use HB\Booking\Core\Database;

class ReminderService
{
    private static ?ReminderService $instance = null;
    private Database $database;
    private EmailService $emailService;

    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->emailService = EmailService::getInstance();

        // Register cron hooks
        add_action('hb_booking_send_reminders', [$this, 'sendReminders']);

        // Register cron schedule
        add_filter('cron_schedules', [$this, 'addCronSchedule']);
    }

    public static function getInstance(): ReminderService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add custom cron schedule for every 15 minutes
     */
    public function addCronSchedule(array $schedules): array
    {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * 60, // 15 minutes in seconds
            'display'  => __('Every 15 Minutes', 'hb-booking'),
        ];

        return $schedules;
    }

    /**
     * Schedule the cron job
     */
    public static function scheduleCron(): void
    {
        // Ensure the custom schedule is registered before using it
        add_filter('cron_schedules', function($schedules) {
            if (!isset($schedules['every_15_minutes'])) {
                $schedules['every_15_minutes'] = [
                    'interval' => 15 * 60,
                    'display'  => __('Every 15 Minutes', 'hb-booking'),
                ];
            }
            return $schedules;
        });

        $next_scheduled = wp_next_scheduled('hb_booking_send_reminders');

        if (!$next_scheduled) {
            // Clear any existing scheduled events first
            wp_clear_scheduled_hook('hb_booking_send_reminders');

            wp_schedule_event(time(), 'every_15_minutes', 'hb_booking_send_reminders');
        }
    }

    /**
     * Unschedule the cron job
     */
    public static function unscheduleCron(): void
    {
        $timestamp = wp_next_scheduled('hb_booking_send_reminders');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hb_booking_send_reminders');
        }
    }

    /**
     * Send reminder emails for upcoming bookings
     */
    public function sendReminders(): void
    {
        // Send 24-hour reminders
        $this->send24HourReminders();

        // Send 30-minute reminders
        $this->send30MinReminders();
    }

    /**
     * Send 30-minute reminder emails
     */
    private function send30MinReminders(): void
    {
        $bookings = $this->database->getBookingsNeeding30MinReminders();

        if (empty($bookings)) {
            return;
        }

        foreach ($bookings as $booking) {
            // Send 30-minute reminder email
            $sent = $this->emailService->send30MinReminderEmail($booking);

            // Mark as sent if successful
            if ($sent) {
                $this->database->mark30MinReminderSent($booking->id);
            }
        }
    }

    /**
     * Send 24-hour reminder emails
     */
    private function send24HourReminders(): void
    {
        $bookings = $this->database->getBookingsNeeding24HourReminders();

        if (empty($bookings)) {
            return;
        }

        foreach ($bookings as $booking) {
            // Send 24-hour reminder email
            $sent = $this->emailService->send24HourReminderEmail($booking);

            // Mark as sent if successful
            if ($sent) {
                $this->database->mark24HourReminderSent($booking->id);
            }
        }
    }
}
