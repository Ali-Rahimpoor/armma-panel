<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var array<string, mixed> $user
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

$orderId   = (int) ($_GET['order_id'] ?? 0);
$token     = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
$isValid   = (bool) ($section_data['can_spin'] ?? false);
$slices    = (array) $settings->get('lucky_wheel.slices', []);
// A wheel with no slices can neither spin nor render — treat it as
// unavailable instead of drawing a broken (NaN-arc) canvas.
if ($slices === []) {
    $isValid = false;
}
$panelSlug = trim((string) $settings->get('general.panel_slug', 'arma-panel'), '/');
$panelUrl  = function_exists('arma_panel_url') ? arma_panel_url('dashboard') : home_url('/' . $panelSlug . '/');

$orderSummary = $section_data['order_summary'] ?? null;
$alreadySpun  = (bool) ($section_data['already_spun'] ?? false);
?>

<?php if (!empty($orderSummary)): ?>
    <!-- ════════ 1. Thank You Hero (تشکر از خرید) ════════ -->
    <section class="arma-card arma-thanks-hero" aria-live="polite">
        <div class="arma-thanks-hero__badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
        <h2 class="arma-thanks-hero__title"><?php esc_html_e('سپاس از خرید شما!', 'arma-panel'); ?></h2>
        <p class="arma-thanks-hero__subtitle">
            <?php esc_html_e('سفارش شما با موفقیت ثبت شد. از اعتمادتان به ما سپاسگزاریم — در ادامه شانستان را در گردونه امتحان کنید.', 'arma-panel'); ?>
        </p>
        <?php if (!empty($orderSummary['order_number']) || !empty($orderSummary['total_formatted'])): ?>
            <div class="arma-thanks-hero__meta">
                <?php if (!empty($orderSummary['order_number'])): ?>
                    <span class="arma-thanks-hero__chip">
                        <?php esc_html_e('شماره سفارش:', 'arma-panel'); ?>
                        <strong><?php echo esc_html((string) $orderSummary['order_number']); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($orderSummary['total_formatted'])): ?>
                    <span class="arma-thanks-hero__chip">
                        <?php esc_html_e('مبلغ:', 'arma-panel'); ?>
                        <strong><?php echo esc_html((string) $orderSummary['total_formatted']); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($orderSummary['status_label'])): ?>
                    <span class="arma-thanks-hero__chip">
                        <?php esc_html_e('وضعیت:', 'arma-panel'); ?>
                        <strong><?php echo esc_html((string) $orderSummary['status_label']); ?></strong>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <a href="#arma-wheel-section" class="arma-thanks-hero__cta">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            <?php echo $alreadySpun
                ? esc_html__('مشاهده سفارش‌های من', 'arma-panel')
                : esc_html__('شروع چرخش گردونه شانس', 'arma-panel'); ?>
        </a>
    </section>
<?php endif; ?>

