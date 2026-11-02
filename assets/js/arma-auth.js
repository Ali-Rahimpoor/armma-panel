/**
 * Arma Panel - Authentication (Phone & OTP Flow)
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var authForm = document.getElementById('arma-auth-form');
        if (!authForm) return;

        var phoneStep   = document.getElementById('arma-step-phone');
        var otpStep     = document.getElementById('arma-step-otp');
        var nameStep    = document.getElementById('arma-step-name');
        var phoneInput  = document.getElementById('arma-phone-input');
        var otpInput    = document.getElementById('arma-otp-input');
        var firstName   = document.getElementById('arma-firstname-input');
        var lastName    = document.getElementById('arma-lastname-input');
        var btnSendOtp  = document.getElementById('arma-btn-send-otp');
        var btnVerify   = document.getElementById('arma-btn-verify');
        var btnRegister = document.getElementById('arma-btn-register');
        var btnResend   = document.getElementById('arma-btn-resend');
        var timerEl     = document.getElementById('arma-timer-count');
        var msgBox      = document.getElementById('arma-auth-message');
        var displayPhone= document.getElementById('arma-otp-target-phone');
        var editPhoneBtn= document.getElementById('arma-btn-edit-phone');

        var countdownInterval = null;

        function showMessage(text, isError) {
            if (!msgBox) return;
            msgBox.style.display = 'block';
            msgBox.className = 'arma-auth-message ' + (isError ? 'arma-auth-message--error' : 'arma-auth-message--success');
            msgBox.textContent = text;
        }

        function clearMessage() {
            if (!msgBox) return;
            msgBox.style.display = 'none';
            msgBox.textContent = '';
        }

        function startTimer(seconds) {
            clearInterval(countdownInterval);
            var remaining = seconds;
            if (btnResend) {
                btnResend.disabled = true;
                btnResend.style.opacity = '0.5';
            }
            if (timerEl) {
                timerEl.textContent = formatTime(remaining);
            }

            countdownInterval = setInterval(function () {
                remaining--;
                if (timerEl) {
                    timerEl.textContent = formatTime(remaining);
                }
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    if (btnResend) {
                        btnResend.disabled = false;
                        btnResend.style.opacity = '1';
                    }
                    if (timerEl) {
                        timerEl.textContent = '۰:۰۰';
                    }
                }
            }, 1000);
        }

        function formatTime(sec) {
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            var formatted = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            // Convert to Persian digits
            var pDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            return formatted.replace(/[0-9]/g, function (w) {
                return pDigits[+w];
            });
        }

        // 1. Request OTP
        function requestOtp() {
            var phone = (phoneInput.value || '').trim();
            if (!phone) {
                showMessage('لطفاً شماره موبایل خود را وارد نمایید.', true);
                return;
            }

            clearMessage();
            btnSendOtp.disabled = true;
            btnSendOtp.textContent = 'در حال ارسال کد...';

            var formData = new FormData();
            formData.append('action', 'arma_send_otp');
            formData.append('nonce', window.armaAuthData ? window.armaAuthData.nonce : '');
            formData.append('phone', phone);

            fetch(window.armaAuthData ? window.armaAuthData.ajax_url : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                btnSendOtp.disabled = false;
                btnSendOtp.textContent = 'دریافت کد تایید';

                if (res.success) {
                    phoneStep.style.display = 'none';
                    otpStep.style.display = 'block';
                    if (displayPhone) displayPhone.textContent = phone;
                    if (otpInput) {
                        otpInput.value = '';
                        otpInput.focus();
                    }
                    var cooldown = res.data.cooldown || 60;
                    startTimer(cooldown);
                    showMessage(res.data.message || 'کد تایید ارسال شد.', false);
                } else {
                    showMessage(res.data.message || 'خطا در ارسال کد تایید.', true);
                }
            })
            .catch(function () {
                btnSendOtp.disabled = false;
                btnSendOtp.textContent = 'دریافت کد تایید';
                showMessage('خطای شبکه یا سرور رخ داد. لطفاً دوباره امتحان کنید.', true);
            });
        }

        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', function (e) {
                e.preventDefault();
                requestOtp();
            });
        }

        // 2. Verify OTP
        function verifyOtp() {
            var phone = (phoneInput.value || '').trim();
            var code  = (otpInput.value || '').trim();

            if (!code) {
                showMessage('لطفاً کد تایید را وارد کنید.', true);
                return;
            }

            clearMessage();
            btnVerify.disabled = true;
            btnVerify.textContent = 'در حال بررسی...';

            var formData = new FormData();
            formData.append('action', 'arma_verify_otp');
            formData.append('nonce', window.armaAuthData ? window.armaAuthData.nonce : '');
            formData.append('phone', phone);
            formData.append('code', code);
            if (window.armaAuthData && window.armaAuthData.redirect_to) {
                formData.append('redirect_to', window.armaAuthData.redirect_to);
            }

            fetch(window.armaAuthData ? window.armaAuthData.ajax_url : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                btnVerify.disabled = false;
                btnVerify.textContent = 'ورود به حساب';

                if (res.success) {
                    if (res.data.requires_registration) {
                        // User needs to fill first & last name
                        otpStep.style.display = 'none';
                        nameStep.style.display = 'block';
                        if (firstName) firstName.focus();
                        showMessage(res.data.message, false);
                    } else if (res.data.redirect_url) {
                        // Logged in!
                        showMessage('ورود با موفقیت انجام شد. در حال انتقال...', false);
                        window.location.href = res.data.redirect_url;
                    }
                } else {
                    showMessage(res.data.message || 'کد تایید نادرست است.', true);
                }
            })
            .catch(function () {
                btnVerify.disabled = false;
                btnVerify.textContent = 'ورود به حساب';
                showMessage('خطای سرور رخ داد. لطفاً دوباره تلاش کنید.', true);
            });
        }

        if (btnVerify) {
            btnVerify.addEventListener('click', function (e) {
                e.preventDefault();
                verifyOtp();
            });
        }

        // 3. Complete Registration
        function completeRegistration() {
            var phone = (phoneInput.value || '').trim();
            var fname = (firstName.value || '').trim();
            var lname = (lastName.value || '').trim();

            if (!fname || !lname) {
                showMessage('لطفاً نام و نام خانوادگی خود را کامل وارد کنید.', true);
                return;
            }

            clearMessage();
            btnRegister.disabled = true;
            btnRegister.textContent = 'در حال ثبت‌نام...';

            var formData = new FormData();
            formData.append('action', 'arma_complete_registration');
            formData.append('nonce', window.armaAuthData ? window.armaAuthData.nonce : '');
            formData.append('phone', phone);
            formData.append('first_name', fname);
            formData.append('last_name', lname);
            if (window.armaAuthData && window.armaAuthData.redirect_to) {
                formData.append('redirect_to', window.armaAuthData.redirect_to);
            }

            fetch(window.armaAuthData ? window.armaAuthData.ajax_url : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                btnRegister.disabled = false;
                btnRegister.textContent = 'تکمیل ثبت‌نام';

                if (res.success && res.data.redirect_url) {
                    showMessage('ثبت‌نام با موفقیت انجام شد. در حال انتقال...', false);
                    window.location.href = res.data.redirect_url;
                } else {
                    showMessage(res.data.message || 'خطا در ثبت‌نام.', true);
                }
            })
            .catch(function () {
                btnRegister.disabled = false;
                btnRegister.textContent = 'تکمیل ثبت‌نام';
                showMessage('خطا در برقراری ارتباط با سرور.', true);
            });
        }

        if (btnRegister) {
            btnRegister.addEventListener('click', function (e) {
                e.preventDefault();
                completeRegistration();
            });
        }

        // Resend OTP button
        if (btnResend) {
            btnResend.addEventListener('click', function (e) {
                e.preventDefault();
                if (!btnResend.disabled) {
                    requestOtp();
                }
            });
        }

        // Edit Phone button
        if (editPhoneBtn) {
            editPhoneBtn.addEventListener('click', function (e) {
                e.preventDefault();
                clearInterval(countdownInterval);
                otpStep.style.display = 'none';
                phoneStep.style.display = 'block';
                phoneInput.focus();
                clearMessage();
            });
        }
    });
})();
