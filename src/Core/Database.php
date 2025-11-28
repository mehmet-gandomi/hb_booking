<?php
/**
 * Database layer
 * Handles all database operations using WordPress WPDB
 */

namespace HB\Booking\Core;

class Database
{
    private static ?Database $instance = null;
    private string $table_name;

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hb_bookings';
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create a new booking
     */
    public function createBooking(array $data): int|false
    {
        global $wpdb;

        $sanitized = $this->sanitizeBookingData($data);

        // Generate format array based on actual fields present
        $formats = array_fill(0, count($sanitized), '%s');

        $result = $wpdb->insert(
            $this->table_name,
            $sanitized,
            $formats
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get booking by ID
     */
    public function getBooking(int $id): ?object
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );

        $result = $wpdb->get_row($query);

        return $result ?: null;
    }

    /**
     * Get all bookings with filters
     */
    public function getBookings(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'booking_date >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'booking_date <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['customer_email'])) {
            $where[] = 'customer_email = %s';
            $params[] = $filters['customer_email'];
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->table_name} WHERE $where_clause ORDER BY booking_date DESC, booking_time DESC";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Update booking
     */
    public function updateBooking(int $id, array $data): bool
    {
        global $wpdb;

        $sanitized = $this->sanitizeBookingData($data);

        $result = $wpdb->update(
            $this->table_name,
            $sanitized,
            ['id' => $id],
            null,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete booking
     */
    public function deleteBooking(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Check if time slot is available
     */
    public function isTimeSlotAvailable(string $date, string $time, ?int $exclude_id = null): bool
    {
        global $wpdb;

        // Base query parts
        $where_conditions = [
            $wpdb->prepare("booking_date = %s", $date),
            $wpdb->prepare("booking_time = %s", $time),
            "status != 'cancelled'"
        ];

        if ($exclude_id) {
            $where_conditions[] = $wpdb->prepare("id != %d", $exclude_id);
        }

        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        $count = $wpdb->get_var($query);

        // Debug logging
        error_log("HB Booking - Time Slot Check: Date={$date}, Time={$time}, Count={$count}, Available=" . ($count === '0' || $count === 0 ? 'true' : 'false'));

        return $count === '0' || $count === 0 || $count === null;
    }

    /**
     * Get booked time slots for a specific date
     */
    public function getBookedTimesForDate(string $date): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT booking_time FROM {$this->table_name}
             WHERE booking_date = %s AND status != 'cancelled'
             ORDER BY booking_time ASC",
            $date
        );

        $results = $wpdb->get_col($query);

        return $results ?: [];
    }

    /**
     * Get bookings that need 30-minute reminder emails
     */
    public function getBookingsNeeding30MinReminders(): array
    {
        global $wpdb;

        // Calculate the time range: bookings 25-35 minutes from now
        // This 10-minute window ensures we catch bookings even if cron is delayed
        $now = current_time('timestamp');
        $reminder_start = $now + (25 * MINUTE_IN_SECONDS);
        $reminder_end = $now + (35 * MINUTE_IN_SECONDS);

        $start_datetime = gmdate('Y-m-d H:i:s', $reminder_start);
        $end_datetime = gmdate('Y-m-d H:i:s', $reminder_end);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE CONCAT(booking_date, ' ', booking_time) >= %s
             AND CONCAT(booking_date, ' ', booking_time) < %s
             AND status IN ('pending', 'confirmed')
             AND (reminder_sent_30min = 0 OR reminder_sent_30min IS NULL)
             ORDER BY booking_date ASC, booking_time ASC",
            $start_datetime,
            $end_datetime
        );

        $results = $wpdb->get_results($query);
        return $results ?: [];
    }

    /**
     * Get bookings that need 24-hour reminder emails
     */
    public function getBookingsNeeding24HourReminders(): array
    {
        global $wpdb;

        // Calculate the time range: bookings 23-25 hours from now
        // This 2-hour window ensures we catch bookings even if cron is delayed
        $now = current_time('timestamp');
        $reminder_start = $now + (23 * HOUR_IN_SECONDS);
        $reminder_end = $now + (25 * HOUR_IN_SECONDS);

        $start_datetime = gmdate('Y-m-d H:i:s', $reminder_start);
        $end_datetime = gmdate('Y-m-d H:i:s', $reminder_end);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE CONCAT(booking_date, ' ', booking_time) >= %s
             AND CONCAT(booking_date, ' ', booking_time) < %s
             AND status IN ('pending', 'confirmed')
             AND (reminder_sent_24h = 0 OR reminder_sent_24h IS NULL)
             ORDER BY booking_date ASC, booking_time ASC",
            $start_datetime,
            $end_datetime
        );

        $results = $wpdb->get_results($query);
        return $results ?: [];
    }

    /**
     * Get bookings that need reminder emails (deprecated - kept for backward compatibility)
     */
    public function getBookingsNeedingReminders(): array
    {
        return $this->getBookingsNeeding30MinReminders();
    }

    /**
     * Mark 30-minute reminder as sent for a booking
     */
    public function mark30MinReminderSent(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['reminder_sent_30min' => 1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Mark 24-hour reminder as sent for a booking
     */
    public function mark24HourReminderSent(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            ['reminder_sent_24h' => 1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Mark reminder as sent for a booking (deprecated - kept for backward compatibility)
     */
    public function markReminderSent(int $id): bool
    {
        return $this->mark30MinReminderSent($id);
    }

    /**
     * Sanitize booking data
     */
    private function sanitizeBookingData(array $data): array
    {
        $sanitized = [];

        if (isset($data['customer_name'])) {
            $sanitized['customer_name'] = sanitize_text_field($data['customer_name']);
        }

        if (isset($data['customer_email'])) {
            $sanitized['customer_email'] = sanitize_email($data['customer_email']);
        }

        if (isset($data['customer_phone']) && !empty($data['customer_phone'])) {
            $sanitized['customer_phone'] = sanitize_text_field($data['customer_phone']);
        }

        if (isset($data['booking_date'])) {
            $sanitized['booking_date'] = sanitize_text_field($data['booking_date']);
        }

        if (isset($data['booking_time'])) {
            $sanitized['booking_time'] = sanitize_text_field($data['booking_time']);
        }

        if (isset($data['business_status'])) {
            $sanitized['business_status'] = sanitize_text_field($data['business_status']);
        }

        if (isset($data['target_country'])) {
            $sanitized['target_country'] = sanitize_text_field($data['target_country']);
        }

        if (isset($data['team_size'])) {
            $sanitized['team_size'] = absint($data['team_size']);
        }

        if (isset($data['services'])) {
            // Handle array or string (for multi-select)
            if (is_array($data['services'])) {
                $sanitized['services'] = sanitize_textarea_field(implode(', ', array_map('sanitize_text_field', $data['services'])));
            } else {
                $sanitized['services'] = sanitize_textarea_field($data['services']);
            }
        }

        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }

        if (isset($data['status'])) {
            $sanitized['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['google_event_id'])) {
            $sanitized['google_event_id'] = sanitize_text_field($data['google_event_id']);
        }

        return $sanitized;
    }
}
