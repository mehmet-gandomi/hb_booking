<?php
/**
 * Email service
 * Handles sending email notifications
 */

namespace HB\Booking\Services;

class EmailService
{
    private static ?EmailService $instance = null;
    private DateConverter $dateConverter;

    private function __construct()
    {
        $this->dateConverter = DateConverter::getInstance();
        add_filter('wp_mail_content_type', [$this, 'setHtmlContentType']);
    }

    public static function getInstance(): EmailService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set HTML content type for emails
     */
    public function setHtmlContentType(): string
    {
        return 'text/html';
    }

    /**
     * Send booking confirmation to customer
     */
    public function sendBookingConfirmation(object $booking): bool
    {
        if (!get_option('hb_booking_enable_notifications', true)) {
            return false;
        }

        $to = $booking->customer_email;
        $subject = sprintf(
            __('[%s] Booking Confirmation', 'hb-booking'),
            get_bloginfo('name')
        );

        $message = $this->getConfirmationEmailTemplate($booking);

        $headers = [
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send booking notification to admin
     */
    public function sendAdminNotification(object $booking): bool
    {
        if (!get_option('hb_booking_enable_notifications', true)) {
            return false;
        }

        $to = get_option('hb_booking_admin_email', get_option('admin_email'));
        $subject = sprintf(
            __('[%s] New Booking Received', 'hb-booking'),
            get_bloginfo('name')
        );

        $message = $this->getAdminNotificationTemplate($booking);

        $headers = [
            'Reply-To: ' . $booking->customer_name . ' <' . $booking->customer_email . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send booking status update notification
     */
    public function sendStatusUpdateNotification(object $booking): bool
    {
        if (!get_option('hb_booking_enable_notifications', true)) {
            return false;
        }

        $to = $booking->customer_email;
        $subject = sprintf(
            __('[%s] Booking Status Updated', 'hb-booking'),
            get_bloginfo('name')
        );

        $message = $this->getStatusUpdateTemplate($booking);

        $headers = [
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get confirmation email template
     */
    private function getConfirmationEmailTemplate(object $booking): string
    {
        $date = $this->dateConverter->formatDate($booking->booking_date);
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Booking Confirmation', 'hb-booking'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'hb-booking'), esc_html($booking->customer_name)); ?></p>
                    <p><?php esc_html_e('Thank you for your booking! Here are the details:', 'hb-booking'); ?></p>

                    <div class="booking-details">
                        <h3><?php esc_html_e('Booking Details', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Date:', 'hb-booking'); ?></strong> <?php echo esc_html($date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'hb-booking'); ?></strong> <?php echo esc_html($time); ?></p>
                        <?php if ($booking->business_status): ?>
                            <p><strong><?php esc_html_e('Business Status:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->business_status); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->target_country): ?>
                            <p><strong><?php esc_html_e('Target Country:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->target_country); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->notes): ?>
                            <p><strong><?php esc_html_e('Notes:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->notes); ?></p>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Status:', 'hb-booking'); ?></strong>
                            <span style="color: #f39c12;"><?php echo esc_html(ucfirst($booking->status)); ?></span>
                        </p>
                    </div>

                    <p><?php esc_html_e('We will confirm your booking shortly. If you have any questions, please don\'t hesitate to contact us.', 'hb-booking'); ?></p>

                    <p style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo esc_url(home_url()); ?>" class="button">
                            <?php esc_html_e('Visit Our Website', 'hb-booking'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; <?php echo esc_html(date('Y') . ' ' . get_bloginfo('name')); ?></p>
                    <p><?php esc_html_e('This is an automated message, please do not reply directly to this email.', 'hb-booking'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get admin notification template
     */
    private function getAdminNotificationTemplate(object $booking): string
    {
        $date = $this->dateConverter->formatDate($booking->booking_date);
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
                .button { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('New Booking Received', 'hb-booking'); ?></h1>
                </div>
                <div class="content">
                    <p><?php esc_html_e('A new booking has been submitted:', 'hb-booking'); ?></p>

                    <div class="booking-details">
                        <h3><?php esc_html_e('Customer Information', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Name:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->customer_name); ?></p>
                        <p><strong><?php esc_html_e('Email:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->customer_email); ?></p>
                        <p><strong><?php esc_html_e('Phone:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->customer_phone); ?></p>

                        <h3><?php esc_html_e('Booking Details', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Date:', 'hb-booking'); ?></strong> <?php echo esc_html($date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'hb-booking'); ?></strong> <?php echo esc_html($time); ?></p>

                        <h3><?php esc_html_e('Business Information', 'hb-booking'); ?></h3>
                        <?php if ($booking->business_status): ?>
                            <p><strong><?php esc_html_e('Business Status:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->business_status); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->target_country): ?>
                            <p><strong><?php esc_html_e('Target Country:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->target_country); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->team_size): ?>
                            <p><strong><?php esc_html_e('Team Size:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->team_size); ?> <?php echo $booking->team_size == 1 ? esc_html__('person', 'hb-booking') : esc_html__('people', 'hb-booking'); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->services): ?>
                            <p><strong><?php esc_html_e('Services:', 'hb-booking'); ?></strong> <?php echo nl2br(esc_html($booking->services)); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->notes): ?>
                            <p><strong><?php esc_html_e('Notes:', 'hb-booking'); ?></strong> <?php echo nl2br(esc_html($booking->notes)); ?></p>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Booking ID:', 'hb-booking'); ?></strong> #<?php echo esc_html($booking->id); ?></p>
                    </div>

                    <p style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hb-booking')); ?>" class="button">
                            <?php esc_html_e('View in Dashboard', 'hb-booking'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send 30-minute reminder email to customer
     */
    public function send30MinReminderEmail(object $booking): bool
    {
        if (!get_option('hb_booking_enable_notifications', true)) {
            return false;
        }

        $to = $booking->customer_email;
        $subject = sprintf(
            __('[%s] Reminder: Your Appointment in 30 Minutes', 'hb-booking'),
            get_bloginfo('name')
        );

        $message = $this->get30MinReminderTemplate($booking);

        $headers = [
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send 24-hour reminder email to customer
     */
    public function send24HourReminderEmail(object $booking): bool
    {
        if (!get_option('hb_booking_enable_notifications', true)) {
            return false;
        }

        $to = $booking->customer_email;
        $subject = sprintf(
            __('[%s] Reminder: Your Appointment Tomorrow', 'hb-booking'),
            get_bloginfo('name')
        );

        $message = $this->get24HourReminderTemplate($booking);

        $headers = [
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get status update template
     */
    private function getStatusUpdateTemplate(object $booking): string
    {
        $date = $this->dateConverter->formatDate($booking->booking_date);
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        $status_colors = [
            'pending' => '#f39c12',
            'confirmed' => '#27ae60',
            'cancelled' => '#e74c3c',
            'completed' => '#95a5a6',
        ];

        $color = $status_colors[$booking->status] ?? '#3498db';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: <?php echo esc_attr($color); ?>; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid <?php echo esc_attr($color); ?>; }
                .status-badge { display: inline-block; padding: 5px 15px; background: <?php echo esc_attr($color); ?>; color: white; border-radius: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Booking Status Update', 'hb-booking'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'hb-booking'), esc_html($booking->customer_name)); ?></p>
                    <p><?php esc_html_e('Your booking status has been updated:', 'hb-booking'); ?></p>

                    <div class="booking-details">
                        <p style="text-align: center; font-size: 18px;">
                            <strong><?php esc_html_e('New Status:', 'hb-booking'); ?></strong>
                            <span class="status-badge"><?php echo esc_html(ucfirst($booking->status)); ?></span>
                        </p>

                        <h3><?php esc_html_e('Booking Details', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Date:', 'hb-booking'); ?></strong> <?php echo esc_html($date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'hb-booking'); ?></strong> <?php echo esc_html($time); ?></p>
                        <?php if ($booking->business_status): ?>
                            <p><strong><?php esc_html_e('Business Status:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->business_status); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->target_country): ?>
                            <p><strong><?php esc_html_e('Target Country:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->target_country); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($booking->status === 'confirmed'): ?>
                        <p><?php esc_html_e('Great news! Your booking has been confirmed. We look forward to seeing you!', 'hb-booking'); ?></p>
                    <?php elseif ($booking->status === 'cancelled'): ?>
                        <p><?php esc_html_e('Your booking has been cancelled. If you have any questions, please contact us.', 'hb-booking'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get 30-minute reminder email template
     */
    private function get30MinReminderTemplate(object $booking): string
    {
        $date = $this->dateConverter->formatDate($booking->booking_date);
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #FF9800; }
                .alert-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('⏰ Appointment Reminder', 'hb-booking'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'hb-booking'), esc_html($booking->customer_name)); ?></p>

                    <div class="alert-box">
                        <strong><?php esc_html_e('Your appointment is starting in 30 minutes!', 'hb-booking'); ?></strong>
                    </div>

                    <div class="booking-details">
                        <h3><?php esc_html_e('Appointment Details', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Date:', 'hb-booking'); ?></strong> <?php echo esc_html($date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'hb-booking'); ?></strong> <?php echo esc_html($time); ?></p>
                        <?php if ($booking->business_status): ?>
                            <p><strong><?php esc_html_e('Business Status:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->business_status); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->target_country): ?>
                            <p><strong><?php esc_html_e('Target Country:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->target_country); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->notes): ?>
                            <p><strong><?php esc_html_e('Notes:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->notes); ?></p>
                        <?php endif; ?>
                    </div>

                    <p><?php esc_html_e('Please make sure you are ready for your appointment. If you need to reschedule or cancel, please contact us as soon as possible.', 'hb-booking'); ?></p>
                </div>
                <div class="footer">
                    <p>&copy; <?php echo esc_html(date('Y') . ' ' . get_bloginfo('name')); ?></p>
                    <p><?php esc_html_e('This is an automated reminder, please do not reply directly to this email.', 'hb-booking'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get 24-hour reminder email template
     */
    private function get24HourReminderTemplate(object $booking): string
    {
        $date = $this->dateConverter->formatDate($booking->booking_date);
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
                .alert-box { background: #d1ecf1; border: 1px solid #0c5460; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('📅 Appointment Reminder', 'hb-booking'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'hb-booking'), esc_html($booking->customer_name)); ?></p>

                    <div class="alert-box">
                        <strong><?php esc_html_e('Your appointment is scheduled for tomorrow!', 'hb-booking'); ?></strong>
                    </div>

                    <div class="booking-details">
                        <h3><?php esc_html_e('Appointment Details', 'hb-booking'); ?></h3>
                        <p><strong><?php esc_html_e('Date:', 'hb-booking'); ?></strong> <?php echo esc_html($date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'hb-booking'); ?></strong> <?php echo esc_html($time); ?></p>
                        <?php if ($booking->business_status): ?>
                            <p><strong><?php esc_html_e('Business Status:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->business_status); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->target_country): ?>
                            <p><strong><?php esc_html_e('Target Country:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->target_country); ?></p>
                        <?php endif; ?>
                        <?php if ($booking->notes): ?>
                            <p><strong><?php esc_html_e('Notes:', 'hb-booking'); ?></strong> <?php echo esc_html($booking->notes); ?></p>
                        <?php endif; ?>
                    </div>

                    <p><?php esc_html_e('We look forward to meeting with you tomorrow. If you need to reschedule or cancel, please contact us as soon as possible.', 'hb-booking'); ?></p>
                </div>
                <div class="footer">
                    <p>&copy; <?php echo esc_html(date('Y') . ' ' . get_bloginfo('name')); ?></p>
                    <p><?php esc_html_e('This is an automated reminder, please do not reply directly to this email.', 'hb-booking'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
