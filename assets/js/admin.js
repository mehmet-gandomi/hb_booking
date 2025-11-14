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
            console.log('🔧 HB Booking Admin: initDatepicker called');

            if (typeof hbBookingAdmin === 'undefined') {
                console.error('❌ HB Booking Admin: hbBookingAdmin object not found');
                return;
            }

            console.log('📊 HB Booking Admin: hbBookingAdmin object:', hbBookingAdmin);

            if (typeof hbBookingAdmin.dateConfig === 'undefined') {
                console.warn('⚠️ HB Booking Admin: dateConfig not found, using Gregorian');
            } else {
                console.log('📅 HB Booking Admin: dateConfig:', hbBookingAdmin.dateConfig);
                console.log('📅 HB Booking Admin: calendar_type:', hbBookingAdmin.dateConfig.calendar_type);
            }

            // Check if Persian datepicker is needed
            if (typeof hbBookingAdmin.dateConfig !== 'undefined' &&
                hbBookingAdmin.dateConfig.calendar_type === 'jalali') {
                console.log('✅ HB Booking Admin: Jalali calendar detected');

                const $elements = $('.hb-datepicker');
                console.log('🎯 HB Booking Admin: Found', $elements.length, 'elements with class .hb-datepicker');

                // Initialize Persian datepicker
                if (typeof $.fn.pDatepicker !== 'undefined') {
                    console.log('✅ HB Booking Admin: pDatepicker plugin available');
                    try {
                        $elements.pDatepicker({
                            format: 'YYYY-MM-DD',
                            initialValue: false,
                            autoClose: true,
                            minDate: new Date().getTime(),
                            observer: true,
                            altFormat: 'YYYY-MM-DD',
                            calendarType: 'persian',
                            viewMode: 'day'
                        });
                        console.log('✅ HB Booking Admin: Persian datepicker initialized successfully');
                    } catch (error) {
                        console.error('❌ HB Booking Admin: Error initializing pDatepicker:', error);
                    }
                } else {
                    console.error('❌ HB Booking Admin: pDatepicker plugin not loaded');
                    console.log('📦 Available jQuery plugins:', Object.keys($.fn).filter(k => k.toLowerCase().includes('date')));
                }
            } else {
                console.log('ℹ️ HB Booking Admin: Using Gregorian calendar');
                // Initialize jQuery UI datepicker for Gregorian
                if ($.fn.datepicker) {
                    console.log('✅ HB Booking Admin: jQuery UI datepicker available');
                    $('.hb-datepicker').datepicker({
                        dateFormat: 'yy-mm-dd',
                        minDate: 0
                    });
                    console.log('✅ HB Booking Admin: jQuery UI datepicker initialized');
                } else {
                    console.error('❌ HB Booking Admin: jQuery UI datepicker not available');
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
            let events = [];

            try {
                events = eventsData ? JSON.parse(eventsData) : [];
            } catch (error) {
                console.error('Error parsing calendar events:', error);
            }

            const calendar = new FullCalendar.Calendar(this.calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: events,
                eventClick: (info) => {
                    this.showEventDetails(info.event);
                },
                height: 'auto',
                navLinks: true,
                editable: false,
                dayMaxEvents: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: false
                }
            });

            calendar.render();
        }

        showEventDetails(event) {
            const props = event.extendedProps;
            const details = `
            Booking Details:
            Name: ${event.title}
            Email: ${props.email || 'N/A'}
            Phone: ${props.phone || 'N/A'}
            Status: ${props.status}
            Date/Time: ${event.start.toLocaleString()}
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
