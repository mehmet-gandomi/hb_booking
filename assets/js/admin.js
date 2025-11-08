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
            if ($.fn.datepicker) {
                $('.hb-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0
                });
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
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        new AdminBookingManager();
        new CalendarManager();
    });

})(jQuery);
