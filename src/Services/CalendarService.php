<?php
/**
 * Calendar service
 * Handles calendar integration (Google Calendar, iCal, etc.)
 */

namespace HB\Booking\Services;

class CalendarService
{
    private static ?CalendarService $instance = null;

    private function __construct()
    {
        // Initialize calendar integration
    }

    public static function getInstance(): CalendarService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add booking to calendar
     * Returns event ID or false on failure
     */
    public function addToCalendar(object $booking): string|false
    {
        $calendar_type = get_option('hb_booking_calendar_integration', 'none');

        switch ($calendar_type) {
            case 'google':
                return $this->addToGoogleCalendar($booking);
            case 'ical':
                return $this->generateICalEvent($booking);
            default:
                return false;
        }
    }

    /**
     * Update calendar event
     */
    public function updateCalendarEvent(object $booking): bool
    {
        if (empty($booking->google_event_id)) {
            return false;
        }

        $calendar_type = get_option('hb_booking_calendar_integration', 'none');

        switch ($calendar_type) {
            case 'google':
                return $this->updateGoogleCalendarEvent($booking);
            default:
                return false;
        }
    }

    /**
     * Remove booking from calendar
     */
    public function removeFromCalendar(object $booking): bool
    {
        if (empty($booking->google_event_id)) {
            return false;
        }

        $calendar_type = get_option('hb_booking_calendar_integration', 'none');

        switch ($calendar_type) {
            case 'google':
                return $this->removeFromGoogleCalendar($booking);
            default:
                return false;
        }
    }

    /**
     * Add to Google Calendar
     * This is a placeholder - requires Google Calendar API setup
     */
    private function addToGoogleCalendar(object $booking): string|false
    {
        // TODO: Implement Google Calendar API integration
        // Requires OAuth2 authentication and Google Calendar API client
        // For now, return a mock event ID

        /**
         * Implementation would include:
         * 1. Authenticate with Google Calendar API using OAuth2
         * 2. Create event with booking details
         * 3. Add attendees (customer email, admin email)
         * 4. Set reminders
         * 5. Return event ID
         *
         * Example structure:
         * $event = [
         *     'summary' => "Booking: {$booking->service}",
         *     'description' => $booking->notes,
         *     'start' => [
         *         'dateTime' => "{$booking->booking_date}T{$booking->booking_time}:00",
         *         'timeZone' => wp_timezone_string(),
         *     ],
         *     'end' => [
         *         'dateTime' => date('Y-m-d\TH:i:s', strtotime("$booking->booking_date $booking->booking_time +1 hour")),
         *         'timeZone' => wp_timezone_string(),
         *     ],
         *     'attendees' => [
         *         ['email' => $booking->customer_email],
         *         ['email' => get_option('admin_email')],
         *     ],
         * ];
         */

        // Apply filter to allow custom implementation
        return apply_filters('hb_booking_google_calendar_event_id', false, $booking);
    }

    /**
     * Update Google Calendar event
     */
    private function updateGoogleCalendarEvent(object $booking): bool
    {
        // TODO: Implement Google Calendar API update
        return apply_filters('hb_booking_google_calendar_update', false, $booking);
    }

    /**
     * Remove from Google Calendar
     */
    private function removeFromGoogleCalendar(object $booking): bool
    {
        // TODO: Implement Google Calendar API deletion
        return apply_filters('hb_booking_google_calendar_delete', false, $booking);
    }

    /**
     * Generate iCal format event
     */
    private function generateICalEvent(object $booking): string
    {
        $start_datetime = strtotime("{$booking->booking_date} {$booking->booking_time}");
        $end_datetime = strtotime("+1 hour", $start_datetime);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//HB Booking//WordPress Plugin//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . md5($booking->id . $booking->customer_email) . "\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . gmdate('Ymd\THis\Z', $start_datetime) . "\r\n";
        $ical .= "DTEND:" . gmdate('Ymd\THis\Z', $end_datetime) . "\r\n";
        $ical .= "SUMMARY:Booking: " . ($booking->service ?: 'Appointment') . "\r\n";

        if ($booking->notes) {
            $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $booking->notes) . "\r\n";
        }

        $ical .= "ORGANIZER:mailto:" . get_option('admin_email') . "\r\n";
        $ical .= "ATTENDEE:mailto:" . $booking->customer_email . "\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        // Store as attachment or return link
        return $this->saveICalFile($booking->id, $ical);
    }

    /**
     * Save iCal file and return filename
     */
    private function saveICalFile(int $booking_id, string $ical_content): string
    {
        $upload_dir = wp_upload_dir();
        $ical_dir = $upload_dir['basedir'] . '/hb-booking-icals';

        if (!file_exists($ical_dir)) {
            wp_mkdir_p($ical_dir);
        }

        $filename = "booking-{$booking_id}.ics";
        $filepath = $ical_dir . '/' . $filename;

        file_put_contents($filepath, $ical_content);

        return $filename;
    }

    /**
     * Get iCal download URL
     */
    public function getICalDownloadUrl(int $booking_id): string
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . "/hb-booking-icals/booking-{$booking_id}.ics";
    }
}
