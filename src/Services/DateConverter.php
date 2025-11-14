<?php
/**
 * Date Converter Service
 * Handles conversion between Gregorian and Jalali (Persian) calendars
 */

namespace HB\Booking\Services;

use Morilog\Jalali\Jalalian;

class DateConverter
{
    private static ?DateConverter $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): DateConverter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get current calendar type setting
     */
    public function getCalendarType(): string
    {
        return get_option('hb_booking_calendar_type', 'gregorian');
    }

    /**
     * Check if Jalali calendar is enabled
     */
    public function isJalali(): bool
    {
        return $this->getCalendarType() === 'jalali';
    }

    /**
     * Convert Jalali date to Gregorian (for database storage)
     *
     * @param string $jalali_date Date in YYYY-MM-DD format (Jalali)
     * @return string Date in YYYY-MM-DD format (Gregorian)
     */
    public function toGregorian(string $jalali_date): string
    {
        try {
            // Parse Jalali date (format: YYYY-MM-DD or YYYY/MM/DD)
            $jalali_date = str_replace('/', '-', $jalali_date);
            $parts = explode('-', $jalali_date);

            if (count($parts) !== 3) {
                throw new \Exception('Invalid date format');
            }

            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];

            // Create Jalalian instance and convert to Gregorian
            $jalalian = Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));
            return $jalalian->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            error_log('HB Booking: Date conversion error (Jalali to Gregorian): ' . $e->getMessage());
            return $jalali_date; // Return original if conversion fails
        }
    }

    /**
     * Convert Gregorian date to Jalali (for display)
     *
     * @param string $gregorian_date Date in YYYY-MM-DD format (Gregorian)
     * @param string $format Output format (default: Y-m-d)
     * @return string Date in specified format (Jalali)
     */
    public function toJalali(string $gregorian_date, string $format = 'Y-m-d'): string
    {
        try {
            // Create Jalalian instance from Gregorian date
            $jalalian = Jalalian::fromDateTime(new \DateTime($gregorian_date));
            return $jalalian->format($format);
        } catch (\Exception $e) {
            error_log('HB Booking: Date conversion error (Gregorian to Jalali): ' . $e->getMessage());
            return $gregorian_date; // Return original if conversion fails
        }
    }

    /**
     * Format date for display based on calendar type setting
     *
     * @param string $gregorian_date Date in YYYY-MM-DD format (from database)
     * @param string $format Optional custom format
     * @return string Formatted date string
     */
    public function formatDate(string $gregorian_date, ?string $format = null): string
    {
        if ($this->isJalali()) {
            // Default Jalali format: Y/m/d (e.g., 1403/08/15)
            $jalali_format = $format ?? 'Y/m/d';
            return $this->toJalali($gregorian_date, $jalali_format);
        } else {
            // Use WordPress date format for Gregorian
            $wp_format = $format ?? get_option('date_format', 'Y-m-d');
            return date_i18n($wp_format, strtotime($gregorian_date));
        }
    }

    /**
     * Prepare date for database storage
     * Converts from display format (Jalali or Gregorian) to Gregorian YYYY-MM-DD
     *
     * @param string $date Date from user input
     * @return string Date in YYYY-MM-DD format (Gregorian) for database
     */
    public function prepareForDatabase(string $date): string
    {
        if ($this->isJalali()) {
            return $this->toGregorian($date);
        } else {
            // Already Gregorian, just ensure proper format
            return date('Y-m-d', strtotime($date));
        }
    }

    /**
     * Validate date format based on calendar type
     *
     * @param string $date Date string to validate
     * @return bool True if valid
     */
    public function isValidDate(string $date): bool
    {
        try {
            $date = str_replace('/', '-', $date);
            $parts = explode('-', $date);

            if (count($parts) !== 3) {
                return false;
            }

            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];

            if ($this->isJalali()) {
                // Validate Jalali date ranges
                if ($year < 1300 || $year > 1500) {
                    return false;
                }
                if ($month < 1 || $month > 12) {
                    return false;
                }
                if ($day < 1 || $day > 31) {
                    return false;
                }

                // Try to convert to verify it's a real date
                $this->toGregorian($date);
                return true;
            } else {
                // Validate Gregorian date
                return checkdate($month, $day, $year);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get today's date in current calendar format
     *
     * @param string $format Optional format
     * @return string Today's date
     */
    public function today(?string $format = null): string
    {
        $today_gregorian = date('Y-m-d');

        if ($this->isJalali()) {
            $jalali_format = $format ?? 'Y-m-d';
            return $this->toJalali($today_gregorian, $jalali_format);
        } else {
            $gregorian_format = $format ?? 'Y-m-d';
            return date($gregorian_format);
        }
    }

    /**
     * Get month name in current calendar
     *
     * @param int $month Month number (1-12)
     * @return string Month name
     */
    public function getMonthName(int $month): string
    {
        if ($this->isJalali()) {
            $jalali_months = [
                1 => 'فروردین',
                2 => 'اردیبهشت',
                3 => 'خرداد',
                4 => 'تیر',
                5 => 'مرداد',
                6 => 'شهریور',
                7 => 'مهر',
                8 => 'آبان',
                9 => 'آذر',
                10 => 'دی',
                11 => 'بهمن',
                12 => 'اسفند',
            ];
            return $jalali_months[$month] ?? '';
        } else {
            return date_i18n('F', mktime(0, 0, 0, $month, 1));
        }
    }

    /**
     * Get JavaScript datepicker configuration
     *
     * @return array Configuration for datepicker
     */
    public function getDatepickerConfig(): array
    {
        if ($this->isJalali()) {
            return [
                'calendar_type' => 'jalali',
                'format' => 'YYYY-MM-DD',
                'separator' => '-',
                'locale' => 'fa',
                'today' => $this->today('Y-m-d'),
            ];
        } else {
            return [
                'calendar_type' => 'gregorian',
                'format' => 'yy-mm-dd',
                'today' => date('Y-m-d'),
            ];
        }
    }
}
