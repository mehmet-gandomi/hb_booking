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
            this.isJalali = typeof hbBookingAdmin !== 'undefined' &&
                           hbBookingAdmin.dateConfig &&
                           hbBookingAdmin.dateConfig.calendar_type === 'jalali';
            this.init();
        }

        init() {
            this.initDatepicker();
            this.bindEvents();
        }

        /**
         * Convert Gregorian date to Jalali if needed
         */
        formatDate(gregorianDate) {
            if (!gregorianDate) return '';

            if (this.isJalali && typeof persianDate !== 'undefined') {
                try {
                    const parts = gregorianDate.split('-');
                    const year = parseInt(parts[0]);
                    const month = parseInt(parts[1]);
                    const day = parseInt(parts[2]);
                    const date = new Date(year, month - 1, day);
                    const pDate = new persianDate(date);
                    return pDate.format('YYYY/MM/DD');
                } catch (error) {
                    console.error('Error converting date:', error);
                    return gregorianDate;
                }
            }

            return gregorianDate;
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
            // View booking
            $(document).on('click', '.hb-view-booking', (e) => {
                const bookingId = $(e.currentTarget).data('id');
                this.viewBooking(bookingId);
            });

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

            // Close modal
            $(document).on('click', '.hb-modal-close, .hb-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });

            // Save status change
            $(document).on('click', '.hb-save-status', (e) => {
                const bookingId = $(e.currentTarget).data('id');
                this.saveStatus(bookingId);
            });
        }

        async viewBooking(bookingId) {
            try {
                const response = await fetch(`${hbBookingAdmin.restUrl}/bookings/${bookingId}`, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': hbBookingAdmin.nonce
                    }
                });

                if (response.ok) {
                    const booking = await response.json();
                    this.showBookingModal(booking);
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Failed to load booking'));
                }
            } catch (error) {
                console.error('Error loading booking:', error);
                alert('Error: Failed to load booking');
            }
        }

        showBookingModal(booking) {
            // Create modal HTML
            const modalHtml = `
                <div class="hb-modal-overlay">
                    <div class="hb-modal">
                        <div class="hb-modal-header">
                            <h2>Booking Details #${booking.id}</h2>
                            <button class="hb-modal-close">&times;</button>
                        </div>
                        <div class="hb-modal-body">
                            <div class="hb-booking-details">
                                <h3 style="margin: 0 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Customer Information</h3>
                                <div class="hb-detail-row">
                                    <strong>Customer Name:</strong>
                                    <span>${this.escapeHtml(booking.customer_name)}</span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Email:</strong>
                                    <span><a href="mailto:${this.escapeHtml(booking.customer_email)}">${this.escapeHtml(booking.customer_email)}</a></span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Phone:</strong>
                                    <span><a href="tel:${this.escapeHtml(booking.customer_phone)}">${this.escapeHtml(booking.customer_phone)}</a></span>
                                </div>

                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Booking Information</h3>
                                <div class="hb-detail-row">
                                    <strong>Date:</strong>
                                    <span>${this.escapeHtml(this.formatDate(booking.booking_date))}${this.isJalali ? ' <small style="color: #888;">(Jalali)</small>' : ''}</span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Time:</strong>
                                    <span>${this.escapeHtml(booking.booking_time)}</span>
                                </div>
                                ${booking.business_status ? `
                                <div class="hb-detail-row">
                                    <strong>Business Status:</strong>
                                    <span>${this.escapeHtml(booking.business_status)}</span>
                                </div>
                                ` : ''}
                                ${booking.target_country ? `
                                <div class="hb-detail-row">
                                    <strong>Target Country:</strong>
                                    <span>${this.escapeHtml(booking.target_country)}</span>
                                </div>
                                ` : ''}

                                ${booking.team_size || booking.services ? `
                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Additional Details</h3>
                                ` : ''}
                                ${booking.team_size ? `
                                <div class="hb-detail-row">
                                    <strong>Team Size:</strong>
                                    <span>${this.escapeHtml(booking.team_size.toString())} ${booking.team_size == 1 ? 'person' : 'people'}</span>
                                </div>
                                ` : ''}
                                ${booking.services ? `
                                <div class="hb-detail-row">
                                    <strong>Services:</strong>
                                    <span style="white-space: pre-wrap;">${this.escapeHtml(booking.services)}</span>
                                </div>
                                ` : ''}
                                ${booking.notes ? `
                                <div class="hb-detail-row">
                                    <strong>Notes:</strong>
                                    <span style="white-space: pre-wrap;">${this.escapeHtml(booking.notes)}</span>
                                </div>
                                ` : ''}

                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Status & Metadata</h3>
                                <div class="hb-detail-row hb-status-row">
                                    <strong>Status:</strong>
                                    <select class="hb-status-select" data-original="${this.escapeHtml(booking.status)}">
                                        <option value="pending" ${booking.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="confirmed" ${booking.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                        <option value="cancelled" ${booking.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                        <option value="completed" ${booking.status === 'completed' ? 'selected' : ''}>Completed</option>
                                    </select>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Created:</strong>
                                    <span>${this.escapeHtml(booking.created_at)}</span>
                                </div>
                                ${booking.updated_at && booking.updated_at !== booking.created_at ? `
                                <div class="hb-detail-row">
                                    <strong>Last Updated:</strong>
                                    <span>${this.escapeHtml(booking.updated_at)}</span>
                                </div>
                                ` : ''}
                                ${booking.google_event_id ? `
                                <div class="hb-detail-row">
                                    <strong>Google Event ID:</strong>
                                    <span style="font-family: monospace; font-size: 11px;">${this.escapeHtml(booking.google_event_id)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="hb-modal-footer">
                            <button class="button button-primary hb-save-status" data-id="${booking.id}">Update Status</button>
                            <button class="button hb-modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to body
            $('body').append(modalHtml);
        }

        closeModal() {
            $('.hb-modal-overlay').remove();
        }

        async saveStatus(bookingId) {
            const newStatus = $('.hb-status-select').val();
            const originalStatus = $('.hb-status-select').data('original');

            if (newStatus === originalStatus) {
                alert('No changes to save');
                return;
            }

            try {
                const response = await fetch(`${hbBookingAdmin.restUrl}/bookings/${bookingId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hbBookingAdmin.nonce
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                });

                if (response.ok) {
                    this.closeModal();
                    location.reload();
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Failed to update status'));
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('Error: Failed to update status');
            }
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
            this.isJalali = typeof hbBookingAdmin !== 'undefined' &&
                           hbBookingAdmin.dateConfig &&
                           hbBookingAdmin.dateConfig.calendar_type === 'jalali';
            if (this.calendarEl) {
                this.initCalendar();
                this.bindEvents();
            }
        }

        /**
         * Convert Gregorian date to Jalali if needed
         */
        formatDate(gregorianDate) {
            if (!gregorianDate) return '';

            if (this.isJalali && typeof persianDate !== 'undefined') {
                try {
                    const parts = gregorianDate.split('-');
                    const year = parseInt(parts[0]);
                    const month = parseInt(parts[1]);
                    const day = parseInt(parts[2]);
                    const date = new Date(year, month - 1, day);
                    const pDate = new persianDate(date);
                    return pDate.format('YYYY/MM/DD');
                } catch (error) {
                    console.error('Error converting date:', error);
                    return gregorianDate;
                }
            }

            return gregorianDate;
        }

        bindEvents() {
            // Close modal
            $(document).on('click', '.hb-modal-close, .hb-modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });

            // Save status change from calendar modal
            $(document).on('click', '.hb-save-status', (e) => {
                const bookingId = $(e.currentTarget).data('id');
                this.saveStatus(bookingId);
            });
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
                    this.handleEventClick(info.event.id);
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

        async handleEventClick(bookingId) {
            try {
                const response = await fetch(`${hbBookingAdmin.restUrl}/bookings/${bookingId}`, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': hbBookingAdmin.nonce
                    }
                });

                if (response.ok) {
                    const booking = await response.json();
                    this.showBookingModal(booking);
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Failed to load booking'));
                }
            } catch (error) {
                console.error('Error loading booking:', error);
                alert('Error: Failed to load booking');
            }
        }

        showBookingModal(booking) {
            // Create modal HTML - same as AdminBookingManager
            const modalHtml = `
                <div class="hb-modal-overlay">
                    <div class="hb-modal">
                        <div class="hb-modal-header">
                            <h2>Booking Details #${booking.id}</h2>
                            <button class="hb-modal-close">&times;</button>
                        </div>
                        <div class="hb-modal-body">
                            <div class="hb-booking-details">
                                <h3 style="margin: 0 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Customer Information</h3>
                                <div class="hb-detail-row">
                                    <strong>Customer Name:</strong>
                                    <span>${this.escapeHtml(booking.customer_name)}</span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Email:</strong>
                                    <span><a href="mailto:${this.escapeHtml(booking.customer_email)}">${this.escapeHtml(booking.customer_email)}</a></span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Phone:</strong>
                                    <span><a href="tel:${this.escapeHtml(booking.customer_phone)}">${this.escapeHtml(booking.customer_phone)}</a></span>
                                </div>

                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Booking Information</h3>
                                <div class="hb-detail-row">
                                    <strong>Date:</strong>
                                    <span>${this.escapeHtml(this.formatDate(booking.booking_date))}${this.isJalali ? ' <small style="color: #888;">(Jalali)</small>' : ''}</span>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Time:</strong>
                                    <span>${this.escapeHtml(booking.booking_time)}</span>
                                </div>
                                ${booking.business_status ? `
                                <div class="hb-detail-row">
                                    <strong>Business Status:</strong>
                                    <span>${this.escapeHtml(booking.business_status)}</span>
                                </div>
                                ` : ''}
                                ${booking.target_country ? `
                                <div class="hb-detail-row">
                                    <strong>Target Country:</strong>
                                    <span>${this.escapeHtml(booking.target_country)}</span>
                                </div>
                                ` : ''}

                                ${booking.team_size || booking.services ? `
                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Additional Details</h3>
                                ` : ''}
                                ${booking.team_size ? `
                                <div class="hb-detail-row">
                                    <strong>Team Size:</strong>
                                    <span>${this.escapeHtml(booking.team_size.toString())} ${booking.team_size == 1 ? 'person' : 'people'}</span>
                                </div>
                                ` : ''}
                                ${booking.services ? `
                                <div class="hb-detail-row">
                                    <strong>Services:</strong>
                                    <span style="white-space: pre-wrap;">${this.escapeHtml(booking.services)}</span>
                                </div>
                                ` : ''}
                                ${booking.notes ? `
                                <div class="hb-detail-row">
                                    <strong>Notes:</strong>
                                    <span style="white-space: pre-wrap;">${this.escapeHtml(booking.notes)}</span>
                                </div>
                                ` : ''}

                                <h3 style="margin: 24px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid #2271b1; color: #2271b1;">Status & Metadata</h3>
                                <div class="hb-detail-row hb-status-row">
                                    <strong>Status:</strong>
                                    <select class="hb-status-select" data-original="${this.escapeHtml(booking.status)}">
                                        <option value="pending" ${booking.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="confirmed" ${booking.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                        <option value="cancelled" ${booking.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                        <option value="completed" ${booking.status === 'completed' ? 'selected' : ''}>Completed</option>
                                    </select>
                                </div>
                                <div class="hb-detail-row">
                                    <strong>Created:</strong>
                                    <span>${this.escapeHtml(booking.created_at)}</span>
                                </div>
                                ${booking.updated_at && booking.updated_at !== booking.created_at ? `
                                <div class="hb-detail-row">
                                    <strong>Last Updated:</strong>
                                    <span>${this.escapeHtml(booking.updated_at)}</span>
                                </div>
                                ` : ''}
                                ${booking.google_event_id ? `
                                <div class="hb-detail-row">
                                    <strong>Google Event ID:</strong>
                                    <span style="font-family: monospace; font-size: 11px;">${this.escapeHtml(booking.google_event_id)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="hb-modal-footer">
                            <button class="button button-primary hb-save-status" data-id="${booking.id}">Update Status</button>
                            <button class="button hb-modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to body
            $('body').append(modalHtml);
        }

        closeModal() {
            $('.hb-modal-overlay').remove();
        }

        async saveStatus(bookingId) {
            const newStatus = $('.hb-status-select').val();
            const originalStatus = $('.hb-status-select').data('original');

            if (newStatus === originalStatus) {
                alert('No changes to save');
                return;
            }

            try {
                const response = await fetch(`${hbBookingAdmin.restUrl}/bookings/${bookingId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hbBookingAdmin.nonce
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                });

                if (response.ok) {
                    this.closeModal();
                    location.reload();
                } else {
                    const error = await response.json();
                    alert('Error: ' + (error.message || 'Failed to update status'));
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('Error: Failed to update status');
            }
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
