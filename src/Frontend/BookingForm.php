<?php
/**
 * Booking form frontend
 * Handles the customer-facing booking form
 */

namespace HB\Booking\Frontend;

use HB\Booking\Services\DateConverter;

class BookingForm
{
    private static ?BookingForm $instance = null;
    private DateConverter $date_converter;

    private function __construct()
    {
        $this->date_converter = DateConverter::getInstance();
        add_shortcode('hb_booking_form', [$this, 'renderForm']);
    }

    public static function getInstance(): BookingForm
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Render booking form shortcode
     */
    public function renderForm(array $atts = []): string
    {
        $atts = shortcode_atts([
            'title' => __('Book an Appointment', 'hb-booking'),
            'show_service' => true,
        ], $atts);

        ob_start();
        ?>
        <div class="hb-booking-form-wrapper">
            <h2 class="hb-booking-title"><?php echo esc_html($atts['title']); ?></h2>

            <form id="hb-booking-form" class="hb-booking-form" method="post">
                <?php wp_nonce_field('hb_booking_submit', 'hb_booking_nonce'); ?>

                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label for="hb-customer-name">
                            <?php esc_html_e('Full Name', 'hb-booking'); ?> <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="hb-customer-name"
                            name="customer_name"
                            class="hb-form-control"
                            required
                            aria-required="true"
                        />
                    </div>

                    <div class="hb-form-group">
                        <label for="hb-customer-email">
                            <?php esc_html_e('Email Address', 'hb-booking'); ?> <span class="required">*</span>
                        </label>
                        <input
                            type="email"
                            id="hb-customer-email"
                            name="customer_email"
                            class="hb-form-control"
                            required
                            aria-required="true"
                        />
                    </div>
                </div>

                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label for="hb-customer-phone">
                            <?php esc_html_e('Phone Number', 'hb-booking'); ?> <span class="required">*</span>
                        </label>
                        <input
                            type="tel"
                            id="hb-customer-phone"
                            name="customer_phone"
                            class="hb-form-control"
                            required
                            aria-required="true"
                        />
                    </div>

                    <?php if ($atts['show_service']): ?>
                    <div class="hb-form-group">
                        <label for="hb-service">
                            <?php esc_html_e('Service', 'hb-booking'); ?>
                        </label>
                        <select id="hb-service" name="service" class="hb-form-control">
                            <option value=""><?php esc_html_e('Select a service', 'hb-booking'); ?></option>
                            <?php echo $this->getServiceOptions(); ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label for="hb-booking-date">
                            <?php esc_html_e('Preferred Date', 'hb-booking'); ?> <span class="required">*</span>
                        </label>
                        <?php if ($this->date_converter->isJalali()): ?>
                            <input
                                type="text"
                                id="hb-booking-date"
                                name="booking_date"
                                class="hb-form-control hb-datepicker-jalali"
                                required
                                aria-required="true"
                                autocomplete="off"
                                placeholder="YYYY-MM-DD"
                            />
                        <?php else: ?>
                            <input
                                type="date"
                                id="hb-booking-date"
                                name="booking_date"
                                class="hb-form-control"
                                min="<?php echo esc_attr(date('Y-m-d')); ?>"
                                required
                                aria-required="true"
                            />
                        <?php endif; ?>
                    </div>

                    <div class="hb-form-group">
                        <label for="hb-booking-time">
                            <?php esc_html_e('Preferred Time', 'hb-booking'); ?> <span class="required">*</span>
                        </label>
                        <select id="hb-booking-time" name="booking_time" class="hb-form-control" required aria-required="true">
                            <option value=""><?php esc_html_e('Select a time', 'hb-booking'); ?></option>
                            <?php echo $this->getTimeSlotOptions(); ?>
                        </select>
                    </div>
                </div>

                <div class="hb-form-group">
                    <label for="hb-notes">
                        <?php esc_html_e('Additional Notes', 'hb-booking'); ?>
                    </label>
                    <textarea
                        id="hb-notes"
                        name="notes"
                        class="hb-form-control"
                        rows="4"
                        placeholder="<?php esc_attr_e('Any special requests or information...', 'hb-booking'); ?>"
                    ></textarea>
                </div>

                <div class="hb-form-actions">
                    <button type="submit" class="hb-btn hb-btn-primary">
                        <?php esc_html_e('Submit Booking', 'hb-booking'); ?>
                    </button>
                </div>

                <div class="hb-form-messages" role="alert" aria-live="polite"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get service options (can be filtered)
     */
    private function getServiceOptions(): string
    {
        $services = apply_filters('hb_booking_services', [
            'consultation' => __('Consultation', 'hb-booking'),
            'meeting' => __('Meeting', 'hb-booking'),
            'appointment' => __('General Appointment', 'hb-booking'),
        ]);

        $options = '';
        foreach ($services as $value => $label) {
            $options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($value),
                esc_html($label)
            );
        }

        return $options;
    }

    /**
     * Get time slot options
     */
    private function getTimeSlotOptions(): string
    {
        $start_hour = apply_filters('hb_booking_start_hour', 9);
        $end_hour = apply_filters('hb_booking_end_hour', 17);
        $interval = apply_filters('hb_booking_interval', 30); // minutes

        $options = '';
        $current = strtotime("$start_hour:00");
        $end = strtotime("$end_hour:00");

        while ($current <= $end) {
            $time = date('H:i', $current);
            $display = date('g:i A', $current);
            $options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($time),
                esc_html($display)
            );
            $current = strtotime("+$interval minutes", $current);
        }

        return $options;
    }
}
