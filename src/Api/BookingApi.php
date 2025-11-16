<?php
/**
 * REST API endpoints for bookings
 * Handles AJAX requests and API interactions
 */

namespace HB\Booking\Api;

use HB\Booking\Core\Database;
use HB\Booking\Services\EmailService;
use HB\Booking\Services\CalendarService;
use HB\Booking\Services\DateConverter;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class BookingApi extends WP_REST_Controller
{
    private static ?BookingApi $instance = null;
    private Database $database;
    private EmailService $emailService;
    private CalendarService $calendarService;
    private DateConverter $dateConverter;

    protected $namespace = 'hb-booking/v1';
    protected $rest_base = 'bookings';

    private function __construct()
    {
        $this->database = Database::getInstance();
        $this->emailService = EmailService::getInstance();
        $this->calendarService = CalendarService::getInstance();
        $this->dateConverter = DateConverter::getInstance();

        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_filter('rest_authentication_errors', [$this, 'allowPublicAccess'], 100);
    }

    public static function getInstance(): BookingApi
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getItems'],
                'permission_callback' => [$this, 'getItemsPermissionsCheck'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createItem'],
                'permission_callback' => '__return_true',
                'args' => $this->getCreateArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getItem'],
                'permission_callback' => [$this, 'getItemPermissionsCheck'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateItem'],
                'permission_callback' => [$this, 'updateItemPermissionsCheck'],
                'args' => $this->getUpdateArgs(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'deleteItemPermissionsCheck'],
            ],
        ]);

        register_rest_route($this->namespace, '/check-availability', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'checkAvailability'],
            'permission_callback' => '__return_true',
            'args' => [
                'date' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'date',
                ],
                'time' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'time',
                ],
            ],
        ]);
    }

    /**
     * Get all bookings
     */
    public function getItems(WP_REST_Request $request): WP_REST_Response
    {
        $filters = [
            'status' => $request->get_param('status'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        ];

        $bookings = $this->database->getBookings(array_filter($filters));

        return new WP_REST_Response($bookings, 200);
    }

    /**
     * Get single booking
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $booking = $this->database->getBooking($request['id']);

        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found', 'hb-booking'), ['status' => 404]);
        }

        return new WP_REST_Response($booking, 200);
    }

    /**
     * Create new booking
     */
    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_params();

        // Validate required fields
        $validation = $this->validateBookingData($params);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Check availability
        if (!$this->database->isTimeSlotAvailable($params['booking_date'], $params['booking_time'])) {
            return new WP_Error(
                'time_slot_unavailable',
                __('This time slot is not available', 'hb-booking'),
                ['status' => 400]
            );
        }

        // Convert date to Gregorian for database storage
        $gregorian_date = $this->dateConverter->prepareForDatabase($params['booking_date']);

        // Create booking
        $booking_data = [
            'customer_name' => $params['customer_name'],
            'customer_email' => $params['customer_email'],
            'customer_phone' => $params['customer_phone'],
            'booking_date' => $gregorian_date,
            'booking_time' => $params['booking_time'],
            'business_status' => $params['business_status'] ?? '',
            'target_country' => $params['target_country'] ?? '',
            'team_description' => $params['team_description'] ?? '',
            'idea_description' => $params['idea_description'] ?? '',
            'service_description' => $params['service_description'] ?? '',
            'notes' => $params['notes'] ?? '',
            'status' => 'pending',
        ];

        $booking_id = $this->database->createBooking($booking_data);

        if (!$booking_id) {
            return new WP_Error(
                'creation_failed',
                __('Failed to create booking', 'hb-booking'),
                ['status' => 500]
            );
        }

        // Get the created booking
        $booking = $this->database->getBooking($booking_id);

        // Add to calendar
        $event_id = $this->calendarService->addToCalendar($booking);
        if ($event_id) {
            $this->database->updateBooking($booking_id, ['google_event_id' => $event_id]);
        }

        // Send notifications
        $this->emailService->sendBookingConfirmation($booking);
        $this->emailService->sendAdminNotification($booking);

        return new WP_REST_Response([
            'success' => true,
            'booking' => $booking,
            'message' => __('Booking created successfully', 'hb-booking'),
        ], 201);
    }

    /**
     * Update booking
     */
    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $booking_id = $request['id'];
        $params = $request->get_params();

        $booking = $this->database->getBooking($booking_id);
        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found', 'hb-booking'), ['status' => 404]);
        }

        // Update booking
        $update_data = array_filter([
            'customer_name' => $params['customer_name'] ?? null,
            'customer_email' => $params['customer_email'] ?? null,
            'customer_phone' => $params['customer_phone'] ?? null,
            'booking_date' => $params['booking_date'] ?? null,
            'booking_time' => $params['booking_time'] ?? null,
            'business_status' => $params['business_status'] ?? null,
            'target_country' => $params['target_country'] ?? null,
            'team_description' => $params['team_description'] ?? null,
            'idea_description' => $params['idea_description'] ?? null,
            'service_description' => $params['service_description'] ?? null,
            'notes' => $params['notes'] ?? null,
            'status' => $params['status'] ?? null,
        ], fn($value) => $value !== null);

        $success = $this->database->updateBooking($booking_id, $update_data);

        if (!$success) {
            return new WP_Error('update_failed', __('Failed to update booking', 'hb-booking'), ['status' => 500]);
        }

        $updated_booking = $this->database->getBooking($booking_id);

        // Update calendar if needed
        if (isset($params['booking_date']) || isset($params['booking_time']) || isset($params['status'])) {
            $this->calendarService->updateCalendarEvent($updated_booking);
        }

        return new WP_REST_Response([
            'success' => true,
            'booking' => $updated_booking,
            'message' => __('Booking updated successfully', 'hb-booking'),
        ], 200);
    }

    /**
     * Delete booking
     */
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $booking = $this->database->getBooking($request['id']);
        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found', 'hb-booking'), ['status' => 404]);
        }

        // Remove from calendar
        $this->calendarService->removeFromCalendar($booking);

        $success = $this->database->deleteBooking($request['id']);

        if (!$success) {
            return new WP_Error('deletion_failed', __('Failed to delete booking', 'hb-booking'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Booking deleted successfully', 'hb-booking'),
        ], 200);
    }

    /**
     * Check time slot availability
     */
    public function checkAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $date = $request->get_param('date');
        $time = $request->get_param('time');

        $available = $this->database->isTimeSlotAvailable($date, $time);

        return new WP_REST_Response([
            'available' => $available,
        ], 200);
    }

    /**
     * Validate booking data
     */
    private function validateBookingData(array $data): bool|WP_Error
    {
        if (empty($data['customer_name'])) {
            return new WP_Error('invalid_data', __('Customer name is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['customer_email']) || !is_email($data['customer_email'])) {
            return new WP_Error('invalid_data', __('Valid email address is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['customer_phone'])) {
            return new WP_Error('invalid_data', __('Phone number is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['booking_date'])) {
            return new WP_Error('invalid_data', __('Booking date is required', 'hb-booking'), ['status' => 400]);
        }

        // Validate date format based on calendar type
        if (!$this->dateConverter->isValidDate($data['booking_date'])) {
            return new WP_Error('invalid_data', __('Invalid date format', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['booking_time'])) {
            return new WP_Error('invalid_data', __('Booking time is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['business_status'])) {
            return new WP_Error('invalid_data', __('Business status is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['target_country'])) {
            return new WP_Error('invalid_data', __('Target country is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['team_description'])) {
            return new WP_Error('invalid_data', __('Team description is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['idea_description'])) {
            return new WP_Error('invalid_data', __('Idea description is required', 'hb-booking'), ['status' => 400]);
        }

        if (empty($data['service_description'])) {
            return new WP_Error('invalid_data', __('Service description is required', 'hb-booking'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Permission checks
     */
    public function getItemsPermissionsCheck(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function getItemPermissionsCheck(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function updateItemPermissionsCheck(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function deleteItemPermissionsCheck(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get arguments for create endpoint
     */
    private function getCreateArgs(): array
    {
        return [
            'customer_name' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_email' => [
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'sanitize_callback' => 'sanitize_email',
            ],
            'customer_phone' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'booking_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
            ],
            'booking_time' => [
                'required' => true,
                'type' => 'string',
                'format' => 'time',
            ],
            'business_status' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'target_country' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'team_description' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'idea_description' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'service_description' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'notes' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }

    /**
     * Get arguments for update endpoint
     */
    private function getUpdateArgs(): array
    {
        $args = $this->getCreateArgs();
        // Make all fields optional for updates
        foreach ($args as &$arg) {
            $arg['required'] = false;
        }
        $args['status'] = [
            'type' => 'string',
            'enum' => ['pending', 'confirmed', 'cancelled', 'completed'],
        ];
        return $args;
    }

    /**
     * Allow public access to specific endpoints
     */
    public function allowPublicAccess($result): mixed
    {
        // If there's already an authentication error, return it
        if (is_wp_error($result)) {
            return $result;
        }

        // Get the current request
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Allow public access to specific endpoints
        $public_endpoints = [
            '/wp-json/hb-booking/v1/check-availability',
            '/wp-json/hb-booking/v1/bookings',
        ];

        foreach ($public_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                // For check-availability, always allow
                if (strpos($request_uri, 'check-availability') !== false) {
                    return true;
                }
                // For bookings, allow POST (creating) but require auth for GET/PUT/DELETE
                if (strpos($request_uri, '/bookings') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    return true;
                }
            }
        }

        return $result;
    }
}
