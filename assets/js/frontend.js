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
            this.wrapper = this.form.closest('.hb-booking-form-wrapper');
            this.messages = this.wrapper.find('.hb-form-messages');
            this.submitButton = this.form.find('button[type="submit"]');
            this.dateField = this.form.find('#hb-booking-date');
            this.timeField = this.form.find('#hb-booking-time');

            this.init();
        }

        init() {
            this.form.on('submit', (e) => this.handleSubmit(e));
            this.dateField.on('change', () => this.updateAvailableTimes());
            this.timeField.on('change', () => this.checkAvailability());
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
                    // Hide the form after successful submission
                    this.form.slideUp(300);
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
                    // Handle multi-select arrays (services[])
                    if (field.name.endsWith('[]')) {
                        const fieldName = field.name.slice(0, -2); // Remove '[]'
                        if (!data[fieldName]) {
                            data[fieldName] = [];
                        }
                        data[fieldName].push(field.value);
                    } else {
                        data[field.name] = field.value;
                    }
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

        async updateAvailableTimes() {
            const date = this.dateField.val();

            if (!date) {
                return;
            }

            try {
                const response = await fetch(
                    `${hbBooking.restUrl}/booked-times?date=${encodeURIComponent(date)}`
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                const bookedTimes = data.booked_times || [];

                // Enable all time options first
                this.timeField.find('option').prop('disabled', false);

                // Disable booked time slots
                bookedTimes.forEach(time => {
                    // Convert "17:00:00" to "17:00" to match form option values
                    const timeWithoutSeconds = time.substring(0, 5);
                    console.log(timeWithoutSeconds)
                    this.timeField.find(`option[value="${timeWithoutSeconds}"]`).prop('disabled', true);
                });

                // Reset the time field selection if currently selected time is booked
                const currentTime = this.timeField.val();
                if (currentTime) {
                    const bookedTimesWithoutSeconds = bookedTimes.map(t => t.substring(0, 5));
                    if (bookedTimesWithoutSeconds.includes(currentTime)) {
                        this.timeField.val('');
                    }
                }

                // Refresh the select field display
                this.timeField.trigger('change');

            } catch (error) {
                // Silently handle errors
            }
        }

        async checkAvailability() {
            const date = this.dateField.val();
            const time = this.timeField.val();

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
                // Silently handle availability check errors
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
        }

        hideMessage() {
            this.messages.removeClass('show success error').empty();
        }
    }

    /**
     * Initialize Persian Datepicker
     */
    function initPersianDatepicker() {

        if (hbBooking.dateConfig.calendar_type === 'jalali') {

            const $elements = $('.hb-datepicker-jalali');

            try {
                $elements.pDatepicker({
                    format: 'YYYY-MM-DD',
                    initialValue: false,
                    autoClose: true,
                    minDate: new Date().getTime(),
                    observer: true,
                    altField: '#hb-booking-date',
                    altFormat: 'YYYY-MM-DD',
                    calendarType: 'persian',
                    viewMode: 'day',
                    calendar:{
                        persian: {
                            leapYearMode: 'astronomical'
                        }
                    },
                    formatter: function(unix) {
                        // Force English numbers instead of Persian digits
                        const pDate = new persianDate(unix);
                        const year = pDate.year();
                        const month = String(pDate.month()).padStart(2, '0');
                        const day = String(pDate.date()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    },
                    altFieldFormatter: function(unix) {
                        // Ensure altField also gets English numbers
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
                        $('#hb-booking-date').val(dateStr).trigger('change');
                    }
                });
            } catch (error) {
                // Silently handle datepicker initialization errors
            }
        }
    }

    /**
     * Initialize Select2 for services multi-select
     */
    function initSelect2() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('#hb-services').select2({
                placeholder: 'انتخاب خدمات مورد نیاز',
                allowClear: false,
                dir: 'rtl',
                width: '100%',
                language: {
                    noResults: function() {
                        return 'نتیجه‌ای یافت نشد';
                    },
                    searching: function() {
                        return 'در حال جستجو...';
                    }
                },
                minimumResultsForSearch: -1 // Hide search box since we only have 8 options
            });
        }
    }

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        // Initialize Persian datepicker if needed
        initPersianDatepicker();

        // Initialize Select2 for services field
        initSelect2();

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
