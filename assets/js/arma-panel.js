/**
 * Arma Panel - Customer Dashboard JavaScript
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // 1. Ticket Creation AJAX
        var newTicketForm = document.getElementById('arma-new-ticket-form');
        if (newTicketForm) {
            newTicketForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var submitBtn = newTicketForm.querySelector('button[type="submit"]');
                var msgEl = document.getElementById('arma-ticket-form-msg');

                if (submitBtn) submitBtn.disabled = true;
                if (msgEl) {
                    msgEl.style.display = 'none';
                    msgEl.className = 'arma-notice';
                }

                var formData = new FormData(newTicketForm);
                formData.append('action', 'arma_create_ticket');
                formData.append('nonce', window.armaPanelData ? window.armaPanelData.ticket_nonce : '');

                fetch(window.armaPanelData ? window.armaPanelData.ajax_url : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.className = 'arma-notice ' + (res.success ? 'arma-notice--success' : 'arma-notice--error');
                        msgEl.textContent = res.data.message;
                    }
                    if (res.success) {
                        newTicketForm.reset();
                        setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                    }
                })
                .catch(function () {
                    if (submitBtn) submitBtn.disabled = false;
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.className = 'arma-notice arma-notice--error';
                        msgEl.textContent = 'خطا در برقراری ارتباط با سرور.';
                    }
                });
            });
        }

        // 2. Ticket Reply AJAX
        var replyTicketForm = document.getElementById('arma-reply-ticket-form');
        if (replyTicketForm) {
            replyTicketForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var submitBtn = replyTicketForm.querySelector('button[type="submit"]');
                var msgEl = document.getElementById('arma-ticket-reply-msg');

                if (submitBtn) submitBtn.disabled = true;
                if (msgEl) {
                    msgEl.style.display = 'none';
                    msgEl.className = 'arma-notice';
                }

                var formData = new FormData(replyTicketForm);
                formData.append('action', 'arma_reply_ticket');
                formData.append('nonce', window.armaPanelData ? window.armaPanelData.ticket_nonce : '');

                fetch(window.armaPanelData ? window.armaPanelData.ajax_url : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.className = 'arma-notice ' + (res.success ? 'arma-notice--success' : 'arma-notice--error');
                        msgEl.textContent = res.data.message;
                    }
                    if (res.success) {
                        replyTicketForm.reset();
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    }
                })
                .catch(function () {
                    if (submitBtn) submitBtn.disabled = false;
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.className = 'arma-notice arma-notice--error';
                        msgEl.textContent = 'خطا در ارسال پاسخ.';
                    }
                });
            });
        }

        // 3. Mark Notification Read
        var markReadBtns = document.querySelectorAll('.arma-btn-mark-notification-read');
        markReadBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var id = btn.getAttribute('data-id');
                if (!id) return;

                var card = btn.closest('.arma-notification-item');
                btn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'arma_mark_notification_read');
                formData.append('nonce', window.armaPanelData ? window.armaPanelData.notification_nonce : '');
                formData.append('id', id);

                fetch(window.armaPanelData ? window.armaPanelData.ajax_url : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success && card) {
                        card.classList.remove('arma-notification-item--unread');
                        btn.remove();
                        decrementBellBadge();
                    }
                });
            });
        });

        // 4. Mark All Notifications Read
        var markAllBtn = document.getElementById('arma-mark-all-notifications');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                markAllBtn.disabled = true;

                var formData = new FormData();
                formData.append('action', 'arma_mark_all_notifications_read');
                formData.append('nonce', window.armaPanelData ? window.armaPanelData.notification_nonce : '');

                fetch(window.armaPanelData ? window.armaPanelData.ajax_url : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success) {
                        window.location.reload();
                    }
                });
            });
        }

        function decrementBellBadge() {
            var badge = document.querySelector('.arma-profile-card__bell .arma-badge-dot');
            if (badge) {
                var current = parseInt(badge.textContent, 10) || 1;
                var next = current - 1;
                if (next <= 0) {
                    badge.remove();
                } else {
                    badge.textContent = next;
                }
            }
        }
    });
})();
