/**
 * Frontend JavaScript for HB Booking Plugin
 * Modern ES6+ implementation with best practices
 */

(function($) {
    'use strict';

    /**
     * Booking Form Handler
     */
    class BookingForm {
        constructor(formElement) {
            this.form = $(formElement);
            this.messages = this.form.find('.hb-form-messages');
            this.submitButton = this.form.find('button[type="submit"]');

            this.init();
        }

        init() {
            this.form.on('submit', (e) => this.handleSubmit(e));
            this.form.find('#hb-booking-date, #hb-booking-time').on('change', () => this.checkAvailability());
        }

        async handleSubmit(e) {
            e.preventDefault();

            if (!this.validateForm()) {
                return;
            }

            this.setLoading(true);
            this.hideMessage();

            const formData = this.getFormData();

            try {
                const response = await this.submitBooking(formData);

                if (response.success) {
                    this.showMessage('success', response.message || hbBooking.i18n.success);
                    this.form[0].reset();
                } else {
                    throw new Error(response.message || hbBooking.i18n.error);
                }
            } catch (error) {
                this.showMessage('error', error.message || hbBooking.i18n.error);
            } finally {
                this.setLoading(false);
            }
        }

        validateForm() {
            // HTML5 validation
            if (!this.form[0].checkValidity()) {
                this.form[0].reportValidity();
                return false;
            }

            return true;
        }

        getFormData() {
            const data = {};
            const formArray = this.form.serializeArray();

            formArray.forEach(field => {
                if (field.name !== 'hb_booking_nonce' && field.name !== '_wp_http_referer') {
                    data[field.name] = field.value;
                }
            });

            return data;
        }

        async submitBooking(data) {
            const response = await fetch(`${hbBooking.restUrl}/bookings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': hbBooking.nonce
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Request failed');
            }

            return await response.json();
        }

        async checkAvailability() {
            const date = this.form.find('#hb-booking-date').val();
            const time = this.form.find('#hb-booking-time').val();

            if (!date || !time) {
                return;
            }

            try {
                const response = await fetch(
                    `${hbBooking.restUrl}/check-availability?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data.available) {
                    this.showMessage('error', 'This time slot is not available. Please choose another time.');
                } else {
                    this.hideMessage();
                }
            } catch (error) {
                console.error('Error checking availability:', error);
            }
        }

        setLoading(loading) {
            if (loading) {
                this.submitButton.prop('disabled', true).text(hbBooking.i18n.pleaseWait);
                this.form.addClass('loading');
            } else {
                this.submitButton.prop('disabled', false).text(this.submitButton.data('original-text') || 'Submit Booking');
                this.form.removeClass('loading');
            }
        }

        showMessage(type, message) {
            this.messages
                .removeClass('success error')
                .addClass(type + ' show')
                .html(`<p>${message}</p>`)
                .attr('role', 'alert');

            // Scroll to message
            $('html, body').animate({
                scrollTop: this.messages.offset().top - 100
            }, 500);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => this.hideMessage(), 5000);
            }
        }

        hideMessage() {
            this.messages.removeClass('show success error').empty();
        }
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        // Initialize all booking forms
        $('.hb-booking-form').each(function() {
            new BookingForm(this);
        });

        // Store original button text
        $('.hb-btn').each(function() {
            $(this).data('original-text', $(this).text());
        });
    });

})(jQuery);
