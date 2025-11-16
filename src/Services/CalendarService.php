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

        if ($calendar_type === 'google') {
            return $this->addToGoogleCalendar($booking);
        }

        return false;
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

        // Always use Asia/Tehran timezone for bookings
        $timezone = 'Asia/Tehran';

        // Create DateTime objects with Tehran timezone
        $start_datetime = new \DateTime("{$booking->booking_date} {$booking->booking_time}", new \DateTimeZone($timezone));
        $end_datetime = clone $start_datetime;
        $end_datetime->modify('+1 hour');

        // Build comprehensive description with all booking details
        $description = $this->buildEventDescription($booking);

        // Create event summary
        $summary = sprintf(
            'جلسه مشاوره: %s - %s',
            $booking->customer_name,
            $booking->target_country ?? 'کشور نامشخص'
        );

        $event = [
            'summary' => $summary,
            'description' => $description,
            'location' => $booking->target_country ?? '',
            'start' => [
                'dateTime' => $start_datetime->format('c'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end_datetime->format('c'),
                'timeZone' => $timezone,
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
            'colorId' => '9', // Blue color for consultation bookings
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

        // Always use Asia/Tehran timezone for bookings
        $timezone = 'Asia/Tehran';

        // Create DateTime objects with Tehran timezone
        $start_datetime = new \DateTime("{$booking->booking_date} {$booking->booking_time}", new \DateTimeZone($timezone));
        $end_datetime = clone $start_datetime;
        $end_datetime->modify('+1 hour');

        // Build comprehensive description with all booking details
        $description = $this->buildEventDescription($booking);

        // Create event summary
        $summary = sprintf(
            'جلسه مشاوره: %s - %s',
            $booking->customer_name,
            $booking->target_country ?? 'کشور نامشخص'
        );

        $event = [
            'summary' => $summary,
            'description' => $description,
            'location' => $booking->target_country ?? '',
            'start' => [
                'dateTime' => $start_datetime->format('c'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end_datetime->format('c'),
                'timeZone' => $timezone,
            ],
            'attendees' => [
                ['email' => $booking->customer_email, 'displayName' => $booking->customer_name],
                ['email' => get_option('hb_booking_admin_email', get_option('admin_email'))],
            ],
            'colorId' => '9', // Blue color for consultation bookings
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
     * Build comprehensive event description with all booking details
     */
    private function buildEventDescription(object $booking): string
    {
        $description_parts = [];

        // Header
        $description_parts[] = "📋 جزئیات جلسه مشاوره";
        $description_parts[] = str_repeat("=", 50);
        $description_parts[] = "";

        // Customer Information
        $description_parts[] = "👤 اطلاعات شما:";
        $description_parts[] = "نام: {$booking->customer_name}";
        $description_parts[] = "ایمیل: {$booking->customer_email}";
        $description_parts[] = "تلفن: {$booking->customer_phone}";
        $description_parts[] = "";

        // Business Information
        if (!empty($booking->business_status)) {
            $description_parts[] = "💼 وضعیت کسب و کار:";
            $description_parts[] = $booking->business_status;
            $description_parts[] = "";
        }

        if (!empty($booking->target_country)) {
            $description_parts[] = "🌍 کشور مقصد:";
            $description_parts[] = $booking->target_country;
            $description_parts[] = "";
        }

        // Team Information
        if (!empty($booking->team_description)) {
            $description_parts[] = "👥 اطلاعات تیم:";
            $description_parts[] = $booking->team_description;
            $description_parts[] = "";
        }

        // Business Idea
        if (!empty($booking->idea_description)) {
            $description_parts[] = "💡 توضیح ایده:";
            $description_parts[] = $booking->idea_description;
            $description_parts[] = "";
        }

        // Service Requirements
        if (!empty($booking->service_description)) {
            $description_parts[] = "🎯 خدمات مورد نیاز:";
            $description_parts[] = $booking->service_description;
            $description_parts[] = "";
        }

        // Additional Notes
        if (!empty($booking->notes)) {
            $description_parts[] = "📝 یادداشت‌های اضافی:";
            $description_parts[] = $booking->notes;
            $description_parts[] = "";
        }

        // Booking Status
        $description_parts[] = str_repeat("-", 50);
        $description_parts[] = "وضعیت رزرو: " . $this->getStatusLabel($booking->status ?? 'pending');
        $description_parts[] = "شماره رزرو: #{$booking->id}";
        $description_parts[] = "";
        $description_parts[] = "⏰ زمان جلسه به وقت تهران (UTC+3:30) است";

        return implode("\n", $description_parts);
    }

    /**
     * Get Persian label for booking status
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => '⏳ در انتظار تایید',
            'confirmed' => '✅ تایید شده',
            'cancelled' => '❌ لغو شده',
            'completed' => '✔️ انجام شده',
        ];

        return $labels[$status] ?? $status;
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

        // Build comprehensive description
        $description = $this->buildEventDescription($booking);
        $escaped_description = str_replace(["\n", "\r"], ["\\n", ""], $description);

        // Create summary
        $summary = sprintf(
            'جلسه مشاوره: %s - %s',
            $booking->customer_name,
            $booking->target_country ?? 'کشور نامشخص'
        );

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//HB Booking//WordPress Plugin//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . md5($booking->id . $booking->customer_email . time()) . "@hb-booking\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . gmdate('Ymd\THis\Z', $start_datetime) . "\r\n";
        $ical .= "DTEND:" . gmdate('Ymd\THis\Z', $end_datetime) . "\r\n";
        $ical .= "SUMMARY:" . $summary . "\r\n";
        $ical .= "DESCRIPTION:" . $escaped_description . "\r\n";

        if (!empty($booking->target_country)) {
            $ical .= "LOCATION:" . $booking->target_country . "\r\n";
        }

        $ical .= "ORGANIZER;CN=" . get_bloginfo('name') . ":mailto:" . get_option('hb_booking_admin_email', get_option('admin_email')) . "\r\n";
        $ical .= "ATTENDEE;CN=" . $booking->customer_name . ";RSVP=TRUE:mailto:" . $booking->customer_email . "\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "SEQUENCE:0\r\n";
        $ical .= "PRIORITY:5\r\n";
        $ical .= "CLASS:PUBLIC\r\n";
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
