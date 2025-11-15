/**
 * Admin JavaScript for HB Booking Plugin
 */

(function($) {
    'use strict';

    /**
     * Admin Booking Manager
     */
    class AdminBookingManager {
        constructor() {
            this.init();
        }

        init() {
            this.initDatepicker();
            this.bindEvents();
        }

        initDatepicker() {
            if (typeof hbBookingAdmin === 'undefined') {
                return;
            }

            // Check if Persian datepicker is needed
            if (typeof hbBookingAdmin.dateConfig !== 'undefined' &&
                hbBookingAdmin.dateConfig.calendar_type === 'jalali') {

                const $elements = $('.hb-datepicker');

                // Initialize Persian datepicker
                if (typeof $.fn.pDatepicker !== 'undefined') {
                    try {
                        $elements.pDatepicker({
                            format: 'YYYY-MM-DD',
                            initialValue: false,
                            autoClose: true,
                            minDate: new Date().getTime(),
                            observer: true,
                            altFormat: 'YYYY-MM-DD',
                            calendarType: 'persian',
                            viewMode: 'day',
                            formatter: function(unix) {
                                // Force English numbers instead of Persian digits
                                const pDate = new persianDate(unix);
                                const year = pDate.year();
                                const month = String(pDate.month()).padStart(2, '0');
                                const day = String(pDate.date()).padStart(2, '0');
                                return `${year}-${month}-${day}`;
                            },
                            onSelect: function(unix) {
                                // Manually set the value with English numbers
                                const pDate = new persianDate(unix);
                                const year = pDate.year();
                                const month = String(pDate.month()).padStart(2, '0');
                                const day = String(pDate.date()).padStart(2, '0');
                                const dateStr = `${year}-${month}-${day}`;
                                $(this).val(dateStr).trigger('change');
                            }
                        });
                    } catch (error) {
                        // Silently handle datepicker initialization errors
                    }
                }
            } else {
                // Initialize jQuery UI datepicker for Gregorian
                if ($.fn.datepicker) {
                    $('.hb-datepicker').datepicker({
                        dateFormat: 'yy-mm-dd',
                        minDate: 0
                    });
                }
            }
        }

        bindEvents() {
            // Edit booking
            $(document).on('click', '.hb-edit-booking', (e) => {
                const bookingId = $(e.currentTarget).data('id');
                this.editBooking(bookingId);
            });

            // Delete booking
            $(document).on('click', '.hb-delete-booking', (e) => {
                const bookingId = $(e.currentTarget).data('id');
                this.deleteBooking(bookingId);
            });
        }

        async editBooking(bookingId) {
            // For now, redirect to edit page
            // In a full implementation, this would open a modal
            alert('Edit functionality - booking ID: ' + bookingId);
        }

        async deleteBooking(bookingId) {
            if (!confirm('Are you sure you want to delete this booking?')) {
                return;
            }

            try {
                const response = await fetch(`${hbBookingAdmin.restUrl}/bookings/${bookingId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': hbBookingAdmin.nonce
                    }
                });

                if (response.ok) {
                    location.reload();
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Failed to delete booking'));
                }
            } catch (error) {
                console.error('Error deleting booking:', error);
                alert('Error: Failed to delete booking');
            }
        }
    }

    /**
     * Calendar Manager
     */
    class CalendarManager {
        constructor() {
            this.calendarEl = document.getElementById('hb-booking-calendar');
            if (this.calendarEl) {
                this.initCalendar();
            }
        }

        initCalendar() {
            const eventsData = this.calendarEl.getAttribute('data-events');
            const calendarType = this.calendarEl.getAttribute('data-calendar-type');
            const isJalali = calendarType === 'jalali';
            let events = [];

            try {
                events = eventsData ? JSON.parse(eventsData) : [];
            } catch (error) {
                console.error('Error parsing calendar events:', error);
            }

            const calendarConfig = {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: events,
                eventClick: (info) => {
                    this.showEventDetails(info.event, isJalali);
                },
                height: 'auto',
                navLinks: true,
                editable: false,
                dayMaxEvents: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: false
                },
                buttonText: {
                    today: isJalali ? 'امروز' : 'Today',
                    month: isJalali ? 'ماه' : 'Month',
                    week: isJalali ? 'هفته' : 'Week',
                    day: isJalali ? 'روز' : 'Day',
                    list: isJalali ? 'لیست' : 'List'
                }
            };

            // Add Persian locale support if Jalali
            if (isJalali) {
                calendarConfig.locale = 'fa';
                calendarConfig.direction = 'rtl';
                calendarConfig.firstDay = 6; // Start week on Saturday (شنبه) for Persian calendar

                // Custom day header names in Persian
                calendarConfig.dayHeaderContent = function(arg) {
                    const persianDayNames = ['یک شنبه', 'دو شنبه', 'سه شنبه', 'چهار شنبه', 'پنج شنبه', 'جمعه', 'شنبه']; // Yekshanbeh to Shanbeh
                    return persianDayNames[arg.dow];
                };

                // Override the title formatter to show Jalali dates
                const self = this;
                calendarConfig.viewDidMount = function(info) {
                    const titleEl = info.el.querySelector('.fc-toolbar-title');
                    if (titleEl && info.view.currentStart) {
                        const jalaliTitle = self.getJalaliMonthYear(info.view.currentStart);
                        titleEl.textContent = jalaliTitle;
                    }
                };
            }

            const calendar = new FullCalendar.Calendar(this.calendarEl, calendarConfig);
            calendar.render();
        }

        getJalaliMonthYear(gregorianDate) {
            const jalaliMonths = [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];

            // Ensure we have a Date object
            if (!(gregorianDate instanceof Date)) {
                gregorianDate = new Date(gregorianDate);
            }

            // Use Persian date library if available
            if (typeof persianDate !== 'undefined') {
                try {
                    // Convert Date object to Unix timestamp (milliseconds)
                    const timestamp = gregorianDate.getTime();
                    const pDate = new persianDate(timestamp);
                    const month = pDate.month(); // 1-12
                    const year = pDate.year();
                    return `${jalaliMonths[month - 1]} ${year}`;
                } catch (error) {
                    console.error('Error converting to Jalali:', error);
                    // Fall through to fallback method
                }
            }

            // Fallback: approximate conversion
            const year = gregorianDate.getFullYear();
            const month = gregorianDate.getMonth();
            const jalaliYear = year - 621;

            return `${jalaliMonths[month]} ${jalaliYear}`;
        }

        showEventDetails(event, isJalali) {
            const props = event.extendedProps;
            const dateLabel = isJalali ? 'تاریخ' : 'Date';
            const dateValue = isJalali && props.jalali_date ?
                props.jalali_date + ' ' + event.start.toLocaleTimeString('fa-IR', {hour: '2-digit', minute: '2-digit'}) :
                event.start.toLocaleString();

            const details = `
            ${isJalali ? 'جزئیات رزرو' : 'Booking Details'}:
            ${isJalali ? 'نام' : 'Name'}: ${event.title}
            ${isJalali ? 'ایمیل' : 'Email'}: ${props.email || 'N/A'}
            ${isJalali ? 'تلفن' : 'Phone'}: ${props.phone || 'N/A'}
            ${isJalali ? 'وضعیت' : 'Status'}: ${props.status}
            ${dateLabel}: ${dateValue}
            `.trim();

            alert(details);
        }
    }

    /**
     * Settings Manager
     */
    class SettingsManager {
        constructor() {
            this.init();
        }

        init() {
            this.toggleGoogleCalendarSettings();
            this.bindEvents();
        }

        bindEvents() {
            $('#hb_booking_calendar_integration').on('change', () => {
                this.toggleGoogleCalendarSettings();
            });
        }

        toggleGoogleCalendarSettings() {
            const calendarType = $('#hb_booking_calendar_integration').val();
            const $googleSettings = $('#google-calendar-settings, .google-calendar-settings');
            const $googleDescription = $('#google-calendar-settings').next('p.description');

            if (calendarType === 'google') {
                $googleSettings.show();
                $googleDescription.show();
            } else {
                $googleSettings.hide();
                $googleDescription.hide();
            }
        }
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        new AdminBookingManager();
        new CalendarManager();
        new SettingsManager();
    });

})(jQuery);
