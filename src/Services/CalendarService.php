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
     */
    private function addToGoogleCalendar(object $booking): string|false
    {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return false;
        }

        $calendar_id = get_option('hb_booking_google_calendar_id', 'primary');
        $start_datetime = strtotime("{$booking->booking_date} {$booking->booking_time}");
        $end_datetime = strtotime("+1 hour", $start_datetime);

        $event = [
            'summary' => "Booking: " . ($booking->service ?: 'Appointment'),
            'description' => $booking->notes ?: "Customer: {$booking->customer_name}\nPhone: {$booking->customer_phone}",
            'start' => [
                'dateTime' => date('c', $start_datetime),
                'timeZone' => wp_timezone_string(),
            ],
            'end' => [
                'dateTime' => date('c', $end_datetime),
                'timeZone' => wp_timezone_string(),
            ],
            'attendees' => [
                ['email' => $booking->customer_email, 'displayName' => $booking->customer_name],
                ['email' => get_option('hb_booking_admin_email', get_option('admin_email'))],
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 30],
                ],
            ],
        ];

        $response = wp_remote_post(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($event),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            return false;
        }

        return $body['id'] ?? false;
    }

    /**
     * Update Google Calendar event
     */
    private function updateGoogleCalendarEvent(object $booking): bool
    {
        $access_token = $this->getAccessToken();
        if (!$access_token || empty($booking->google_event_id)) {
            return false;
        }

        $calendar_id = get_option('hb_booking_google_calendar_id', 'primary');
        $start_datetime = strtotime("{$booking->booking_date} {$booking->booking_time}");
        $end_datetime = strtotime("+1 hour", $start_datetime);

        $event = [
            'summary' => "Booking: " . ($booking->service ?: 'Appointment'),
            'description' => $booking->notes ?: "Customer: {$booking->customer_name}\nPhone: {$booking->customer_phone}",
            'start' => [
                'dateTime' => date('c', $start_datetime),
                'timeZone' => wp_timezone_string(),
            ],
            'end' => [
                'dateTime' => date('c', $end_datetime),
                'timeZone' => wp_timezone_string(),
            ],
            'attendees' => [
                ['email' => $booking->customer_email, 'displayName' => $booking->customer_name],
                ['email' => get_option('hb_booking_admin_email', get_option('admin_email'))],
            ],
        ];

        $response = wp_remote_request(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$booking->google_event_id}",
            [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($event),
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Remove from Google Calendar
     */
    private function removeFromGoogleCalendar(object $booking): bool
    {
        $access_token = $this->getAccessToken();
        if (!$access_token || empty($booking->google_event_id)) {
            return false;
        }

        $calendar_id = get_option('hb_booking_google_calendar_id', 'primary');

        $response = wp_remote_request(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$booking->google_event_id}",
            [
                'method' => 'DELETE',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 204;
    }

    /**
     * Get access token using refresh token
     */
    private function getAccessToken(): string|false
    {
        $refresh_token = get_option('hb_booking_google_refresh_token');
        if (!$refresh_token) {
            return false;
        }

        $client_id = get_option('hb_booking_google_client_id');
        $client_secret = get_option('hb_booking_google_client_secret');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? false;
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