<?php if ($alreadySpun && !$isValid): ?>
    <!-- Already played — show a friendly notice instead of the wheel -->
    <div class="arma-card" id="arma-wheel-used">
        <div class="arma-empty" style="padding: 32px 24px;">
            <div style="width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 50%; background: var(--arma-primary-light); display: flex; align-items: center; justify-content: center; color: var(--arma-primary);">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--arma-heading); margin: 0 0 8px;">
                <?php esc_html_e('شانس این سفارش استفاده شده است', 'arma-panel'); ?>
            </h3>
            <p style="color: var(--arma-text-muted); font-size: 0.9rem; margin: 0;">
                <?php esc_html_e('در خرید بعدی دوباره شانس خود را امتحان کنید. جوایز منتظر شما هستند!', 'arma-panel'); ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="arma-card" id="arma-wheel-section">
    <?php if (!$isValid): ?>
        <div class="arma-empty" style="padding: 48px 24px;">
            <div style="width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; background: #fff1f2; display: flex; align-items: center; justify-content: center; color: var(--arma-danger);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--arma-heading); margin-bottom: 10px;">
                <?php esc_html_e('گردونه شانس در دسترس نیست', 'arma-panel'); ?>
            </h2>
            <p style="color: var(--arma-text-muted); font-size: 0.95rem; max-width: 480px; margin: 0 auto 24px; line-height: 1.7;">
                <?php esc_html_e('این بخش اختصاصی مشتریانی است که به تازگی سفارشی در سایت ثبت کرده‌اند یا شانس چرخش این سفارش قبلاً استفاده شده است.', 'arma-panel'); ?>
            </p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="<?php echo esc_url($panelUrl); ?>" class="arma-btn arma-btn--primary">
                    <?php esc_html_e('بازگشت به پیشخوان', 'arma-panel'); ?>
                </a>
                <?php if (function_exists('wc_get_page_permalink')): ?>
                    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="arma-btn arma-btn--outline">
                        <?php esc_html_e('مشاهده فروشگاه', 'arma-panel'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="arma-wheel-inner">
            <span class="arma-wheel-ordertag">
                <?php printf(esc_html__('سفارش شماره #%d با موفقیت ثبت شد', 'arma-panel'), $orderId); ?>
            </span>
            <h1 class="arma-wheel-title">
                <?php echo esc_html($settings->get('lucky_wheel.title', 'گردونه شانس خریداران')); ?>
            </h1>
            <p class="arma-wheel-subtitle">
                <?php echo esc_html($settings->get('lucky_wheel.subtitle', 'شانس خود را برای برنده شدن کد تخفیف یا هدایای ویژه در سفارش بعدی امتحان کنید!')); ?>
            </p>

            <!-- Wheel Stage (fully responsive, never overflows its card) -->
            <div class="arma-wheel-stage">
                <div class="arma-wheel-pointer" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 22 L20 12 L12 15 L4 12 Z" fill="currentColor"/></svg>
                </div>
                <div class="arma-wheel-wrap">
                    <canvas id="arma-lucky-canvas" aria-label="<?php esc_attr_e('گردونه شانس', 'arma-panel'); ?>"></canvas>
                    <button type="button" id="arma-sound-toggle" class="arma-sound-btn" aria-label="<?php esc_attr_e('صدا', 'arma-panel'); ?>" title="<?php esc_attr_e('روشن/خاموش کردن صدا', 'arma-panel'); ?>">
                        <svg class="arma-sound-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>
                        <svg class="arma-sound-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>
                    </button>
                </div>
            </div>

            <!-- Spin Button -->
            <div>
                <button type="button" id="arma-btn-spin" class="arma-btn arma-btn--primary arma-spin-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"></path></svg>
                    <span><?php esc_html_e('شروع چرخش گردونه', 'arma-panel'); ?></span>
                </button>
            </div>

            <!-- Result Card -->
            <div id="arma-wheel-result" class="arma-wheel-result">
                <div class="arma-wheel-result__icon" id="arma-wheel-result-icon"></div>
                <h3 id="arma-wheel-prize-title" class="arma-wheel-result__title"></h3>
                <p id="arma-wheel-prize-desc" class="arma-wheel-result__desc"></p>

                <div id="arma-coupon-container" class="arma-wheel-coupon" style="display: none;">
                    <span class="arma-wheel-coupon__label"><?php esc_html_e('کد تخفیف اختصاصی شما:', 'arma-panel'); ?></span>
                    <div class="arma-wheel-coupon__row">
                        <strong id="arma-coupon-code" class="arma-wheel-coupon__code"></strong>
                        <button type="button" id="arma-btn-copy-code" class="arma-btn arma-btn--outline arma-btn--sm">
                            <?php esc_html_e('کپی کد', 'arma-panel'); ?>
                        </button>
                    </div>
                    <p class="arma-wheel-coupon__hint"><?php esc_html_e('این کد فقط برای شما و یک‌بار مصرف است.', 'arma-panel'); ?></p>
                </div>

                <div>
                    <a href="<?php echo esc_url($panelUrl); ?>" class="arma-btn arma-btn--primary arma-btn--sm">
                        <?php esc_html_e('مشاهده پنل کاربری', 'arma-panel'); ?>
                    </a>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var canvas = document.getElementById('arma-lucky-canvas');
            if (!canvas) return;

            var ctx = canvas.getContext('2d');
            var slices = <?php echo wp_json_encode($slices); ?>;
            var numSlices = slices.length;
            var arc = (2 * Math.PI) / numSlices;
            var currentAngle = 0;
            var isSpinning = false;
            var size = 0;
            var highlightIndex = -1;
            var hubLabel = '🎁';

            /* ─────────── Sound engine (WebAudio — no external files) ─────────── */
            var Sound = (function () {
                var audioCtx = null;
                var enabled = true;
                try { enabled = localStorage.getItem('armaWheelSound') !== 'off'; } catch (e) {}

                function ensure() {
                    if (!audioCtx) {
                        var AC = window.AudioContext || window.webkitAudioContext;
                        if (!AC) return null;
                        audioCtx = new AC();
                    }
                    if (audioCtx.state === 'suspended') { audioCtx.resume(); }
                    return audioCtx;
                }

                function tone(freq, startAt, duration, type, volume) {
                    var c = ensure();
                    if (!c || !enabled) return;
                    var osc = c.createOscillator();
                    var gain = c.createGain();
                    osc.type = type || 'sine';
                    osc.frequency.setValueAtTime(freq, c.currentTime + startAt);
                    gain.gain.setValueAtTime(0.0001, c.currentTime + startAt);
                    gain.gain.exponentialRampToValueAtTime(volume || 0.12, c.currentTime + startAt + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + startAt + duration);
                    osc.connect(gain).connect(c.destination);
                    osc.start(c.currentTime + startAt);
                    osc.stop(c.currentTime + startAt + duration + 0.05);
                }

                return {
                    tick: function () { tone(1450, 0, 0.045, 'square', 0.05); },
                    win: function () {
                        tone(523.25, 0,    0.16, 'triangle', 0.16);
                        tone(659.25, 0.13, 0.16, 'triangle', 0.16);
                        tone(783.99, 0.26, 0.16, 'triangle', 0.16);
                        tone(1046.5, 0.40, 0.42, 'triangle', 0.18);
                        tone(1046.5, 0.40, 0.42, 'sine',     0.10);
                    },
                    lose: function () {
                        tone(320, 0,    0.22, 'sawtooth', 0.10);
                        tone(240, 0.18, 0.30, 'sawtooth', 0.10);
                        tone(160, 0.42, 0.40, 'sine',     0.12);
                    },
                    isEnabled: function () { return enabled; },
                    setEnabled: function (v) {
                        enabled = !!v;
                        try { localStorage.setItem('armaWheelSound', enabled ? 'on' : 'off'); } catch (e) {}
                    },
                    unlock: function () { ensure(); }
                };
            })();

            var soundBtn = document.getElementById('arma-sound-toggle');
            function paintSoundBtn() {
                if (!soundBtn) return;
                soundBtn.classList.toggle('is-muted', !Sound.isEnabled());
            }
            if (soundBtn) {
                paintSoundBtn();
                soundBtn.addEventListener('click', function () {
                    Sound.setEnabled(!Sound.isEnabled());
                    paintSoundBtn();
                    if (Sound.isEnabled()) { Sound.unlock(); Sound.tick(); }
                });
            }

            /* ─────────── Canvas setup (crisp on retina, responsive) ─────────── */
            function setupCanvas() {
                var wrap = canvas.parentElement;
                var target = Math.min(wrap.clientWidth || 340, 360);
                var dpr = window.devicePixelRatio || 1;
                size = Math.max(240, Math.floor(target));
                canvas.width = Math.floor(size * dpr);
                canvas.height = Math.floor(size * dpr);
                canvas.style.width = size + 'px';
                canvas.style.height = size + 'px';
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            }

            function shade(hex, percent) {
                var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '#64748b');
                var r = parseInt(m[1], 16), g = parseInt(m[2], 16), b = parseInt(m[3], 16);
                var p = percent < 0 ? 1 + percent : 1;
                var t = percent < 0 ? 0 : 255 * percent;
                r = Math.round(r * p + t * (percent > 0 ? percent : 0));
                g = Math.round(g * p + t * (percent > 0 ? percent : 0));
                b = Math.round(b * p + t * (percent > 0 ? percent : 0));
                return 'rgb(' + Math.min(255, Math.max(0, r)) + ',' + Math.min(255, Math.max(0, g)) + ',' + Math.min(255, Math.max(0, b)) + ')';
            }

            function drawWheel() {
                var R = size / 2;
                var rimW = Math.max(14, R * 0.11);
                var faceR = R - rimW - 2;
                ctx.clearRect(0, 0, size, size);

                // outer soft shadow ring
                ctx.beginPath();
                ctx.arc(R, R, R - 1, 0, 2 * Math.PI);
                ctx.fillStyle = '#0f172a';
                ctx.fill();

                // slices
                for (var i = 0; i < numSlices; i++) {
                    var a0 = currentAngle + i * arc;
                    var a1 = a0 + arc;
                    var base = slices[i].color || '#f43f5e';

                    var grad = ctx.createRadialGradient(R, R, faceR * 0.25, R, R, faceR);
                    grad.addColorStop(0, shade(base, 0.22));
                    grad.addColorStop(1, base);

                    ctx.beginPath();
                    ctx.moveTo(R, R);
                    ctx.arc(R, R, faceR, a0, a1);
                    ctx.closePath();
                    ctx.fillStyle = grad;
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(255,255,255,0.85)';
                    ctx.lineWidth = 2;
                    ctx.stroke();

                    // labels
                    ctx.save();
                    ctx.translate(R, R);
                    ctx.rotate(a0 + arc / 2);
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    var label = String(slices[i].label || '');
                    var fs = Math.max(11, Math.round(R * 0.082));
                    ctx.font = '700 ' + fs + 'px ' + (getComputedStyle(document.body).fontFamily || 'Tahoma');
                    var maxW = faceR * 0.58;
                    while (ctx.measureText(label).width > maxW && label.length > 4) {
                        label = label.slice(0, -2);
                    }
                    if (label !== String(slices[i].label || '')) label += '…';
                    ctx.fillStyle = 'rgba(0,0,0,0.25)';
                    ctx.fillText(label, faceR - 12, 1.5);
                    ctx.fillStyle = '#ffffff';
                    ctx.shadowColor = 'rgba(0,0,0,0.35)';
                    ctx.shadowBlur = 4;
                    ctx.fillText(label, faceR - 12, 0);
                    ctx.restore();
                }

                // highlight the winning slice (all others dimmed)
                if (highlightIndex >= 0 && highlightIndex < numSlices) {
                    for (var d = 0; d < numSlices; d++) {
                        if (d === highlightIndex) continue;
                        var da0 = currentAngle + d * arc;
                        ctx.beginPath();
                        ctx.moveTo(R, R);
                        ctx.arc(R, R, faceR, da0, da0 + arc);
                        ctx.closePath();
                        ctx.fillStyle = 'rgba(15,23,42,0.55)';
                        ctx.fill();
                    }
                    var wa0 = currentAngle + highlightIndex * arc;
                    ctx.beginPath();
                    ctx.moveTo(R, R);
                    ctx.arc(R, R, faceR, wa0, wa0 + arc);
                    ctx.closePath();
                    ctx.lineWidth = 5;
                    ctx.strokeStyle = '#fbbf24';
                    ctx.shadowColor = 'rgba(251,191,36,0.95)';
                    ctx.shadowBlur = 18;
                    ctx.stroke();
                    ctx.shadowBlur = 0;
                }

                // golden bulbs on the rim
                var bulbs = 12;
                for (var b = 0; b < bulbs; b++) {
                    var ba = (b / bulbs) * 2 * Math.PI;
                    var bx = R + Math.cos(ba) * (R - rimW / 2);
                    var by = R + Math.sin(ba) * (R - rimW / 2);
                    ctx.beginPath();
                    ctx.arc(bx, by, Math.max(2.5, rimW * 0.16), 0, 2 * Math.PI);
                    ctx.fillStyle = '#fbbf24';
                    ctx.shadowColor = 'rgba(251,191,36,0.9)';
                    ctx.shadowBlur = 6;
                    ctx.fill();
                    ctx.shadowBlur = 0;
                }

                // center hub
                var hubR = Math.max(24, R * 0.15);
                ctx.beginPath();
                ctx.arc(R, R, hubR, 0, 2 * Math.PI);
                ctx.fillStyle = '#ffffff';
                ctx.shadowColor = 'rgba(0,0,0,0.35)';
                ctx.shadowBlur = 10;
                ctx.fill();
                ctx.shadowBlur = 0;
                ctx.beginPath();
                ctx.arc(R, R, hubR - 4, 0, 2 * Math.PI);
                ctx.strokeStyle = '#fbbf24';
                ctx.lineWidth = 2.5;
                ctx.stroke();
                ctx.font = '700 ' + Math.max(10, Math.round(hubR * 0.62)) + 'px Tahoma';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#0f172a';
                ctx.fillText(hubLabel, R, R + 1);
            }

            setupCanvas();
            drawWheel();
            window.addEventListener('resize', function () { setupCanvas(); drawWheel(); });

            /* ─────────── Spin flow ─────────── */
            var spinBtn = document.getElementById('arma-btn-spin');
            var resultBox = document.getElementById('arma-wheel-result');
            var resultIcon = document.getElementById('arma-wheel-result-icon');
            var prizeTitle = document.getElementById('arma-wheel-prize-title');
            var prizeDesc = document.getElementById('arma-wheel-prize-desc');
            var couponBox = document.getElementById('arma-coupon-container');
            var couponCodeEl = document.getElementById('arma-coupon-code');
            var copyBtn = document.getElementById('arma-btn-copy-code');

            if (spinBtn) {
                spinBtn.addEventListener('click', function () {
                    if (isSpinning) return;
                    isSpinning = true;
                    Sound.unlock();
                    spinBtn.disabled = true;
                    spinBtn.style.opacity = '0.6';

                    var formData = new FormData();
                    formData.append('action', 'arma_spin_wheel');
                    formData.append('nonce', window.armaWheelData ? window.armaWheelData.nonce : '');
                    formData.append('order_id', <?php echo (int) $orderId; ?>);
                    formData.append('token', <?php echo wp_json_encode($token); ?>);

                    fetch(window.armaWheelData ? window.armaWheelData.ajax_url : '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (!res.success) {
                            alert(res.data.message || 'خطا در چرخش گردونه.');
                            isSpinning = false;
                            spinBtn.disabled = false;
                            spinBtn.style.opacity = '1';
                            return;
                        }
                        animateWheel(res.data.slice_index, res.data);
                    })
                    .catch(function () {
                        alert('خطا در برقراری ارتباط با سرور.');
                        isSpinning = false;
                        spinBtn.disabled = false;
                        spinBtn.style.opacity = '1';
                    });
                });
            }

            function pointerSliceIndex() {
                var pointer = -Math.PI / 2;
                var rel = ((pointer - currentAngle) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);
                return Math.floor(rel / arc) % numSlices;
            }

            function animateWheel(targetIndex, winData) {
                var sliceAngle = (2 * Math.PI) / numSlices;
                var targetSliceCenter = targetIndex * sliceAngle + (sliceAngle / 2);
                var targetOffset = (1.5 * Math.PI) - targetSliceCenter;
                var totalRotation = (6 * 2 * Math.PI) + targetOffset;

                var startTime = performance.now();
                var duration = 5200;
                var lastIdx = pointerSliceIndex();

                function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

                function step(now) {
                    var elapsed = now - startTime;
                    var progress = Math.min(elapsed / duration, 1);
                    currentAngle = easeOutQuart(progress) * totalRotation;
                    drawWheel();

                    var idx = pointerSliceIndex();
                    if (idx !== lastIdx) {
                        lastIdx = idx;
                        Sound.tick();
                    }

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        isSpinning = false;
                        highlightIndex = targetIndex;
                        var wType = (winData.slice || {}).type;
                        hubLabel = ((wType === 'coupon' || wType === 'free_shipping') && (winData.slice || {}).code) ? '✓' : '✗';
                        drawWheel();
                        var stage = document.querySelector('.arma-wheel-stage');
                        if (stage) stage.classList.add('arma-wheel--decided');
                        showPrize(winData);
                    }
                }

                requestAnimationFrame(step);
            }

            function confettiBurst() {
                var stage = document.querySelector('.arma-wheel-stage');
                if (!stage) return;
                var colors = ['#fbbf24', '#f43f5e', '#10b981', '#0ea5e9', '#a78bfa', '#fff'];
                for (var i = 0; i < 60; i++) {
                    var c = document.createElement('i');
                    c.className = 'arma-confetti';
                    c.style.left = (5 + Math.random() * 90) + '%';
                    c.style.background = colors[Math.floor(Math.random() * colors.length)];
                    c.style.animationDelay = (Math.random() * 0.5) + 's';
                    c.style.animationDuration = (1.6 + Math.random() * 1.4) + 's';
                    c.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
                    stage.appendChild(c);
                    (function (el) {
                        setTimeout(function () { el.remove(); }, 3600);
                    })(c);
                }
            }

            function showPrize(data) {
                var wonSlice = data.slice || {};
                var isWin = (wonSlice.type === 'coupon' || wonSlice.type === 'free_shipping') && wonSlice.code;

                resultBox.style.display = 'block';
                resultBox.classList.add(isWin ? 'arma-wheel-result--win' : 'arma-wheel-result--lose');
                resultIcon.innerHTML = isWin
                    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 15s1.5-2 4-2 4 2 4 2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>';
                prizeTitle.textContent = data.message;

                if (isWin) {
                    prizeDesc.textContent = 'این کد تخفیف در حساب کاربری شما نیز ذخیره شد و در خرید بعدی قابل استفاده است:';
                    couponBox.style.display = 'block';
                    couponCodeEl.textContent = wonSlice.code;
                    Sound.win();
                    confettiBurst();
                } else {
                    prizeDesc.textContent = 'از اینکه همراه ما بودید سپاسگزاریم! در خرید بعدی دوباره شانس خود را امتحان کنید.';
                    couponBox.style.display = 'none';
                    Sound.lose();
                }

                resultBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    var code = couponCodeEl.textContent;
                    function markCopied() {
                        copyBtn.textContent = 'کپی شد!';
                        setTimeout(function () { copyBtn.textContent = 'کپی کد'; }, 2000);
                    }
                    function legacyCopy() {
                        // Fallback for non-secure contexts (no clipboard API)
                        var ta = document.createElement('textarea');
                        ta.value = code;
                        ta.style.cssText = 'position:fixed;opacity:0;';
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); markCopied(); } catch (e) {}
                        ta.remove();
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code).then(markCopied, legacyCopy);
                    } else {
                        legacyCopy();
                    }
                });
            }
        })();
        </script>
    <?php endif; ?>
</div>
<?php if (!empty($section_data['dashboard'])): ?>
    <!-- ════════ 3. Dashboard (below the wheel — keep browsing) ════════ -->
    <div class="arma-thanks-dashboard">
        <?php
        arma_view('panel/dashboard', [
            'user'         => $user,
            'settings'     => $settings,
            'section_data' => $section_data['dashboard'],
        ]);
        ?>
    </div>
<?php endif; ?>
