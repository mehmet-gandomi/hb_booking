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
        if (!wp_next_scheduled('hb_booking_send_reminders')) {
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
            error_log("HB Booking - No bookings need 30-minute reminders at this time");
            return;
        }

        error_log("HB Booking - Found " . count($bookings) . " bookings needing 30-minute reminders");

        foreach ($bookings as $booking) {
            // Send 30-minute reminder email
            $sent = $this->emailService->send30MinReminderEmail($booking);

            // Mark as sent if successful
            if ($sent) {
                $this->database->mark30MinReminderSent($booking->id);
                error_log("HB Booking - 30-min reminder sent for booking #{$booking->id} to {$booking->customer_email}");
            } else {
                error_log("HB Booking - Failed to send 30-min reminder for booking #{$booking->id}");
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
            error_log("HB Booking - No bookings need 24-hour reminders at this time");
            return;
        }

        error_log("HB Booking - Found " . count($bookings) . " bookings needing 24-hour reminders");

        foreach ($bookings as $booking) {
            // Send 24-hour reminder email
            $sent = $this->emailService->send24HourReminderEmail($booking);

            // Mark as sent if successful
            if ($sent) {
                $this->database->mark24HourReminderSent($booking->id);
                error_log("HB Booking - 24-hour reminder sent for booking #{$booking->id} to {$booking->customer_email}");
            } else {
                error_log("HB Booking - Failed to send 24-hour reminder for booking #{$booking->id}");
            }
        }
    }
}
