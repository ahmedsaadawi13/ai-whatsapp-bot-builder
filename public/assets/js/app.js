// FILE: /public/assets/js/app.js

/**
 * AI WhatsApp Bot Builder - Main JavaScript
 */

(function() {
    'use strict';

    // Get CSRF token from meta tag
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Add CSRF token to all AJAX requests
    function setupCsrfForAjax() {
        const csrfToken = getCsrfToken();

        // For fetch API
        const originalFetch = window.fetch;
        window.fetch = function(url, options) {
            options = options || {};
            options.headers = options.headers || {};

            if (typeof options.headers.append === 'function') {
                options.headers.append('X-CSRF-Token', csrfToken);
            } else {
                options.headers['X-CSRF-Token'] = csrfToken;
            }

            return originalFetch(url, options);
        };

        // For XMLHttpRequest
        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function() {
            originalOpen.apply(this, arguments);
            this.setRequestHeader('X-CSRF-Token', csrfToken);
        };
    }

    // Confirm delete actions
    function setupDeleteConfirmation() {
        const deleteForms = document.querySelectorAll('form[data-confirm]');

        deleteForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const message = form.getAttribute('data-confirm') || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        const deleteButtons = document.querySelectorAll('[data-confirm]');
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                const message = button.getAttribute('data-confirm') || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    }

    // Auto-hide alerts after 5 seconds
    function setupAutoHideAlerts() {
        const alerts = document.querySelectorAll('.alert');

        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';

                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000);
        });
    }

    // Copy to clipboard functionality
    function setupCopyToClipboard() {
        const copyButtons = document.querySelectorAll('[data-copy]');

        copyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const text = button.getAttribute('data-copy');

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        showTempMessage(button, 'Copied!');
                    }).catch(function(err) {
                        console.error('Failed to copy:', err);
                    });
                } else {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showTempMessage(button, 'Copied!');
                }
            });
        });
    }

    // Show temporary message
    function showTempMessage(element, message) {
        const originalText = element.textContent;
        element.textContent = message;

        setTimeout(function() {
            element.textContent = originalText;
        }, 2000);
    }

    // Form validation helper
    function setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');

        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        });
    }

    // Initialize all functionality
    function init() {
        setupCsrfForAjax();
        setupDeleteConfirmation();
        setupAutoHideAlerts();
        setupCopyToClipboard();
        setupFormValidation();

        console.log('AI WhatsApp Bot Builder initialized');
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
