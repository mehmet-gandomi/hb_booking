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
                            نام و نام خانوادگی <span class="required">*</span>
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
                            آدرس ایمیل <span class="required">*</span>
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
                            <?php esc_html_e('شماره تماس', 'hb-booking'); ?> <span class="required">*</span>
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
                </div>

                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label for="hb-business-status">
                            وضعیت فعلی کسب و کار <span class="required">*</span>
                        </label>
                        <select id="hb-business-status" name="business_status" class="hb-form-control" required aria-required="true">
                            <option value="">انتخاب کنید</option>
                            <option value="ایده اولیه">ایده اولیه</option>
                            <option value="استارتاپ در مرحله MVP">استارتاپ در مرحله MVP</option>
                            <option value="در حال جذب سرمایه">در حال جذب سرمایه</option>
                            <option value="در حال فعالیت بین‌المللی">در حال فعالیت بین‌المللی</option>
                            <option value="سایر">سایر</option>
                        </select>
                    </div>

                    <div class="hb-form-group">
                        <label for="hb-target-country">
                            کشور مقصد مورد نظر <span class="required">*</span>
                        </label>
                        <select id="hb-target-country" name="target_country" class="hb-form-control" required aria-required="true">
                            <option value="">انتخاب کنید</option>
                            <option value="اسپانیا">اسپانیا</option>
                            <option value="کانادا">کانادا</option>
                            <option value="انگلستان">انگلستان</option>
                            <option value="لتونی">لتونی</option>
                            <option value="لیتوانی">لیتوانی</option>
                            <option value="استونی">استونی</option>
                            <option value="پرتغال">پرتغال</option>
                            <option value="هلند">هلند</option>
                            <option value="فنلاند">فنلاند</option>
                            <option value="فرانسه">فرانسه</option>
                            <option value="ترکیه">ترکیه</option>
                            <option value="امارات">امارات</option>
                        </select>
                    </div>
                </div>

                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label for="hb-booking-date">
                            تاریخ مورد نظر <span class="required">*</span>
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
                            ساعت مورد نظر (به وقت تهران) <span class="required">*</span>
                        </label>
                        <select id="hb-booking-time" name="booking_time" class="hb-form-control" required aria-required="true">
                            <option value="">انتخاب ساعت</option>
                            <?php echo $this->getTimeSlotOptions(); ?>
                        </select>
                        <small class="hb-timezone-notice">⏰ تمام ساعات به وقت تهران (UTC+3:30) می‌باشد</small>
                    </div>
                </div>

                <div class="hb-form-group">
                    <label for="hb-team-size">
                        تیم شما از چند نفر تشکیل شده؟ <span class="required">*</span>
                    </label>
                    <select id="hb-team-size" name="team_size" class="hb-form-control" required aria-required="true">
                        <option value="">انتخاب کنید</option>
                        <option value="1">۱ نفر (فقط خودم)</option>
                        <option value="2">۲ نفر</option>
                        <option value="3">۳ نفر</option>
                        <option value="4">۴ نفر</option>
                        <option value="5">۵ نفر</option>
                        <option value="6">۶-۱۰ نفر</option>
                        <option value="11">بیش از ۱۰ نفر</option>
                    </select>
                </div>

                <div class="hb-form-group">
                    <label for="hb-services">
                        چه خدماتی نیاز دارید؟ <span class="required">*</span>
                    </label>
                    <select id="hb-services" name="services[]" class="hb-form-control" multiple required aria-required="true">
                        <option value="تهیه اسناد بیزنس">تهیه اسناد بیزنس</option>
                        <option value="جذب سرمایه">جذب سرمایه</option>
                        <option value="خدمات رلوکیشن">خدمات رلوکیشن</option>
                        <option value="مشاوره ایده و کسب‌وکار">مشاوره ایده و کسب‌وکار</option>
                        <option value="ساخت MVP (محصول اولیه)">ساخت MVP (محصول اولیه)</option>
                        <option value="اپلیکیشن ویزا">اپلیکیشن ویزا</option>
                        <option value="خدمات جامع ویزای استارتاپ">خدمات جامع ویزای استارتاپ</option>
                        <option value="بازسازی و نگهداری استارتاپ">بازسازی و نگهداری استارتاپ</option>
                    </select>
                    <small class="hb-field-help">می‌توانید چند خدمت را همزمان انتخاب کنید</small>
                </div>

                <div class="hb-form-group">
                    <label for="hb-notes">
                        یادداشت‌های اضافی
                    </label>
                    <textarea
                        id="hb-notes"
                        name="notes"
                        class="hb-form-control"
                        rows="4"
                        placeholder="هرگونه درخواست یا اطلاعات خاص..."
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
