<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var string $activeTab
 * @var bool $saved
 * @var string $testSmsNotice
 * @var string $chanceErrorNotice
 */

$settings = isset($this) && isset($this->settings) ? $this->settings : ($settings ?? arma_app('settings'));
$testSmsNotice = $testSmsNotice ?? '';
$chanceErrorNotice = $chanceErrorNotice ?? '';

$tabs = [
    'general'        => ['label' => __('تنظیمات عمومی', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>'],
    'appearance'     => ['label' => __('ظاهر و رنگ‌بندی', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"></path><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path><path d="M2 2l7.586 7.586"></path><circle cx="11" cy="11" r="2"></circle></svg>'],
    'dashboard'      => ['label' => __('چیدمان داشبورد', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>'],
    'wishlist'       => ['label' => __('علاقه‌مندی‌ها', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>'],
    'authentication' => ['label' => __('ورود و ثبت‌نام (OTP)', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>'],
    'sms'            => ['label' => __('سامانه پیامک', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'],
    'wc_sms'         => ['label' => __('پیامک‌های ووکامرس', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>'],
    'lucky_wheel'    => ['label' => __('گردونه شانس', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a10 10 0 0 1 10 10M12 12l7-3M12 12l-5 6M12 12l-7-3M12 12l5 6M12 12V2"></path></svg>'],
    'modules'        => ['label' => __('مدیریت بخش‌ها', 'arma-panel'), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>'],
];

$currentPageUrl = admin_url('admin.php?page=arma-panel-settings');

// Active SMS provider: the param-name fields live on the auth/wc_sms
// tabs, but the provider <select> only exists on the sms tab — the JS toggle
// never runs there, so visibility is decided SERVER-SIDE here instead.
$activeProvider = (string) $settings->get('sms.provider', 'melipayamak');
?>

<style>
/* ============ Arma Panel — Modern Sidebar Admin UI ============ */
.arma-admin {
    direction: rtl;
    text-align: right;
    max-width: 1240px;
    margin: 20px 20px 40px 0;
    font-family: inherit;
}

/* Layout: sticky sidebar + content */
.arma-admin__layout { display: flex; gap: 22px; align-items: flex-start; }
.arma-admin__sidebar {
    width: 250px; flex-shrink: 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 10px; position: sticky; top: 46px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.arma-admin__sidebar-title {
    font-size: 0.72rem; font-weight: 800; color: #94a3b8;
    letter-spacing: 0.4px; padding: 10px 12px 8px; margin: 0;
}
.arma-admin__nav { display: flex; flex-direction: column; gap: 3px; }
.arma-admin__nav a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    color: #475569; text-decoration: none; font-size: 0.88rem; font-weight: 600;
    border: 1px solid transparent; transition: all 0.15s ease;
}
.arma-admin__nav a svg { width: 17px; height: 17px; flex-shrink: 0; opacity: 0.75; }
.arma-admin__nav a:hover { background: #f8fafc; color: #0f172a; }
.arma-admin__nav a.is-active {
    background: #fff1f2; border-color: #fecdd3; color: #be123c;
}
.arma-admin__nav a.is-active svg { opacity: 1; color: #e11d48; }

/* Content card */
.arma-admin__main { flex: 1; min-width: 0; }
.arma-admin__card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;
}
.arma-admin__card-header {
    padding: 18px 26px; border-bottom: 1px solid #f1f5f9;
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.arma-admin__card-title { margin: 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.arma-admin__card-hint { margin: 2px 0 0; font-size: 0.79rem; color: #64748b; }
.arma-admin__card-body { padding: 10px 26px 24px; }
.arma-admin__card-footer {
    padding: 15px 26px; background: #f8fafc; border-top: 1px solid #e2e8f0;
    display: flex; justify-content: flex-end; align-items: center; gap: 10px;
}

/* Field rows */
.arma-field-row {
    display: grid; grid-template-columns: 230px 1fr; gap: 20px;
    padding: 18px 0; border-bottom: 1px solid #f1f5f9; align-items: center;
}
.arma-field-row:last-child { border-bottom: none; }
.arma-field-label { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
.arma-field-desc { font-size: 0.78rem; color: #64748b; margin-top: 4px; font-weight: normal; line-height: 1.6; }
.arma-input-text, .arma-select {
    width: 100%; max-width: 440px; padding: 9px 14px;
    border: 1px solid #cbd5e1; border-radius: 9px; font-size: 0.9rem;
    color: #1e293b; background: #fff; transition: all 0.2s; font-family: inherit;
}
.arma-input-text:focus, .arma-select:focus {
    outline: none; border-color: #e11d48; box-shadow: 0 0 0 3px rgba(225,29,72,0.12);
}

/* Color palette grid */
.arma-color-section {
    margin: 26px 0 6px; padding: 18px 18px 8px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
}
.arma-color-section__title {
    margin: 0 0 4px; font-size: 0.92rem; font-weight: 800; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
.arma-color-section__title svg { width: 16px; height: 16px; color: #e11d48; }
.arma-color-section__desc { margin: 0 0 14px; font-size: 0.78rem; color: #64748b; }
.arma-color-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px;
}
.arma-color-item {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px;
}
.arma-color-item label { display: block; font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 8px; }
.arma-color-item input[type="color"] {
    width: 100%; height: 34px; padding: 2px; border: 1px solid #e2e8f0;
    border-radius: 8px; cursor: pointer; background: #fff;
}
.arma-color-item .arma-color-code {
    font-size: 0.72rem; color: #94a3b8; direction: ltr; text-align: left; margin-top: 5px; display: block;
}

/* Radius inputs */
.arma-radius-grid { display: flex; gap: 12px; flex-wrap: wrap; }
.arma-radius-item { display: flex; align-items: center; gap: 8px; }
.arma-radius-item input[type="number"] {
    width: 86px; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px;
    direction: ltr; text-align: center; font-size: 0.88rem;
}
.arma-radius-item span { font-size: 0.8rem; color: #475569; font-weight: 600; }

/* SMS test box */
.arma-sms-test-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 24px; }
.arma-sms-test-bar { display: flex; gap: 12px; align-items: center; max-width: 620px; }
.arma-btn-test-sms {
    background: #e11d48; color: #fff; border: none; padding: 10px 22px;
    border-radius: 9px; font-weight: 700; font-size: 0.88rem; cursor: pointer;
    transition: background 0.2s; font-family: inherit; white-space: nowrap;
}
.arma-btn-test-sms:hover { background: #be123c; }

/* Font preview + picker */
.arma-font-pick { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.arma-font-preview-line {
    margin-top: 14px; padding: 14px 18px; border: 1px dashed #e2e8f0; border-radius: 10px;
    background: #fff; font-size: 1.05rem; color: #0f172a;
}

/* Slices table */
.arma-slices-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
.arma-slices-table th {
    background: #f8fafc; padding: 10px 14px; text-align: right;
    font-size: 0.82rem; color: #475569; border-bottom: 1px solid #e2e8f0;
}
.arma-slices-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }

/* Buttons */
.arma-btn-ghost {
    background: #fff; border: 1px solid #cbd5e1; color: #475569;
    border-radius: 9px; padding: 9px 18px; cursor: pointer; font-size: 0.85rem;
    font-weight: 700; font-family: inherit; transition: all 0.2s;
}
.arma-btn-ghost:hover { border-color: #e11d48; color: #e11d48; }

/* Responsive */
@media (max-width: 960px) {
    .arma-admin__layout { flex-direction: column; }
    .arma-admin__sidebar {
        width: 100%; position: static; display: flex; gap: 6px;
        overflow-x: auto; padding: 8px;
    }
    .arma-admin__sidebar-title { display: none; }
    .arma-admin__nav { flex-direction: row; }
    .arma-admin__nav a { white-space: nowrap; padding: 8px 12px; }
    .arma-field-row { grid-template-columns: 1fr; gap: 8px; }
}

/* ============ Professional polish ============ */

/* Softer, layered elevation for cards */
.arma-admin__card, .arma-admin__sidebar {
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -18px rgba(15, 23, 42, 0.25);
}

/* Sidebar nav: refined active state with side bar (RTL) */
.arma-admin__nav a { position: relative; }
.arma-admin__nav a.is-active::before {
    content: '';
    position: absolute;
    right: -10px; top: 50%;
    transform: translateY(-50%);
    width: 4px; height: 20px;
    border-radius: 4px 0 0 4px;
    background: #e11d48;
}
.arma-admin__nav a {
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.arma-admin__nav a:hover { transform: translateX(-2px); }

/* Text inputs / selects: stronger focus ring */
.arma-input-text, .arma-select, input[type="number"] {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.arma-input-text:focus, .arma-select:focus {
    border-color: #e11d48;
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
    outline: none;
}

/* ── Modern toggle switches ──
   Applied to the plain "label > input[type=checkbox]" rows (name-only, no markup change).
   Switches stay real checkboxes → same POST semantics. */
.arma-admin input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
    border: none;
    border-radius: 999px;
    background: #cbd5e1;
    position: relative;
    cursor: pointer;
    transition: background 0.2s ease;
    margin: 0;
    vertical-align: middle;
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.18);
}
.arma-admin input[type="checkbox"]::after {
    content: '';
    position: absolute;
    top: 3px;
    right: 3px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.3);
    transition: transform 0.2s ease;
}
.arma-admin input[type="checkbox"]:checked {
    background: #22c55e;
    box-shadow: inset 0 1px 3px rgba(21, 128, 61, 0.35), 0 0 0 3px rgba(34, 197, 94, 0.18);
}
.arma-admin input[type="checkbox"]::before {
    content: '\2713';
    position: absolute;
    top: 50%;
    right: 7px;
    transform: translateY(-50%);
    font-size: 12px;
    font-weight: 900;
    color: #fff;
    opacity: 0;
    transition: opacity 0.15s ease;
    line-height: 1;
}
.arma-admin input[type="checkbox"]:checked::before { opacity: 1; }
.arma-admin input[type="checkbox"]:checked::after {
    transform: translateX(-18px);
    background: #fff;
}
.arma-admin input[type="checkbox"]:focus-visible {
    outline: 2px solid #e11d48;
    outline-offset: 2px;
}

/* Small checkboxes inside tables stay classic (wheel slices etc.) */
.arma-admin table input[type="checkbox"],
.arma-admin .arma-slices-table input[type="checkbox"] {
    appearance: auto;
    -webkit-appearance: auto;
    width: auto;
    height: auto;
    border-radius: 0;
    background: #fff;
    box-shadow: none;
    accent-color: #22c55e;
    width: 16px;
    height: 16px;
}
.arma-admin table input[type="checkbox"]::after,
.arma-admin table input[type="checkbox"]::before { content: none; }

/* Card footer save button emphasis */
.arma-admin__card-footer .button-primary {
    box-shadow: 0 6px 16px -8px rgba(225, 29, 72, 0.6);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.arma-admin__card-footer .button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px -8px rgba(225, 29, 72, 0.7);
}

/* Responsive sidebar */
@media (max-width: 960px) {
    .arma-admin__layout { flex-direction: column; }
    .arma-admin__sidebar { width: 100%; position: static; }
    .arma-admin__nav { flex-direction: row; flex-wrap: wrap; }
    .arma-admin__nav a.is-active::before { display: none; }
}

/* ============ Light professional admin shell ============ */

/* Page canvas */
.arma-admin { margin: 16px 0 40px 0; }

/* Sidebar nav: tint pill active */
.arma-admin__sidebar {
    border-radius: 14px;
    border-color: #e6e9ef;
    padding: 8px;
}
.arma-admin__nav a { border-radius: 10px; font-size: 0.86rem; }
.arma-admin__nav a svg { width: 17px; height: 17px; }
.arma-admin__nav a:hover { background: #f8f9fb; }

/* Cards */
.arma-admin__card { border-radius: 16px; border-color: #e6e9ef; }
.arma-admin__card-header { padding: 16px 24px; }
.arma-admin__card-title { font-size: 1rem; letter-spacing: -0.01em; }
.arma-admin__card-body { padding: 8px 24px 22px; }
.arma-admin__card-footer { border-radius: 0 0 16px 16px; }

/* Field typography */
.arma-field-row { padding: 17px 0; }
.arma-field-label { font-weight: 700; color: #1e293b; }
.arma-field-desc { color: #64748b; line-height: 1.8; }
.arma-input-text, .arma-select {
    min-height: 42px;
    border-radius: 10px;
    border-color: #dbe1e8;
}
.arma-input-text:focus, .arma-select:focus {
    border-color: #e11d48;
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.10);
}

/* Inputs inside number/color rows keep alignment */
.arma-admin input[type="number"] { min-height: 42px; }

/* Responsive tweaks */
@media (max-width: 960px) {
    .arma-admin { margin: 12px 0 32px 0; }
}

/* ============ Settings page design system ============ */

/* Two-column, label-above field grid */
.arma-admin__card-body {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 44px;
    padding: 8px 26px 22px;
}
.arma-admin__card-body > .arma-wide,
.arma-admin__card-body > .arma-color-section,
.arma-admin__card-body > .arma-sms-test-box,
.arma-admin__card-body > table.arma-slices-table { grid-column: 1 / -1; }

.arma-field-row {
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 16px 0 18px;
    border-bottom: 1px dashed #eef1f5;
    align-items: stretch;
}
.arma-field-row:last-child { border-bottom: none; }
.arma-field-row .arma-field-desc { margin: 0; }
.arma-input-text, .arma-select { width: 100%; max-width: 100%; }

/* Sticky branded save bar */
.arma-admin__card-footer {
    position: sticky;
    bottom: 0;
    z-index: 6;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
    border-radius: 0 0 16px 16px;
    box-shadow: 0 -6px 18px -12px rgba(16, 24, 40, 0.18);
}

/* Full-pill nav tabs */
.arma-admin__nav a { border-radius: 999px; }

/* Color section cards sit on the grid nicely */
.arma-color-section { margin: 10px 0 4px; }

/* Toast */
.arma-toast {
    position: fixed;
    bottom: 26px;
    left: 26px;
    z-index: 100000;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 20px;
    border-radius: 14px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
    font-size: 0.86rem;
    font-weight: 700;
    box-shadow: 0 10px 30px -12px rgba(4, 120, 87, 0.45);
    animation: armaToastIn 0.35s cubic-bezier(0.21, 1.02, 0.73, 1);
}
.arma-toast svg { width: 19px; height: 19px; flex-shrink: 0; }
.arma-toast--out { animation: armaToastOut 0.32s ease forwards; }
@keyframes armaToastIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes armaToastOut { to { opacity: 0; transform: translateY(10px); } }

/* Responsive: single column on narrow screens */
@media (max-width: 1100px) {
    .arma-admin__card-body { grid-template-columns: 1fr; column-gap: 0; }
}

/* ============ UI fixes ============ */

/* Generic full-width span (any marked block, any nesting level's parent) */
.arma-admin__card-body > .arma-wide { grid-column: 1 / -1; }

/* Wheel slices: comfortable full-width table with mobile scroll */
.arma-wheel-slices .arma-field-desc { max-width: 760px; }
.arma-table-scroll { overflow-x: auto; border-radius: 10px; }
.arma-slices-table { min-width: 780px; }
.arma-slices-table th { white-space: nowrap; }
.arma-slices-table input[type="text"],
.arma-slices-table input[type="number"],
.arma-slices-table select { width: 100%; min-width: 64px; }
</style>

<div class="arma-admin wrap">

    <?php if ($saved): ?>
        <div class="arma-toast" role="status" aria-live="polite">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span><?php esc_html_e('تنظیمات با موفقیت ذخیره شد.', 'arma-panel'); ?></span>
        </div>
        <script>
            (function () {
                setTimeout(function () {
                    var t = document.querySelector('.arma-toast');
                    if (!t) return;
                    t.classList.add('arma-toast--out');
                    setTimeout(function () { t.remove(); }, 350);
                }, 3800);
            })();
        </script>
    <?php endif; ?>

    <?php if (!empty($testSmsNotice)) echo $testSmsNotice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php if (!empty($chanceErrorNotice)) echo $chanceErrorNotice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div class="arma-admin__layout">

        <!-- Sidebar Navigation -->
        <aside class="arma-admin__sidebar">
            <p class="arma-admin__sidebar-title"><?php esc_html_e('بخش‌های تنظیمات', 'arma-panel'); ?></p>
            <nav class="arma-admin__nav">
                <?php foreach ($tabs as $key => $tab): ?>
                    <a href="<?php echo esc_url($currentPageUrl . '&tab=' . $key); ?>"
                       class="<?php echo $activeTab === $key ? 'is-active' : ''; ?>">
                        <?php echo $tab['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span><?php echo esc_html($tab['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Content -->
        <div class="arma-admin__main">
            <form method="post" action="" id="arma-settings-form">
                <?php wp_nonce_field('arma_save_settings', 'arma_settings_nonce'); ?>

                <div class="arma-admin__card">
                    <div class="arma-admin__card-header">
                        <div>
                            <h2 class="arma-admin__card-title"><?php echo esc_html($tabs[$activeTab]['label'] ?? __('تنظیمات', 'arma-panel')); ?></h2>
                            <p class="arma-admin__card-hint"><?php echo esc_html($tabs[$activeTab]['hint'] ?? ''); ?></p>
                        </div>
                    </div>

                    <div class="arma-admin__card-body">

                        <!-- 1. GENERAL -->
                        <?php if ($activeTab === 'general'): ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label" for="panel_slug"><?php esc_html_e('نامک آدرس پنل (Slug)', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('مسیر دسترسی اختصاصی کاربران به داشبورد', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="panel_slug" type="text" id="panel_slug" value="<?php echo esc_attr($settings->get('general.panel_slug', 'arma-panel')); ?>" class="arma-input-text" style="direction: ltr; text-align: left;">
                                    <p class="arma-field-desc">
                                        <?php printf(esc_html__('آدرس پنل: %s', 'arma-panel'), '<code>' . esc_html(home_url('/') . $settings->get('general.panel_slug', 'arma-panel') . '/') . '</code>'); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label" for="panel_title"><?php esc_html_e('عنوان پنل', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('عنوان صفحه حساب کاربری در مرورگر و منوها (پیش‌فرض: حساب کاربری من)', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="panel_title" type="text" id="panel_title" value="<?php echo esc_attr($settings->get('general.panel_title', 'حساب کاربری من')); ?>" class="arma-input-text">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('الزام ورود پیش از خرید (سبد خرید و پرداخت)', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('وقتی فعال باشد، مهمان‌ها پیش از تکمیل خرید به صفحه ورود پیامکی هدایت می‌شوند؛ سبد خریدشان بعد از ورود حفظ و به همان صفحه بازگردانده می‌شوند. وقتی غیرفعال باشد، خرید مهمان آزاد است.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="force_login_checkout" type="checkbox" value="1" <?php checked((bool)$settings->get('woocommerce.force_login_checkout', false)); ?>>
                                        <span><?php esc_html_e('مشتری باید قبل از خرید وارد حساب کاربری شود (حفظ سبد خرید + بازگشت به همان صفحه).', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Uninstall data wipe — dangerous, off by default -->
                            <div class="arma-field-row" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;">
                                <div>
                                    <label class="arma-field-label" style="color:#991b1b;"><?php esc_html_e('حذف کامل داده‌ها هنگام حذف افزونه', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc" style="color:#991b1b;"><?php esc_html_e('هشدار: با فعال کردن این گزینه، وقتی افزونه را از وردپرس حذف کنید، تمام داده‌های آرما پنل برای همیشه پاک می‌شود: جدول‌های پایگاه داده (کدهای یکبار مصرف، تیکت‌ها، اطلاعیه‌ها)، تنظیمات، شماره موبایل و اطلاعات پروفایل کاربران و متاهای سفارش‌ها. اگر این گزینه خاموش باشد (پیش‌فرض)، هنگام حذف افزونه فقط داده‌های موقت و زمان‌بندی‌های خودکار پاک می‌شوند و هیچ داده‌ای از دست نمی‌رود.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color:#991b1b;">
                                        <input name="delete_data_on_uninstall" type="checkbox" value="1" <?php checked((bool)$settings->get('general.delete_data_on_uninstall', false)); ?>>
                                        <span><?php esc_html_e('بله، هنگام حذف افزونه همه داده‌های آرما پنل (تنظیمات، تیکت‌ها، اطلاعیه‌ها و متاهای کاربر/سفارش) کامل حذف شود.', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                        <!-- 2. APPEARANCE -->
                        <?php elseif ($activeTab === 'appearance'): ?>
                            <?php
                            $fontsUrl = ARMA_PANEL_URL . 'assets/fonts/';
                            $currentFont = (string) $settings->get('appearance.font_family', 'inherit');
                            ?>
                            <!-- Local preview fonts for the admin -->
                            <style>
                                @font-face{font-family:'Vazirmatn';src:url('<?php echo esc_url($fontsUrl . 'Vazirmatn.woff2'); ?>') format('woff2-variations');font-weight:100 900;font-display:swap;}
                                @font-face{font-family:'Shabnam';src:url('<?php echo esc_url($fontsUrl . 'Shabnam.woff2'); ?>') format('woff2');font-weight:400;font-display:swap;}
                            </style>

                            <!-- Font Selector -->
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('فونت داشبورد کاربری', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('وزیرمتن و شبنم به‌صورت داخلی در افزونه قرار دارند؛ ایران‌یکان و یکان‌بخ تجاری هستند و باید در پوسته موجود باشند یا از طریق فونت سفارشی بارگذاری شوند.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <div class="arma-font-pick">
                                        <select name="font_family" id="arma-font-family" class="arma-select" onchange="armaToggleCustomFont(this.value)">
                                            <option value="inherit" <?php selected($currentFont, 'inherit'); ?>><?php esc_html_e('ارث‌بری از پوسته فعال سایت (Theme Font)', 'arma-panel'); ?></option>
                                            <option value="vazirmatn" <?php selected($currentFont, 'vazirmatn'); ?>><?php esc_html_e('وزیرمتن (Vazirmatn) — همراه افزونه', 'arma-panel'); ?></option>
                                            <option value="shabnam" <?php selected($currentFont, 'shabnam'); ?>><?php esc_html_e('شبنم (Shabnam) — همراه افزونه', 'arma-panel'); ?></option>
                                            <option value="iranyekan" <?php selected($currentFont, 'iranyekan'); ?>><?php esc_html_e('ایران‌یکان (IRANYekan)', 'arma-panel'); ?></option>
                                            <option value="yekanbakh" <?php selected($currentFont, 'yekanbakh'); ?>><?php esc_html_e('یکان‌بخ (YekanBakh)', 'arma-panel'); ?></option>
                                            <option value="custom" <?php selected($currentFont, 'custom'); ?>><?php esc_html_e('فونت سفارشی دلخواه (Custom Font)', 'arma-panel'); ?></option>
                                        </select>
                                    </div>
                                    <p class="arma-font-preview-line" id="arma-font-preview"><?php esc_html_e('پیش‌نمایش فونت: حساب کاربری من — ۱۲۳۴۵۶۷۸۹۰', 'arma-panel'); ?></p>
                                </div>
                            </div>

                            <!-- Custom Font: name + URL + media library -->
                            <div id="arma-custom-font-fields" class="arma-wide" style="display: <?php echo $currentFont === 'custom' ? 'block' : 'none'; ?>; background: #f8fafc; padding: 16px 20px; border-radius: 12px; margin: 4px 0 18px; border: 1px solid #e2e8f0;">
                                <div class="arma-field-row" style="border: none; padding: 8px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('نام فونت سفارشی', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <input name="custom_font_name" id="arma-custom-font-name" type="text" value="<?php echo esc_attr($settings->get('appearance.custom_font_name', '')); ?>" placeholder="MyFont" class="arma-input-text" style="direction: ltr;">
                                    </div>
                                </div>
                                <div class="arma-field-row" style="border: none; padding: 8px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('فایل فونت (Woff2/Woff/Ttf)', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('آدرس فایل را وارد کنید، از کتابخانه رسانه وردپرس انتخاب کنید یا فایل فونت را مستقیماً بارگذاری نمایید.', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <input name="custom_font_url" id="arma-custom-font-url" type="text" value="<?php echo esc_attr($settings->get('appearance.custom_font_url', '')); ?>" placeholder="https://site.com/wp-content/uploads/fonts/myfont.woff2" class="arma-input-text" style="direction: ltr;">
                                        <p style="margin: 10px 0 0;">
                                            <button type="button" class="button" id="arma-pick-font-btn"><?php esc_html_e('انتخاب / بارگذاری فایل فونت از کتابخانه رسانه', 'arma-panel'); ?></button>
                                        </p>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 12px 0 0; margin-top: 6px; border-top: 1px dashed #e2e8f0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('فونت وزیر لوکال برای صفحه ورود/ثبت‌نام', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('فونت «وزیرمتن» به‌صورت لوکال داخل خود افزونه بسته‌بندی شده است؛ با فعال بودن این گزینه، تمام متن‌ها و فیلدهای صفحه ورود پیامکی (شماره موبایل و کد تایید) با این فونت نمایش داده می‌شوند. فونت فقط در همین صفحه و تنها در صورت فعال بودن بارگذاری می‌شود (یک فایل بهینه woff2) تا سرعت سایت حفظ شود.', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input name="login_font_enabled" type="checkbox" value="1" <?php checked((bool)$settings->get('appearance.login_font_enabled', true)); ?>>
                                            <span><?php esc_html_e('بارگذاری فونت وزیر لوکال در صفحه ورود/ثبت‌نام.', 'arma-panel'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Logo (Login/OTP page only) -->
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('لوگوی صفحه ورود و ثبت‌نام', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('صرفاً در صفحه فرم ورود پیامکی نمایش داده می‌شود.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <?php
                                    $logoId = (int) $settings->get('appearance.logo_id', 0);
                                    $logoUrl = $logoId > 0 && function_exists('wp_get_attachment_image_url') ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';
                                    ?>
                                    <div id="arma-logo-preview" style="margin-bottom: 10px;">
                                        <?php if (!empty($logoUrl)): ?>
                                            <img src="<?php echo esc_url($logoUrl); ?>" style="max-height: 50px; max-width: 180px;">
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="logo_id" id="arma-logo-id" value="<?php echo esc_attr((string)$logoId); ?>">
                                    <button type="button" class="button" id="arma-upload-logo-btn"><?php esc_html_e('انتخاب تصویر لوگو', 'arma-panel'); ?></button>
                                    <button type="button" class="button" id="arma-remove-logo-btn" style="<?php echo empty($logoUrl) ? 'display:none;' : ''; ?>"><?php esc_html_e('حذف', 'arma-panel'); ?></button>
                                </div>
                            </div>

                            <?php
                            $colorSections = [
                                [
                                    'title' => __('رنگ‌های سراسری', 'arma-panel'),
                                    'desc'  => __('رنگ اصلی، متن‌ها و پس‌زمینه کل حساب کاربری مشتری — لینک‌ها و آیتم فعال منو به‌صورت خودکار از «رنگ اصلی» پیروی می‌کنند', 'arma-panel'),
                                    'fields' => [
                                        'primary_color'    => [__('رنگ اصلی', 'arma-panel'), '#e11d48'],
                                        'secondary_color'  => [__('رنگ دوم (آیکون‌ها)', 'arma-panel'), '#475569'],
                                        'background_color' => [__('پس‌زمینه صفحه', 'arma-panel'), '#f4f5f8'],
                                        'welcome_bg'       => [__('کارت خوش‌آمدگویی', 'arma-panel'), '#f1ece2'],
                                        'text_color'       => [__('متن اصلی', 'arma-panel'), '#1e293b'],
                                        'heading_color'    => [__('عنوان‌ها', 'arma-panel'), '#0f172a'],
                                    ],
                                ],
                                [
                                    'title' => __('نوار کناری (سایدبار)', 'arma-panel'),
                                    'desc'  => __('رنگ‌بندی کامل منوی کناری حساب کاربری مشتری', 'arma-panel'),
                                    'fields' => [
                                        'sidebar_bg'         => [__('پس‌زمینه سایدبار', 'arma-panel'), '#ffffff'],
                                        'sidebar_text'       => [__('متن آیتم‌های منو', 'arma-panel'), '#475569'],
                                        'sidebar_hover_bg'   => [__('هاور آیتم‌ها', 'arma-panel'), '#f8fafc'],
                                        'sidebar_border'     => [__('حاشیه سایدبار', 'arma-panel'), '#f1f5f9'],
                                    ],
                                ],
                                [
                                    'title' => __('کارت‌ها و فیلدهای فرم', 'arma-panel'),
                                    'desc'  => __('ظاهر کارت‌ها، کادرهای ورودی و دکمه‌ها', 'arma-panel'),
                                    'fields' => [
                                        'card_color'   => [__('پس‌زمینه کارت‌ها', 'arma-panel'), '#ffffff'],
                                        'border_color' => [__('حاشیه کارت‌ها', 'arma-panel'), '#f1f5f9'],
                                        'input_bg'     => [__('پس‌زمینه فیلدها', 'arma-panel'), '#ffffff'],
                                        'input_border' => [__('حاشیه فیلدها', 'arma-panel'), '#cbd5e1'],
                                        'button_text'  => [__('متن دکمه اصلی', 'arma-panel'), '#ffffff'],
                                    ],
                                ],
                                [
                                    'title' => __('رنگ‌های وضعیت', 'arma-panel'),
                                    'desc'  => __('وضعیت سفارش‌ها، اعلان‌ها و هشدارها', 'arma-panel'),
                                    'fields' => [
                                        'success_color' => [__('موفقیت', 'arma-panel'), '#10b981'],
                                        'warning_color' => [__('هشدار', 'arma-panel'), '#f59e0b'],
                                        'danger_color'  => [__('خطا / لغو', 'arma-panel'), '#ef4444'],
                                    ],
                                ],
                            ];
                            ?>

                            <?php foreach ($colorSections as $section): ?>
                                <div class="arma-color-section">
                                    <h3 class="arma-color-section__title">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"></circle><circle cx="19" cy="13" r="2.5"></circle><circle cx="6" cy="12" r="2.5"></circle><circle cx="10" cy="20" r="2.5"></circle><path d="M12 2a10 10 0 0 0 0 20"></path></svg>
                                        <?php echo esc_html($section['title']); ?>
                                    </h3>
                                    <p class="arma-color-section__desc"><?php echo esc_html($section['desc']); ?></p>
                                    <div class="arma-color-grid">
                                        <?php foreach ($section['fields'] as $fieldKey => $field): ?>
                                            <div class="arma-color-item">
                                                <label for="arma-color-<?php echo esc_attr($fieldKey); ?>"><?php echo esc_html($field[0]); ?></label>
                                                <input type="color" id="arma-color-<?php echo esc_attr($fieldKey); ?>"
                                                       name="<?php echo esc_attr($fieldKey); ?>"
                                                       data-default="<?php echo esc_attr($field[1]); ?>"
                                                       value="<?php echo esc_attr((string) $settings->get('appearance.' . $fieldKey, $field[1])); ?>">
                                                <span class="arma-color-code" data-code-for="<?php echo esc_attr($fieldKey); ?>"><?php echo esc_html((string) $settings->get('appearance.' . $fieldKey, $field[1])); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Radius (گردی گوشه‌ها) -->
                            <div class="arma-color-section" style="margin-top: 18px;">
                                <h3 class="arma-color-section__title">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"></rect></svg>
                                    <?php esc_html_e('گردی گوشه‌ها (Radius)', 'arma-panel'); ?>
                                </h3>
                                <p class="arma-color-section__desc"><?php esc_html_e('میزان گردی گوشه کارت‌ها، فیلدها و دکمه‌ها بر حسب پیکسل', 'arma-panel'); ?></p>
                                <div class="arma-radius-grid" style="padding: 0 4px 14px;">
                                    <div class="arma-radius-item">
                                        <input type="number" min="0" max="40" name="card_radius" value="<?php echo esc_attr((string)$settings->get('appearance.card_radius', 16)); ?>">
                                        <span><?php esc_html_e('کارت‌ها', 'arma-panel'); ?></span>
                                    </div>
                                    <div class="arma-radius-item">
                                        <input type="number" min="0" max="40" name="input_radius" value="<?php echo esc_attr((string)$settings->get('appearance.input_radius', 12)); ?>">
                                        <span><?php esc_html_e('فیلدهای فرم', 'arma-panel'); ?></span>
                                    </div>
                                    <div class="arma-radius-item">
                                        <input type="number" min="0" max="40" name="button_radius" value="<?php echo esc_attr((string)$settings->get('appearance.button_radius', 12)); ?>">
                                        <span><?php esc_html_e('دکمه‌ها', 'arma-panel'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <p class="arma-wide" style="margin-top: 16px;">
                                <button type="button" class="arma-btn-ghost" id="arma-reset-colors"><?php esc_html_e('بازگردانی همه رنگ‌ها به حالت پیش‌فرض', 'arma-panel'); ?></button>
                            </p>

                        <!-- 3. AUTHENTICATION (OTP) -->
                        <?php elseif ($activeTab === 'authentication'): ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('شناسه پترن ورود و ثبت‌نام', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('شناسه الگوی پیامک تایید شده در پنل پیامک حاوی متغیر کد OTP. فرمت آن به سرویس‌دهنده بستگی دارد: ملی پیامک / SMS.ir = کد عددی (مثال: 406323) — کاوه‌نگار = نام قالب (مثال: LoginOtp) — فراز اس‌ام‌اس = کد پترن (مثال: SJ3FgPrE0C) — آی‌پی‌پنل = کد پترن (مثال: zcxxc465465zxc).', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="otp_pattern" type="text" inputmode="latin" value="<?php echo esc_attr((string)($settings->get('authentication.otp_pattern') ?: $settings->get('sms.otp_pattern', ''))); ?>" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                    <span data-providers="smsir" class="arma-param-names" style="<?php echo ($activeProvider === 'smsir') ? '' : 'display:none;'; ?>">
                                        <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر قالب SMS.ir (بدون #، به ترتیب)</label>
                                        <input name="smsir_otp_params" type="text" value="<?php echo esc_attr((string)$settings->get('authentication.smsir_otp_params', '')); ?>" placeholder="Code" class="arma-input-text" style="direction: ltr; text-align: left;">
                                    </span>
                                    <span data-providers="faraz" class="arma-param-names" style="<?php echo ($activeProvider === 'faraz') ? '' : 'display:none;'; ?>">
                                        <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام متغیر پترن فراز (بدون %، به ترتیب)</label>
                                        <input name="faraz_otp_params" type="text" value="<?php echo esc_attr((string)$settings->get('authentication.faraz_otp_params', '')); ?>" placeholder="code" class="arma-input-text" style="direction: ltr; text-align: left;">
                                    </span>
                                </div>
                            </div>

                            <!-- Anti abuse: global IP throttle + trusted proxy -->
                            <?php
                            /*
                             * CDN / reverse-proxy warning: when the trusted-proxy
                             * toggle is OFF but the request carries proxy headers
                             * (or PHP's direct peer is a private IP), every visitor
                             * shares ONE throttle bucket — the warning says so.
                             */
                            $armaTrustedProxy = (bool) $settings->get('authentication.trusted_proxy', false);
                            if (!$armaTrustedProxy && \Arma\ArmaPanel\Support\ProxyDetector::isBehindProxy()) :
                                ?>
                                <div class="arma-field-row" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;">
                                    <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                        <span style="font-size:1.25rem;line-height:1.4;">⚠️</span>
                                        <div>
                                            <p class="arma-field-desc" style="margin:0;font-weight:700;color:#92400e;">
                                                <?php echo esc_html(\Arma\ArmaPanel\Support\ProxyDetector::warningMessage()); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('سایت پشت پروکسی مورد اعتماد است', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('اگر سایت شما پشت CDN یا پروکسی معکوس (مثل کلودفلر) قرار دارد، IP واقعی کاربران فقط از طریق هدرهایی مثل X-Forwarded-For یا CF-Connecting-IP در دسترس است. با فعال کردن این گزینه، محدودیت‌های IP افزونه به‌جای IP سرور پروکسی، از همان IP واقعی کاربر استفاده می‌کنند. هشدار: این هدرها در درخواست مستقیم قابل جعل هستند؛ این گزینه را فقط زمانی فعال کنید که مطمئنید پروکسی/CDN شما این هدرها را برای همه درخواست‌ها بازنویسی می‌کند، وگرنه مهاجم می‌تواند با جعل هدر از محدودیت IP عبور کند.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:0.85rem;">
                                        <input type="checkbox" name="trusted_proxy" <?php checked($armaTrustedProxy); ?>>
                                        <span><?php esc_html_e('هدرهای IP پروکسی مورد اعتماد است (X-Forwarded-For / CF-Connecting-IP)', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('محدودیت ارسال کد بر اساس IP', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('سقف کلی درخواست‌های ارسال کد پیامک از هر IP در بازه زمانی مشخص؛ جلوی هدررفتن اعتبار پیامک با شماره‌های تصادفی را می‌گیرد.', 'arma-panel'); ?></p>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <input name="ip_rate_max" type="number" min="0" max="100" value="<?php echo esc_attr((string)$settings->get('authentication.ip_rate_max', 5)); ?>" class="arma-input-text" style="max-width:90px;direction:ltr;text-align:center;">
                                    <span style="font-size:0.8rem;color:#64748b;"><?php esc_html_e('درخواست، هر', 'arma-panel'); ?></span>
                                    <input name="ip_rate_window" type="number" min="60" max="86400" step="60" value="<?php echo esc_attr((string)$settings->get('authentication.ip_rate_window', 600)); ?>" class="arma-input-text" style="max-width:110px;direction:ltr;text-align:center;">
                                    <span style="font-size:0.8rem;color:#64748b;"><?php esc_html_e('ثانیه (پیش‌فرض: ۵ درخواست در ۱۰ دقیقه — صفر = بدون محدودیت)', 'arma-panel'); ?></span>
                                </div>
                            </div>

                            <!-- verify-endpoint IP throttle -->
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('محدودیت تلاش برای تایید کد بر اساس IP', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('سقف درخواست‌های «تایید کد» از هر IP در بازه زمانی مشخص؛ جلوی حدس زدن کد به‌صورت گروهی را می‌گیرد. (پیش‌فرض: ۲۰ تلاش در ۱۰ دقیقه)', 'arma-panel'); ?></p>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <input name="verify_ip_max" type="number" min="0" max="500" value="<?php echo esc_attr((string)$settings->get('authentication.verify_ip_max', 20)); ?>" class="arma-input-text" style="max-width:90px;direction:ltr;text-align:center;">
                                    <span style="font-size:0.8rem;color:#64748b;"><?php esc_html_e('تلاش، هر', 'arma-panel'); ?></span>
                                    <input name="verify_ip_window" type="number" min="60" max="86400" step="60" value="<?php echo esc_attr((string)$settings->get('authentication.verify_ip_window', 600)); ?>" class="arma-input-text" style="max-width:110px;direction:ltr;text-align:center;">
                                    <span style="font-size:0.8rem;color:#64748b;"><?php esc_html_e('ثانیه (صفر = بدون محدودیت)', 'arma-panel'); ?></span>
                                </div>
                            </div>

                            <!-- OTP-allowed roles -->
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('نقش‌های مجاز ورود با OTP', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('فقط حساب‌هایی که دست‌کم یکی از این نقش‌ها را داشته باشند می‌توانند با پیامک وارد شوند. نقش‌های مدیریتی (مدیر کل، مدیر فروشگاه، ویراستار) همیشه و تحت هر شرایطی از ورود با پیامک منع شده‌اند.', 'arma-panel'); ?></p>
                                </div>
                                <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                                    <?php
                                    $otpAllowedRoles = (array) $settings->get('authentication.otp_allowed_roles', ['customer', 'subscriber']);
                                    $otpRoleChoices  = [
                                        'customer'    => 'مشتری (customer)',
                                        'subscriber'  => 'مشترک (subscriber)',
                                        'contributor' => 'مشارکت‌کننده (contributor)',
                                        'author'      => 'نویسنده (author)',
                                    ];
                                    foreach ($otpRoleChoices as $roleKey => $roleLabel) : ?>
                                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;">
                                            <input type="checkbox" name="otp_allowed_roles[]" value="<?php echo esc_attr($roleKey); ?>" <?php checked(in_array($roleKey, $otpAllowedRoles, true)); ?>>
                                            <?php echo esc_html($roleLabel); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('طول کد یکبار مصرف (OTP)', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('تعداد ارقام کد ارسالی برای کاربر', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="otp_length" type="number" min="4" max="8" value="<?php echo esc_attr((string)$settings->get('authentication.otp_length', 5)); ?>" class="arma-input-text" style="max-width: 120px; direction: ltr;">
                                    <span class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('رقم (پیش‌فرض: ۵)', 'arma-panel'); ?></span>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('مدت اعتبار کد (ثانیه)', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="otp_expiry" type="number" min="60" max="600" value="<?php echo esc_attr((string)$settings->get('authentication.otp_expiry', 120)); ?>" class="arma-input-text" style="max-width: 120px; direction: ltr;">
                                    <span class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('ثانیه (پیش‌فرض: ۱۲۰)', 'arma-panel'); ?></span>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('فاصله مجاز ارسال مجدد (ثانیه)', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="otp_resend_delay" type="number" min="30" max="300" value="<?php echo esc_attr((string)$settings->get('authentication.otp_resend_delay', 60)); ?>" class="arma-input-text" style="max-width: 120px; direction: ltr;">
                                    <span class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('ثانیه (پیش‌فرض: ۶۰)', 'arma-panel'); ?></span>
                                </div>
                            </div>

                        <!-- 4. SMS GATEWAY -->
                        <?php elseif ($activeTab === 'sms'): ?>
                            <?php
                            /*
                             * Credentials encrypted with a PREVIOUS site key
                             * (salts changed / DB migrated) can no longer be
                             * decrypted — warn instead of silently sending
                             * with garbage credentials.
                             */
                            if ($settings->hasUndecryptableCredential()) :
                                ?>
                                <div class="arma-field-row" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;">
                                    <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                        <span style="font-size:1.25rem;line-height:1.4;">🔐</span>
                                        <div>
                                            <p class="arma-field-desc" style="margin:0;font-weight:700;color:#92400e;">
                                                <?php echo esc_html(__('اطلاعات پنل پیامک با کلید فعلی سایت قابل خواندن نیست؛ لطفاً دوباره وارد کنید.', 'arma-panel')); ?>
                                            </p>
                                            <p class="arma-field-desc" style="margin:6px 0 0;color:#92400e;">
                                                <?php esc_html_e('تا زمانی که اطلاعات پیامک دوباره وارد و ذخیره نشود، ارسال هر پیامکی متوقف است.', 'arma-panel'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('سرویس‌دهنده پیامک', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('با انتخاب هر سرویس، تنظیمات اختصاصی همان سرویس در ادامه نمایش داده می‌شود. شناسه پترن‌ها در تب‌های مربوطه وارد می‌شود و فرمت آن به سرویس بستگی دارد.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <select name="sms_provider" id="arma-sms-provider" class="arma-select" style="font-weight: 700;">
                                        <option value="melipayamak" <?php selected((string)$settings->get('sms.provider', 'melipayamak'), 'melipayamak'); ?>>ملی پیامک</option>
                                        <option value="kavenegar" <?php selected((string)$settings->get('sms.provider', 'melipayamak'), 'kavenegar'); ?>>کاوه‌نگار (Kavenegar)</option>
                                        <option value="smsir" <?php selected((string)$settings->get('sms.provider', 'melipayamak'), 'smsir'); ?>>SMS.ir</option>
                                        <option value="faraz" <?php selected((string)$settings->get('sms.provider', 'melipayamak'), 'faraz'); ?>>فراز اس‌ام‌اس (ایران‌پیامک)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="arma-provider-settings" data-provider="melipayamak">
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('نام کاربری', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="sms_username" type="text" value="<?php echo esc_attr($settings->get('sms.username', '')); ?>" placeholder="مثال: 09333506405" class="arma-input-text" style="direction: ltr; text-align: left;">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('رمز عبور', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="sms_password" type="password" placeholder="<?php echo !empty($settings->get('sms.password')) ? '••••••••' : ''; ?>" class="arma-input-text" style="direction: ltr; text-align: left;">
                                    <p class="arma-field-desc"><?php esc_html_e('در صورت عدم تمایل به تغییر، خالی بگذارید.', 'arma-panel'); ?></p><p class="arma-field-desc" style="margin-top:6px; font-weight:700; color:#16a34a;"><?php echo !empty($settings->get('sms.password')) ? '✔ رمز عبور ذخیره شده است — برای تغییر، مقدار جدید را وارد و ذخیره کنید.' : 'هنوز رمز عبور ثبت نشده است.'; ?></p>
                                </div>
                            </div>
                            </div><!-- /melipayamak -->

                            <div class="arma-provider-settings" data-provider="kavenegar">
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('کلید API کاوه‌نگار', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('مخصوص سرویس کاوه‌نگار: کلید API را از پنل کاوه‌نگار (بخش حساب کاربری / API Key) کپی کنید. شناسه پترن‌ها در این سرویس «نام قالب» هستند (مثل LoginOtp) و در فیلدهای پترن همین تنظیمات وارد می‌شوند.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="kavenegar_api_key" type="password" placeholder="<?php echo !empty($settings->get('sms.kavenegar_api_key')) ? '••••••••••••••••' : ''; ?>" class="arma-input-text" style="direction: ltr; text-align: left;" autocomplete="new-password">
                                    <p class="arma-field-desc"><?php esc_html_e('خالی بماند = حفظ مقدار قبلی. این کلید به‌صورت رمزنگاری‌شده ذخیره می‌شود.', 'arma-panel'); ?></p><p class="arma-field-desc" style="margin-top:6px; font-weight:700; color:#16a34a;"><?php echo !empty($settings->get('sms.kavenegar_api_key')) ? '✔ کلید API ذخیره شده است — برای تغییر، مقدار جدید را وارد و ذخیره کنید.' : 'هنوز کلید API ثبت نشده است.'; ?></p>
                                </div>
                            </div>
                            </div><!-- /kavenegar -->

                            <div class="arma-provider-settings" data-provider="smsir">
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('کلید API سرویس SMS.ir', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('مخصوص سرویس SMS.ir: کلید API را از پنل SMS.ir (بخش توسعه‌دهندگان / کلیدهای API) کپی کنید. شناسه پترن‌ها در این سرویس «شناسه عددی قالب» هستند و در فیلدهای پترن وارد می‌شوند.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="smsir_api_key" type="password" placeholder="<?php echo !empty($settings->get('sms.smsir_api_key')) ? '••••••••••••••••' : ''; ?>" class="arma-input-text" style="direction: ltr; text-align: left;" autocomplete="new-password">
                                    <p class="arma-field-desc"><?php esc_html_e('خالی بماند = حفظ مقدار قبلی. این کلید به‌صورت رمزنگاری‌شده ذخیره می‌شود.', 'arma-panel'); ?></p><p class="arma-field-desc" style="margin-top:6px; font-weight:700; color:#16a34a;"><?php echo !empty($settings->get('sms.smsir_api_key')) ? '✔ کلید API ذخیره شده است — برای تغییر، مقدار جدید را وارد و ذخیره کنید.' : 'هنوز کلید API ثبت نشده است.'; ?></p>
                                </div>
                            </div>
                            </div><!-- /smsir -->

                            <div class="arma-provider-settings" data-provider="faraz">
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('کلید Api-Key فراز اس‌ام‌اس', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('مخصوص سرویس فراز اس‌ام‌اس (ایران‌پیامک): «کلید API ایستا» را از پنل فراز (بخش مدیریت کلیدها) کپی کنید — هرگز توکن لاگین را وارد نکنید. شناسه پترن‌ها در این سرویس «کد پترن» هستند (مثل SJ3FgPrE0C).', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="faraz_api_key" type="password" placeholder="<?php echo !empty($settings->get('sms.faraz_api_key')) ? '••••••••••••••••' : ''; ?>" class="arma-input-text" style="direction: ltr; text-align: left;" autocomplete="new-password">
                                    <p class="arma-field-desc"><?php esc_html_e('خالی بماند = حفظ مقدار قبلی. این کلید به‌صورت رمزنگاری‌شده ذخیره می‌شود.', 'arma-panel'); ?></p><p class="arma-field-desc" style="margin-top:6px; font-weight:700; color:#16a34a;"><?php echo !empty($settings->get('sms.faraz_api_key')) ? '✔ کلید API ذخیره شده است — برای تغییر، مقدار جدید را وارد و ذخیره کنید.' : 'هنوز کلید API ثبت نشده است.'; ?></p>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('شماره خط فرستنده فراز اس‌ام‌اس', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('شماره خط (سرویس) فعال خود در پنل فراز اس‌ام‌اس؛ مثال: 90008361. ارسال پترن بدون این شماره انجام نمی‌شود.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="faraz_line_number" type="text" value="<?php echo esc_attr((string)$settings->get('sms.faraz_line_number', '')); ?>" placeholder="90008361" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                </div>
                            </div>
                            </div><!-- /faraz -->

                            <!-- Test SMS Box -->
                            <div class="arma-sms-test-box">
                                <h4 style="margin: 0 0 12px; font-size: 0.95rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('تست ارسال پیامک', 'arma-panel'); ?></h4>
                                <div class="arma-sms-test-bar">
                                    <input type="tel" name="test_mobile" placeholder="<?php esc_attr_e('شماره موبایل', 'arma-panel'); ?>" class="arma-input-text" style="direction: ltr; text-align: left;">
                                    <input type="hidden" name="test_pattern" value="<?php echo esc_attr((string)($settings->get('authentication.otp_pattern') ?: $settings->get('sms.otp_pattern', 0))); ?>">
                                    <button type="submit" name="arma_send_test_sms" value="1" class="arma-btn-test-sms">
                                        <?php esc_html_e('ارسال پیامک تست', 'arma-panel'); ?>
                                    </button>
                                </div>
                                <p style="color: #64748b; font-size: 0.8rem; margin: 12px 0 0; line-height: 1.6;">
                                    <?php esc_html_e('توجه: در صورت فعال بودن حالت دمو ورود و ثبت‌نام، پیامکی ارسال نمی‌شود. لطفاً از غیرفعال بودن حالت دمو ورود و ثبت‌نام اطمینان حاصل فرمایید.', 'arma-panel'); ?>
                                    <?php esc_html_e('نکته مهم: دکمه «ارسال پیامک تست» تنظیمات را ذخیره نمی‌کند؛ اگر همین حالا کلید API یا تنظیم جدیدی وارد کرده‌اید، اول دکمه «ذخیره تنظیمات» را بزنید و بعد تست بگیرید.', 'arma-panel'); ?>
                                </p>
                            </div>

                        <!-- 5. WOOCOMMERCE SMS — three dedicated sections -->
                        <?php elseif ($activeTab === 'wc_sms'): ?>

                            <!-- ═══ بخش ۱: پیامک تایید سفارش ═══ -->
                            <div style="padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                                <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                                    <?php esc_html_e('۱) پیامک تایید سفارش', 'arma-panel'); ?>
                                </h4>
                                <p class="arma-field-desc" style="margin: 0 0 14px;">
                                    <?php esc_html_e('بعد از پرداخت موفق سفارش، پیامک الگو (پترن) برای مشتری ارسال می‌شود.', 'arma-panel'); ?>
                                </p>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('فعال‌سازی پیامک سفارش', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input name="wc_sms_enabled" type="checkbox" value="1" <?php checked((bool)$settings->get('wc_sms.enabled', true)); ?>>
                                            <span><?php esc_html_e('ارسال خودکار پیامک تایید سفارش فعال باشد.', 'arma-panel'); ?></span>
                                        </label>
                                    </div>
                                </div>

<div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('کد پترن پیامک سفارش', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('شناسه الگو (Body ID) در پنل ملی پیامک (متغیر اول: شماره سفارش)', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="order_pattern" type="text" inputmode="latin" value="<?php echo esc_attr((string)($settings->get('wc_sms.order_pattern') ?: $settings->get('sms.order_pattern', ''))); ?>" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                        <span data-providers="smsir" class="arma-param-names" style="<?php echo ($activeProvider === 'smsir') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر قالب SMS.ir (بدون #، به ترتیب)</label>
                                            <input name="smsir_order_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.smsir_order_params', '')); ?>" placeholder="OrderNumber" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>
                                        <span data-providers="faraz" class="arma-param-names" style="<?php echo ($activeProvider === 'faraz') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر پترن فراز (بدون %، به ترتیب)</label>
                                            <input name="faraz_order_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.faraz_order_params', '')); ?>" placeholder="order" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>                                </div>
                            </div>

<div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('وضعیت راه‌انداز ارسال', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <?php $trigger = (string) $settings->get('wc_sms.trigger_status', 'processing'); ?>
                                    <select name="trigger_status" class="arma-select">
                                        <option value="processing" <?php selected($trigger, 'processing'); ?>><?php esc_html_e('در حال پردازش (پرداخت موفق)', 'arma-panel'); ?></option>
                                        <option value="completed" <?php selected($trigger, 'completed'); ?>><?php esc_html_e('تکمیل شده (Completed)', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>
                            </div>

<div style="margin-top: 18px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                                <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                                    <?php esc_html_e('۲) پیامک ارسال و رهگیری مرسوله', 'arma-panel'); ?>
                                </h4>
                                <p class="arma-field-desc" style="margin: 0 0 14px;">
                                    <?php esc_html_e('در صفحه مدیریت هر سفارش (ووکامرس ← سفارش‌ها ← انتخاب سفارش) بخشی برای انتخاب روش حمل و ثبت کد رهگیری اضافه می‌شود؛ با ذخیره، اطلاعیه برای مشتری ثبت و در صورت فعال بودن پیامک، پیامک هم ارسال می‌شود.', 'arma-panel'); ?>
                                </p>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('پیامک رهگیری مرسوله', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input name="shipping_sms_enabled" type="checkbox" value="1" <?php checked((bool)$settings->get('wc_sms.shipping_sms_enabled', true)); ?>>
                                            <span><?php esc_html_e('ارسال پیامک رهگیری برای مشتری (در صورت غیرفعال بودن، فقط اطلاعیه پنل ثبت می‌شود).', 'arma-panel'); ?></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('کد پترن ارسال مرسوله', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('الگوی تاییدشده ملی‌پیامک با دو متغیر', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <input name="shipping_pattern" type="text" inputmode="latin" value="<?php echo esc_attr((string)$settings->get('wc_sms.shipping_pattern', 0)); ?>" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                        <span data-providers="smsir" class="arma-param-names" style="<?php echo ($activeProvider === 'smsir') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر قالب SMS.ir (بدون #، به ترتیب)</label>
                                            <input name="smsir_shipping_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.smsir_shipping_params', '')); ?>" placeholder="MethodName,TrackingCode" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>
                                        <span data-providers="faraz" class="arma-param-names" style="<?php echo ($activeProvider === 'faraz') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر پترن فراز (بدون %، به ترتیب)</label>
                                            <input name="faraz_shipping_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.faraz_shipping_params', '')); ?>" placeholder="method,code" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>                                        <p class="arma-field-desc" style="direction: rtl;">
                                            <code style="direction: rtl; unicode-bidi: plaintext;">مشتری عزیز سفارش شما توسط {0} ارسال شد ، کد رهگیری {1}</code><br>
                                            <?php esc_html_e('{0} = روش حمل (پست، تیپاکس و…) — {1} = کد رهگیری', 'arma-panel'); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('کد پترن «فقط کد رهگیری» (اختیاری)', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('وقتی روش حمل را خالی بگذارید و فقط کد رهگیری ثبت کنید، این پترن تک‌متغیره ارسال می‌شود. اگر پترن «ارسال مرسوله» را خالی بگذارید، این پترن همیشه (حتی با روش حمل) استفاده می‌شود.', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <input name="tracking_code_pattern" type="text" inputmode="latin" value="<?php echo esc_attr((string)$settings->get('wc_sms.tracking_code_pattern', 0)); ?>" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                        <span data-providers="smsir" class="arma-param-names" style="<?php echo ($activeProvider === 'smsir') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر قالب SMS.ir (بدون #، به ترتیب)</label>
                                            <input name="smsir_tracking_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.smsir_tracking_params', '')); ?>" placeholder="TrackingCode" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>
                                        <span data-providers="faraz" class="arma-param-names" style="<?php echo ($activeProvider === 'faraz') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر پترن فراز (بدون %، به ترتیب)</label>
                                            <input name="faraz_tracking_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.faraz_tracking_params', '')); ?>" placeholder="code" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>                                        <p class="arma-field-desc" style="direction: rtl;">
                                            <code style="direction: rtl; unicode-bidi: plaintext;">مشتری عزیز سفارش شما ارسال شد ، کد رهگیری {0}</code><br>
                                            <?php esc_html_e('{0} = کد رهگیری — اگر خالی بماند و روش حملی انتخاب نشود، پیامکی ارسال نمی‌شود (فقط اطلاعیه پنل).', 'arma-panel'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            

<div class="arma-wide" style="margin-top: 18px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                                <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 800; color: #0f172a;">
                                    <?php esc_html_e('۳) یادآوری سفارش در انتظار پرداخت', 'arma-panel'); ?>
                                </h4>
                                <p class="arma-field-desc" style="margin: 0 0 14px;">
                                    <?php esc_html_e('فقط برای کاربران ثبت‌نام‌شده‌ای که سفارششان را تا مرحله درگاه پرداخت پیش برده ولی پرداخت را کامل نکرده‌اند (وضعیت «در انتظار پرداخت/بررسی»). صرفِ افزودن محصول به سبد خرید پیامکی ندارد. هر مشتری در کل فقط یک بار پیامک یادآوری دریافت می‌کند.', 'arma-panel'); ?>
                                </p>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('فعال‌سازی یادآوری', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input name="abandoned_enabled" type="checkbox" value="1" <?php checked((bool)$settings->get('wc_sms.abandoned_enabled', true)); ?>>
                                            <span><?php esc_html_e('ارسال خودکار یادآوری و کد تخفیف برای سفارش‌های پرداخت‌نشده (فقط یک بار برای هر مشتری).', 'arma-panel'); ?></span>
                                        </label>
                                        <p class="arma-field-desc" style="margin-top: 6px;"><?php esc_html_e('شمارش ساعت یادآوری فقط از لحظه فعال‌سازی این گزینه شروع می‌شود؛ سفارش‌هایی که از قبل از فعال‌سازی در انتظار پرداخت بوده‌اند هرگز پیامک نمی‌گیرند. با خاموش و روشن کردن دوباره، شمارش از نو آغاز می‌شود.', 'arma-panel'); ?></p>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('درصد تخفیف یادآوری', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <input name="abandoned_percent" type="number" min="1" max="100" value="<?php echo esc_attr((string)$settings->get('wc_sms.abandoned_percent', 10)); ?>" class="arma-input-text" style="max-width: 100px; direction: ltr; text-align: center;">
                                        <span class="arma-field-desc" style="display: inline-block; margin-right: 6px;"><?php esc_html_e('٪', 'arma-panel'); ?></span>
                                    </div>
                                </div>

                                <!-- sweep statuses — pending only by default -->
                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('وضعیت‌های سفارش در بررسی یادآوری', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('به‌طور پیش‌فرض فقط سفارش‌های «در انتظار پرداخت» (pending) بررسی می‌شوند. در صورت نیاز می‌توانید وضعیت‌های زیر را هم اضافه کنید.', 'arma-panel'); ?></p>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;">
                                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;">
                                            <input type="checkbox" name="abandoned_include_failed" value="1" <?php checked((bool)$settings->get('wc_sms.abandoned_include_failed', false)); ?>>
                                            <span><?php esc_html_e('شامل سفارش‌های «ناموفق» (failed)', 'arma-panel'); ?></span>
                                        </label>
                                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;">
                                            <input type="checkbox" name="abandoned_include_onhold" value="1" <?php checked((bool)$settings->get('wc_sms.abandoned_include_onhold', false)); ?>>
                                            <span><?php esc_html_e('شامل سفارش‌های «در انتظار بررسی» (on-hold)', 'arma-panel'); ?></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('ارسال یادآوری بعد از (ساعت)', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('اگر سفارش مشتری تا این مدت در وضعیت «در انتظار پرداخت» (pending) باقی بماند، پیامک یادآوری و کد تخفیف ارسال می‌شود. بررسی فقط از طریق زمان‌بند وردپرس (WP-Cron) و به‌صورت ساعتی انجام می‌شود؛ پیش‌فرض ۲۴.', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <input name="abandoned_remind_hours" type="number" min="1" max="720" value="<?php echo esc_attr((string)$settings->get('wc_sms.abandoned_remind_hours', 24)); ?>" class="arma-input-text" style="max-width: 100px; direction: ltr;">
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('حداقل فاصله بین دو یادآوری برای یک مشتری', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('به هر مشتری در این بازه حداکثر یک پیامک یادآوری ارسال می‌شود؛ اگر بعد از گذشت این مدت دوباره سفارشش در انتظار پرداخت رها شود، دوباره یادآوری دریافت می‌کند. هر سفارش در کل فقط یک بار یادآوری می‌شود.', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <select name="abandoned_cooldown_days" class="arma-select" style="max-width: 160px;">
                                            <?php foreach ([7 => '۷ روز', 14 => '۱۴ روز', 30 => '۳۰ روز', 60 => '۶۰ روز', 90 => '۹۰ روز', 180 => '۱۸۰ روز', 365 => 'یک سال'] as $cd => $cdLabel): ?>
                                                <option value="<?php echo esc_attr((string) $cd); ?>" <?php selected((int) $settings->get('wc_sms.abandoned_cooldown_days', 30), $cd); ?>><?php echo esc_html($cdLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('انقضای کد یادآوری (روز)', 'arma-panel'); ?></label>
                                    </div>
                                    <div>
                                        <input name="abandoned_expiry_days" type="number" min="1" max="365" value="<?php echo esc_attr((string)$settings->get('wc_sms.abandoned_expiry_days', 7)); ?>" class="arma-input-text" style="max-width: 100px; direction: ltr;">
                                    </div>
                                </div>

                                <div class="arma-field-row" style="border: none; padding: 10px 0;">
                                    <div>
                                        <label class="arma-field-label"><?php esc_html_e('کد پترن یادآوری', 'arma-panel'); ?></label>
                                        <p class="arma-field-desc"><?php esc_html_e('الگوی تاییدشده ملی‌پیامک', 'arma-panel'); ?></p>
                                    </div>
                                    <div>
                                        <input name="abandoned_pattern" type="text" inputmode="latin" value="<?php echo esc_attr((string)$settings->get('wc_sms.abandoned_pattern', 0)); ?>" class="arma-input-text" style="direction: ltr; text-align: left; font-weight: 700;">
                                        <span data-providers="smsir" class="arma-param-names" style="<?php echo ($activeProvider === 'smsir') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر قالب SMS.ir (بدون #، به ترتیب)</label>
                                            <input name="smsir_abandoned_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.smsir_abandoned_params', '')); ?>" placeholder="CouponCode,Percent" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>
                                        <span data-providers="faraz" class="arma-param-names" style="<?php echo ($activeProvider === 'faraz') ? '' : 'display:none;'; ?>">
                                            <label class="arma-field-desc" style="display:block; margin-top:8px; font-weight:700;">نام پارامتر پترن فراز (بدون %، به ترتیب)</label>
                                            <input name="faraz_abandoned_params" type="text" value="<?php echo esc_attr((string)$settings->get('wc_sms.faraz_abandoned_params', '')); ?>" placeholder="coupon,percent" class="arma-input-text" style="direction: ltr; text-align: left;">
                                        </span>                                        <p class="arma-field-desc" style="direction: rtl;">
                                            <code style="direction: rtl; unicode-bidi: plaintext;">مشتری عزیز، سبد خرید شما منتظر است! با کد تخفیف {0} خرید خود را تکمیل کنید</code><br>
                                            <?php esc_html_e('{0} = کد تخفیف اختصاصی — {1} = درصد تخفیف (اختیاری)', 'arma-panel'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>


                        <!-- 6. LUCKY WHEEL -->
                        <?php elseif ($activeTab === 'lucky_wheel'): ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('فعال‌سازی گردونه شانس', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('امکان استفاده از گردونه برای خریداران', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="lucky_wheel_enabled" type="checkbox" value="1" <?php checked((bool)$settings->get('lucky_wheel.enabled', true)); ?>>
                                        <span><?php esc_html_e('ماژول گردونه شانس فعال باشد.', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('عنوان صفحه گردونه', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="wheel_title" type="text" value="<?php echo esc_attr($settings->get('lucky_wheel.title', 'گردونه شانس مشتریان')); ?>" class="arma-input-text">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('زیرعنوان صفحه گردونه', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="wheel_subtitle" type="text" value="<?php echo esc_attr($settings->get('lucky_wheel.subtitle', '')); ?>" class="arma-input-text">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('بنر جذب ثبت‌نام (گردونه شانس)', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('وقتی گردونه فعال باشد ولی ورود اجباری برای خرید غیرفعال باشد، در بالای سایت به مهمان‌ها بنر «با ثبت‌نام، گردونه شانس پس از خرید به شما تعلق می‌گیرد» نمایش داده می‌شود.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="wheel_show_banner" type="checkbox" value="1" <?php checked((bool)$settings->get('lucky_wheel.show_banner', true)); ?>>
                                        <span><?php esc_html_e('نمایش بنر تشویقی ثبت‌نام برای مهمان‌ها.', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- بنر: شخصی‌سازی متن و ظاهر -->
                            <div class="arma-wide" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin:4px 0 18px;">
                                <p style="margin:0 0 12px;font-size:0.82rem;color:#64748b;"><?php esc_html_e('متن و ظاهر بنر را مطابق سلیقه خود تغییر دهید (پیش‌فرض: بنر گردونه شانس).', 'arma-panel'); ?></p>
                                <div class="arma-field-row" style="border:none;padding:8px 0;">
                                    <div><label class="arma-field-label"><?php esc_html_e('متن بنر', 'arma-panel'); ?></label></div>
                                    <div>
                                        <textarea name="banner_text" rows="2" class="arma-input-text" style="max-width:520px;"><?php echo esc_textarea((string)$settings->get('lucky_wheel.banner_text', '')); ?></textarea>
                                    </div>
                                </div>
                                <div class="arma-field-row" style="border:none;padding:8px 0;">
                                    <div><label class="arma-field-label"><?php esc_html_e('متن دکمه', 'arma-panel'); ?></label></div>
                                    <div>
                                        <input name="banner_btn_text" type="text" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.banner_btn_text', '')); ?>" class="arma-input-text" style="max-width:320px;">
                                    </div>
                                </div>
                                <div class="arma-field-row" style="border:none;padding:8px 0;">
                                    <div><label class="arma-field-label"><?php esc_html_e('رنگ‌های بنر', 'arma-panel'); ?></label></div>
                                    <div style="display:flex;gap:14px;flex-wrap:wrap;">
                                        <span style="font-size:0.78rem;color:#475569;"><?php esc_html_e('شروع گرادیان:', 'arma-panel'); ?> <input type="color" name="banner_color1" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.banner_color1', '#0f172a')); ?>" style="width:44px;height:30px;border:none;cursor:pointer;"></span>
                                        <span style="font-size:0.78rem;color:#475569;"><?php esc_html_e('پایان گرادیان:', 'arma-panel'); ?> <input type="color" name="banner_color2" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.banner_color2', '#881337')); ?>" style="width:44px;height:30px;border:none;cursor:pointer;"></span>
                                        <span style="font-size:0.78rem;color:#475569;"><?php esc_html_e('رنگ دکمه:', 'arma-panel'); ?> <input type="color" name="banner_cta_color" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.banner_cta_color', '#fbbf24')); ?>" style="width:44px;height:30px;border:none;cursor:pointer;"></span>
                                        <span style="font-size:0.78rem;color:#475569;"><?php esc_html_e('رنگ متن دکمه:', 'arma-panel'); ?> <input type="color" name="banner_cta_text_color" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.banner_cta_text_color', '#451a03')); ?>" style="width:44px;height:30px;border:none;cursor:pointer;"></span>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="unique_coupons" value="1">
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('پیشوند کد کوپن', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('برای هر برنده به‌صورت خودکار یک کوپن یکتای یک‌بارمصرف ساخته می‌شود. نمونه: ARMA-X7K2P9QM', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <input name="coupon_prefix" type="text" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.coupon_prefix', 'ARMA')); ?>" class="arma-input-text" style="direction: ltr; text-align: left; max-width: 160px; text-transform: uppercase;">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('انقضای کوپن (روز)', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <input name="coupon_expiry_days" type="number" min="0" max="365" value="<?php echo esc_attr((string)$settings->get('lucky_wheel.coupon_expiry_days', 90)); ?>" class="arma-input-text" style="max-width: 120px; direction: ltr;">
                                    <span class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('۰ = بدون انقضا', 'arma-panel'); ?></span>
                                </div>
                            </div>

                                    <div class="arma-wide arma-wheel-slices" style="margin-top: 24px;">
                                        <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('تنظیمات بخش‌های گردونه و درصد شانس (Weighted Chance)', 'arma-panel'); ?></h4>
                                        <p class="arma-field-desc" style="margin-bottom: 16px;">
                                            <?php esc_html_e('درصد شانس هر آیتم بین ۰ تا ۱۰۰ است و مجموع شانس‌ها نباید از ۱۰۰ بیشتر شود — در غیر این صورت ذخیره امکان‌پذیر نیست. درصد ۰ یعنی آیتم روی گردونه نمایش داده می‌شود اما هرگز برنده نمی‌شود (مثلاً معرفی یک جایزه ویژه). اگر مجموع شانس‌ها کمتر از ۱۰۰ باشد، باقیمانده احتمال به‌صورت خودکار به بخش‌های «پوچ» می‌رسد. محاسبه برنده به‌صورت امن روی سرور انجام می‌شود.', 'arma-panel'); ?>
                                        </p>
                                        <p class="arma-field-desc" style="margin: -8px 0 16px;">
                                            <?php esc_html_e('نوع جایزه هر بخش یکی از سه حالت است: «تخفیف» (کد تخفیف درصدی یا مبلغ ثابت)، «پوچ» (بدون جایزه) و «ارسال رایگان» (کوپن ارسال رایگان خرید بعدی). فیلدهای نوع مبلغ و مقدار تخفیف فقط برای جایزه نوع «تخفیف» نمایش داده می‌شوند.', 'arma-panel'); ?>
                                        </p>

                                        <?php $slices = (array) $settings->get('lucky_wheel.slices', []); ?>
                                        <div class="arma-table-scroll"><table class="arma-slices-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('عنوان روی گردونه', 'arma-panel'); ?></th>
                                                    <th><?php esc_html_e('نوع جایزه', 'arma-panel'); ?></th>
                                                    <th><?php esc_html_e('مقدار تخفیف', 'arma-panel'); ?></th>
                                                    <th style="width: 110px;"><?php esc_html_e('درصد شانس (٪)', 'arma-panel'); ?></th>
                                                    <th style="width: 100px;"><?php esc_html_e('رنگ بخش', 'arma-panel'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($slices as $i => $sl):
                                                    // Normalize legacy slices (coupon + free_shipping + no
                                                    // amount == a pure free-shipping prize)
                                                    $sliceType = (string) ($sl['type'] ?? 'empty');
                                                    if ($sliceType === 'coupon' && !empty($sl['free_shipping']) && (float) ($sl['amount'] ?? 0) <= 0) {
                                                        $sliceType = 'free_shipping';
                                                    }
                                                    $isCoupon = ($sliceType === 'coupon');
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <input name="slices[<?php echo esc_attr((string)$i); ?>][label]" type="text" value="<?php echo esc_attr($sl['label'] ?? ''); ?>" class="arma-input-text" style="padding: 6px 10px;" required>
                                                        </td>
                                                        <td>
                                                            <select name="slices[<?php echo esc_attr((string)$i); ?>][type]" class="arma-select arma-slice-type" style="padding: 6px 10px;">
                                                                <option value="coupon" <?php selected($sliceType, 'coupon'); ?>><?php esc_html_e('تخفیف', 'arma-panel'); ?></option>
                                                                <option value="empty" <?php selected($sliceType, 'empty'); ?>><?php esc_html_e('پوچ', 'arma-panel'); ?></option>
                                                                <option value="free_shipping" <?php selected($sliceType, 'free_shipping'); ?>><?php esc_html_e('ارسال رایگان', 'arma-panel'); ?></option>
                                                            </select>
                                                            <select name="slices[<?php echo esc_attr((string)$i); ?>][discount_type]" class="arma-select arma-slice-coupon-only" style="padding: 6px 10px; margin-top: 4px; <?php echo $isCoupon ? '' : 'display:none;'; ?>">
                                                                <option value="percent" <?php selected($sl['discount_type'] ?? 'percent', 'percent'); ?>><?php esc_html_e('درصدی', 'arma-panel'); ?></option>
                                                                <option value="fixed_cart" <?php selected($sl['discount_type'] ?? 'percent', 'fixed_cart'); ?>><?php esc_html_e('مبلغ ثابت (تومان)', 'arma-panel'); ?></option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input name="slices[<?php echo esc_attr((string)$i); ?>][amount]" type="number" min="0" step="any" value="<?php echo esc_attr((string)($sl['amount'] ?? 0)); ?>" class="arma-input-text arma-slice-coupon-only" style="padding: 6px 10px; direction: ltr; text-align: center; max-width: 90px; <?php echo $isCoupon ? '' : 'display:none;'; ?>">
                                                        </td>
                                                        <td>
                                                            <input name="slices[<?php echo esc_attr((string)$i); ?>][chance]" type="number" min="0" max="100" value="<?php echo esc_attr((string)($sl['chance'] ?? 0)); ?>" class="arma-input-text arma-slice-chance" style="padding: 6px 10px; direction: ltr; text-align: center;">
                                                        </td>
                                                        <td>
                                                            <input name="slices[<?php echo esc_attr((string)$i); ?>][color]" type="color" value="<?php echo esc_attr($sl['color'] ?? '#f43f5e'); ?>" style="width: 40px; height: 32px; border: none; cursor: pointer; border-radius: 6px;">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table></div>
                                <p id="arma-chance-total" style="margin: 12px 0 0; font-size: 0.85rem; font-weight: 700; color: #475569;">
                                    <?php esc_html_e('مجموع شانس‌ها:', 'arma-panel'); ?> <span id="arma-chance-total-value">۰٪</span>
                                </p>
                                <p id="arma-chance-error" style="display: none; margin: 8px 0 0; font-size: 0.85rem; font-weight: 700; color: #b91c1c;">
                                    <?php esc_html_e('خطا: مجموع درصد شانس‌ها از ۱۰۰ بیشتر است! درصد‌ها را طوری تنظیم کنید که حداکثر ۱۰۰ شوند.', 'arma-panel'); ?>
                                </p>
                            </div>

                        <?php elseif ($activeTab === 'dashboard'): ?>
                            <?php
                            $dashNames = [
                                'welcome'         => 'کارت خوش‌آمدگویی',
                                'stats'           => 'کارت‌های آمار',
                                'quick'           => 'دسترسی سریع',
                                'recent_orders'   => 'آخرین سفارش‌ها',
                                'frequent_orders' => 'سفارش‌های پرتکرار',
                            ];
                            $dashSections = (array) $settings->get('dashboard.sections', []);
                            $dashTitles = [
                                'welcome'         => (string) $settings->get('dashboard.welcome_title', '{name} عزیز، خوش آمدید 🌸'),
                                'recent_orders'   => (string) $settings->get('dashboard.recent_orders_title', 'آخرین سفارش‌های من'),
                                'frequent_orders' => (string) $settings->get('dashboard.frequent_orders_title', 'پر تکرار ترین سفارش‌های من'),
                            ];
                            $dashHasTitle = ['welcome', 'recent_orders', 'frequent_orders'];
                            $statsShow = (array) $settings->get('dashboard.stats_show', ['orders_total', 'orders_active', 'tickets_open', 'unread']);
                            $statsLabels = [
                                'orders_total'  => 'کل سفارش‌ها',
                                'orders_active' => 'در حال پردازش',
                                'tickets_open'  => 'تیکت‌های باز',
                                'unread'        => 'خوانده‌نشده',
                            ];
                            $quickItemsConf = (array) $settings->get('dashboard.quick_items', []);
                            $quickDefaults = [
                                'orders'    => ['title' => 'سفارش‌های من',  'desc' => 'پیگیری و مشاهده جزئیات'],
                                'addresses' => ['title' => 'نشانی‌های من', 'desc' => 'مدیریت آدرس‌های ارسال'],
                                'tickets'   => ['title' => 'پشتیبانی',     'desc' => 'ارسال تیکت و پیگیری'],
                                'account'   => ['title' => 'جزئیات حساب',  'desc' => 'پروفایل و شخصی‌سازی'],
                            ];
                            ?>

                            <div class="arma-wide arma-wheel-slices">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('ترتیب و نمایش بخش‌های داشبورد', 'arma-panel'); ?></h4>
                                <p class="arma-field-desc" style="margin-bottom: 16px;">
                                    <?php esc_html_e('ترتیب نمایش بخش‌ها در صفحه اصلی پنل کاربری را با دکمه‌های بالا/پایین تغییر دهید و با برداشتن تیک، هر بخش را از داشبورد حذف کنید. ترتیب فعلی همان ترتیبی است که مشتری در پنل می‌بیند.', 'arma-panel'); ?>
                                </p>
                                <div class="arma-table-scroll"><table class="arma-slices-table" id="arma-dash-builder">
                                    <thead>
                                        <tr>
                                            <th style="width: 90px;"><?php esc_html_e('جابه‌جایی', 'arma-panel'); ?></th>
                                            <th style="width: 80px;"><?php esc_html_e('نمایش', 'arma-panel'); ?></th>
                                            <th><?php esc_html_e('بخش داشبورد', 'arma-panel'); ?></th>
                                            <th><?php esc_html_e('عنوان سفارشی', 'arma-panel'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dashSections as $dashSec): ?>
                                            <?php $dashId = (string) ($dashSec['id'] ?? ''); if ($dashId === '' || !isset($dashNames[$dashId])) { continue; } ?>
                                            <tr data-section="<?php echo esc_attr($dashId); ?>">
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <input type="hidden" name="dash_sections[]" value="<?php echo esc_attr($dashId); ?>">
                                                    <button type="button" class="button arma-dash-move" data-dir="up" title="<?php esc_attr_e('انتقال به بالا', 'arma-panel'); ?>" style="margin: 0 2px; padding: 2px 8px;">▲</button>
                                                    <button type="button" class="button arma-dash-move" data-dir="down" title="<?php esc_attr_e('انتقال به پایین', 'arma-panel'); ?>" style="margin: 0 2px; padding: 2px 8px;">▼</button>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="checkbox" name="dash_enabled[<?php echo esc_attr($dashId); ?>]" value="1" <?php checked(!empty($dashSec['enabled'])); ?>>
                                                </td>
                                                <td><?php echo esc_html($dashNames[$dashId]); ?></td>
                                                <td>
                                                    <?php if (in_array($dashId, $dashHasTitle, true)): ?>
                                                        <input name="dash_title[<?php echo esc_attr($dashId); ?>]" type="text" value="<?php echo esc_attr($dashTitles[$dashId] ?? ''); ?>" class="arma-input-text" style="padding: 6px 10px;<?php echo $dashId === 'welcome' ? ' direction: ltr; text-align: left;' : ''; ?>">
                                                        <?php if ($dashId === 'welcome'): ?>
                                                            <p class="arma-field-desc" style="margin: 4px 0 0;"><?php esc_html_e('متغیر {name} با نام کوچک کاربر جایگزین می‌شود.', 'arma-panel'); ?></p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table></div>
                            </div>

                            <div class="arma-field-row arma-wide" style="margin-top: 20px;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('متن‌ها و مدل نمایش کارت خوش‌آمدگویی', 'arma-panel'); ?></h4>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('متن توضیح خوش‌آمدگویی', 'arma-panel'); ?></label></div>
                                <div>
                                    <textarea name="dash_welcome_subtitle" rows="2" class="arma-input-text" style="max-width:520px;"><?php echo esc_textarea((string) $settings->get('dashboard.welcome_subtitle', '')); ?></textarea>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('متن دکمه فروشگاه', 'arma-panel'); ?></label></div>
                                <div>
                                    <input name="dash_welcome_cta" type="text" value="<?php echo esc_attr((string) $settings->get('dashboard.welcome_cta_text', 'مشاهده فروشگاه')); ?>" class="arma-input-text" style="max-width:320px;">
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('چیپ‌های وضعیت (سفارش/در انتظار/تیکت)', 'arma-panel'); ?></label></div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="dash_welcome_chips" type="checkbox" value="1" <?php checked((bool) $settings->get('dashboard.welcome_chips', true)); ?>>
                                        <span><?php esc_html_e('نمایش تعداد سفارش‌ها و تیکت‌های باز داخل کارت خوش‌آمدگویی', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row arma-wide" style="margin-top: 20px;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('مدل کارت‌های آمار', 'arma-panel'); ?></h4>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد ستون کارت‌های آمار', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="dash_stats_columns" class="arma-select" style="max-width: 180px;">
                                        <option value="2" <?php selected((int) $settings->get('dashboard.stats_columns', 4), 2); ?>><?php esc_html_e('۲ ستونه', 'arma-panel'); ?></option>
                                        <option value="3" <?php selected((int) $settings->get('dashboard.stats_columns', 4), 3); ?>><?php esc_html_e('۳ ستونه', 'arma-panel'); ?></option>
                                        <option value="4" <?php selected((int) $settings->get('dashboard.stats_columns', 4), 4); ?>><?php esc_html_e('۴ ستونه', 'arma-panel'); ?></option>
                                    </select>
                                    <p class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('در موبایل همیشه ۲ ستونه نمایش داده می‌شود.', 'arma-panel'); ?></p>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('کارت‌های قابل نمایش', 'arma-panel'); ?></label></div>
                                <div style="display: flex; gap: 18px; flex-wrap: wrap;">
                                    <?php foreach ($statsLabels as $statKey => $statLabel): ?>
                                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                                            <input type="checkbox" name="dash_stats_show[<?php echo esc_attr($statKey); ?>]" value="1" <?php checked(in_array($statKey, $statsShow, true)); ?>>
                                            <span><?php echo esc_html($statLabel); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="arma-field-row arma-wide" style="margin-top: 20px;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('دسترسی سریع', 'arma-panel'); ?></h4>
                                <p class="arma-field-desc" style="margin-bottom: 12px;"><?php esc_html_e('هر آیتم را می‌توانید مخفی کنید یا عنوان و توضیح آن را تغییر دهید.', 'arma-panel'); ?></p>
                            </div>
                            <div class="arma-wide"><div class="arma-table-scroll"><table class="arma-slices-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;"><?php esc_html_e('نمایش', 'arma-panel'); ?></th>
                                        <th><?php esc_html_e('آیتم', 'arma-panel'); ?></th>
                                        <th><?php esc_html_e('عنوان', 'arma-panel'); ?></th>
                                        <th><?php esc_html_e('توضیح', 'arma-panel'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quickDefaults as $quickKey => $quickDefault): ?>
                                        <?php $quickConf = (array) ($quickItemsConf[$quickKey] ?? []); ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="dash_quick_enabled[<?php echo esc_attr($quickKey); ?>]" value="1" <?php checked(!isset($quickConf['enabled']) || !empty($quickConf['enabled'])); ?>>
                                            </td>
                                            <td><?php echo esc_html($quickDefault['title']); ?></td>
                                            <td>
                                                <input name="dash_quick_title[<?php echo esc_attr($quickKey); ?>]" type="text" value="<?php echo esc_attr((string) ($quickConf['title'] ?? $quickDefault['title'])); ?>" class="arma-input-text" style="padding: 6px 10px;">
                                            </td>
                                            <td>
                                                <input name="dash_quick_desc[<?php echo esc_attr($quickKey); ?>]" type="text" value="<?php echo esc_attr((string) ($quickConf['desc'] ?? $quickDefault['desc'])); ?>" class="arma-input-text" style="padding: 6px 10px;">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table></div></div>

                            <div class="arma-field-row arma-wide" style="margin-top: 20px;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('مدل و تعداد کارت‌های سفارش', 'arma-panel'); ?></h4>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('چیدمان کارت‌های سفارش', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="dash_cards_layout" class="arma-select" style="max-width: 220px;">
                                        <option value="side" <?php selected((string) $settings->get('dashboard.cards_layout', 'side'), 'side'); ?>><?php esc_html_e('کنار هم (دو ستونه)', 'arma-panel'); ?></option>
                                        <option value="stacked" <?php selected((string) $settings->get('dashboard.cards_layout', 'side'), 'stacked'); ?>><?php esc_html_e('زیر هم (تمام‌عرض)', 'arma-panel'); ?></option>
                                    </select>
                                    <p class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('در موبایل همیشه زیر هم نمایش داده می‌شوند.', 'arma-panel'); ?></p>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد سفارش‌های «آخرین سفارش‌ها»', 'arma-panel'); ?></label></div>
                                <div>
                                    <input name="dash_recent_count" type="number" min="1" max="10" value="<?php echo esc_attr((string) $settings->get('dashboard.recent_orders_count', 4)); ?>" class="arma-input-text" style="max-width: 100px; direction: ltr; text-align: center;">
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد سفارش‌های «پرتکرارترین‌ها»', 'arma-panel'); ?></label></div>
                                <div>
                                    <input name="dash_frequent_count" type="number" min="1" max="10" value="<?php echo esc_attr((string) $settings->get('dashboard.frequent_orders_count', 3)); ?>" class="arma-input-text" style="max-width: 100px; direction: ltr; text-align: center;">
                                </div>
                            </div>

                            <?php
                            $mobSections = (array) $settings->get('dashboard.mobile.sections', []);
                            $mobStatsCols = (int) $settings->get('dashboard.mobile.stats_columns', 2);
                            $mobQuickCols = (int) $settings->get('dashboard.mobile.quick_columns', 2);
                            ?>

                            <div class="arma-field-row arma-wide" style="margin-top: 26px; padding-top: 18px; border-top: 2px solid #f1f5f9;">
                                <h4 style="margin: 0 0 4px; font-size: 1.02rem; color: #0f172a; font-weight: 800;"><?php esc_html_e('📱 حالت گوشی (موبایل) — اولویت چیدمان', 'arma-panel'); ?></h4>
                                <p class="arma-field-desc" style="margin-bottom: 16px;">
                                    <?php esc_html_e('تنظیمات این بخش فقط نمایش موبایل را تغییر می‌دهند و کاملاً مستقل از دسکتاپ هستند. برای پیش‌نمایش زندهٔ چیدمان گوشی، پارامتر ?arma_mobile_preview=1 را به آدرس پنل اضافه کنید.', 'arma-panel'); ?>
                                </p>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('مدل منوی کاربری', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="dash_mobile_menu_model" class="arma-select" style="max-width: 260px;">
                                        <option value="toggle" <?php selected((string) $settings->get('dashboard.mobile.menu_model', 'toggle'), 'toggle'); ?>><?php esc_html_e('دکمه منو (کلیک برای باز شدن آبشاری)', 'arma-panel'); ?></option>
                                        <option value="open" <?php selected((string) $settings->get('dashboard.mobile.menu_model', 'toggle'), 'open'); ?>><?php esc_html_e('همیشه باز (بدون دکمه)', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('کارت حساب کاربری بالای منو', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('همان آیکون و نام حساب کاربری که بالای منوی موبایل نمایش داده می‌شود.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <select name="dash_mobile_profile_card" class="arma-select" style="max-width: 260px;">
                                        <option value="full" <?php selected((string) $settings->get('dashboard.mobile.profile_card', 'full'), 'full'); ?>><?php esc_html_e('کامل (آواتار + نام + شماره + زنگوله)', 'arma-panel'); ?></option>
                                        <option value="minimal" <?php selected((string) $settings->get('dashboard.mobile.profile_card', 'full'), 'minimal'); ?>><?php esc_html_e('حداقلی (فقط نام و زنگوله — بدون آیکون)', 'arma-panel'); ?></option>
                                        <option value="hidden" <?php selected((string) $settings->get('dashboard.mobile.profile_card', 'full'), 'hidden'); ?>><?php esc_html_e('حذف کامل', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="arma-wide arma-wheel-slices" style="margin-top: 18px;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('ترتیب و نمایش بخش‌ها در موبایل', 'arma-panel'); ?></h4>
                                <p class="arma-field-desc" style="margin-bottom: 16px;">
                                    <?php esc_html_e('ترتیب و نمایش بخش‌های داشبورد در گوشی، جدا از دسکتاپ تعیین می‌شود — مثلاً می‌توانید آمار را در موبایل حذف کنید ولی در دسکتاپ نگه دارید.', 'arma-panel'); ?>
                                </p>
                                <div class="arma-table-scroll"><table class="arma-slices-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 90px;"><?php esc_html_e('جابه‌جایی', 'arma-panel'); ?></th>
                                            <th style="width: 80px;"><?php esc_html_e('نمایش', 'arma-panel'); ?></th>
                                            <th><?php esc_html_e('بخش داشبورد', 'arma-panel'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mobSections as $mobSec): ?>
                                            <?php $mobId = (string) ($mobSec['id'] ?? ''); if ($mobId === '' || !isset($dashNames[$mobId])) { continue; } ?>
                                            <tr>
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <input type="hidden" name="dash_sections_mobile[]" value="<?php echo esc_attr($mobId); ?>">
                                                    <button type="button" class="button arma-dash-move" data-dir="up" title="<?php esc_attr_e('انتقال به بالا', 'arma-panel'); ?>" style="margin: 0 2px; padding: 2px 8px;">▲</button>
                                                    <button type="button" class="button arma-dash-move" data-dir="down" title="<?php esc_attr_e('انتقال به پایین', 'arma-panel'); ?>" style="margin: 0 2px; padding: 2px 8px;">▼</button>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="checkbox" name="dash_enabled_mobile[<?php echo esc_attr($mobId); ?>]" value="1" <?php checked(!empty($mobSec['enabled'])); ?>>
                                                </td>
                                                <td><?php echo esc_html($dashNames[$mobId]); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table></div>
                            </div>

                            <div class="arma-field-row" style="margin-top: 18px;">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد ستون کارت‌های آمار در موبایل', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="dash_mobile_stats_columns" class="arma-select" style="max-width: 180px;">
                                        <option value="1" <?php selected($mobStatsCols, 1); ?>><?php esc_html_e('۱ ستونه (تمام‌عرض)', 'arma-panel'); ?></option>
                                        <option value="2" <?php selected($mobStatsCols, 2); ?>><?php esc_html_e('۲ ستونه', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد ستون دسترسی سریع در موبایل', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="dash_mobile_quick_columns" class="arma-select" style="max-width: 180px;">
                                        <option value="1" <?php selected($mobQuickCols, 1); ?>><?php esc_html_e('۱ ستونه (تمام‌عرض)', 'arma-panel'); ?></option>
                                        <option value="2" <?php selected($mobQuickCols, 2); ?>><?php esc_html_e('۲ ستونه', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('توضیح آیتم‌های دسترسی سریع در موبایل', 'arma-panel'); ?></label></div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="dash_mobile_quick_desc" type="checkbox" value="1" <?php checked((bool) $settings->get('dashboard.mobile.quick_show_desc', false)); ?>>
                                        <span><?php esc_html_e('نمایش توضیح کوتاه زیر عنوان هر آیتم (به‌صورت پیش‌فرض در موبایل مخفی است)', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                        <!-- WISHLIST -->
                        <?php elseif ($activeTab === 'wishlist'): ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('فعال‌سازی ماژول علاقه‌مندی‌ها', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('با فعال بودن ماژول، دکمه قلب در صفحه محصول نمایش داده می‌شود و کاربران می‌توانند محصولات مورد علاقه خود را در پنل کاربری ببینند و مدیریت کنند.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="wishlist_enabled" type="checkbox" value="1" <?php checked((bool) $settings->get('modules.wishlist', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول لیست علاقه‌مندی', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row" style="margin-top: 14px;">
                                <div><label class="arma-field-label"><?php esc_html_e('متن دکمه قلب', 'arma-panel'); ?></label></div>
                                <div>
                                    <input name="wishlist_button_text" type="text" value="<?php echo esc_attr((string) $settings->get('wishlist.button_text', 'افزودن به علاقه‌مندی‌ها')); ?>" class="arma-input-text" style="max-width: 320px;">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('سبک دکمه علاقه‌مندی', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="wishlist_button_style" class="arma-select" style="max-width: 320px;">
                                        <option value="button" <?php selected((string) $settings->get('wishlist.button_style', 'button'), 'button'); ?>><?php esc_html_e('دکمه کامل (قلب + متن)', 'arma-panel'); ?></option>
                                        <option value="icon" <?php selected((string) $settings->get('wishlist.button_style', 'button'), 'icon'); ?>><?php esc_html_e('فقط آیکون قلب کوچک (با کلیک پر می‌شود)', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('محل نمایش دکمه در صفحه محصول', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="wishlist_button_position" class="arma-select" style="max-width: 320px;">
                                        <option value="after_cart" <?php selected((string) $settings->get('wishlist.button_position', 'after_cart'), 'after_cart'); ?>><?php esc_html_e('بعد از دکمه افزودن به سبد خرید', 'arma-panel'); ?></option>
                                        <option value="before_cart" <?php selected((string) $settings->get('wishlist.button_position', 'after_cart'), 'before_cart'); ?>><?php esc_html_e('قبل از دکمه افزودن به سبد خرید', 'arma-panel'); ?></option>
                                        <option value="after_title" <?php selected((string) $settings->get('wishlist.button_position', 'after_cart'), 'after_title'); ?>><?php esc_html_e('زیر عنوان محصول', 'arma-panel'); ?></option>
                                        <option value="after_price" <?php selected((string) $settings->get('wishlist.button_position', 'after_cart'), 'after_price'); ?>><?php esc_html_e('زیر قیمت محصول', 'arma-panel'); ?></option>
                                        <option value="shortcode" <?php selected((string) $settings->get('wishlist.button_position', 'after_cart'), 'shortcode'); ?>><?php esc_html_e('فقط شورت‌کد (بدون نمایش خودکار)', 'arma-panel'); ?></option>
                                    </select>
                                    <p class="arma-field-desc" style="margin-top: 6px; line-height: 2.2;">
                                        <?php esc_html_e('اگر صفحه محصول را با المنتور یا صفحه‌ساز می‌سازید، گزینه‌های «زیر عنوان» و «زیر قیمت» کار نمی‌کنند (صفحه‌سازها هوک استاندارد ووکامرس را اجرا نمی‌کنند). در این حالت شورت‌کد زیر را در ویجت شورت‌کد المنتور در هر نقطه از صفحه درج کنید:', 'arma-panel'); ?><br>
                                        <code dir="ltr" style="display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 10px; font-size: 0.85rem;">[arma-wishlist]</code>
                                        <span style="margin: 0 4px;">یا</span>
                                        <code dir="ltr" style="display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 10px; font-size: 0.85rem;">[arma-wishlist style="icon"]</code>
                                        <span style="margin: 0 4px;">یا</span>
                                        <code dir="ltr" style="display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 10px; font-size: 0.85rem;">[arma-wishlist id="123"]</code>
                                    </p>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('رنگ قلب و دکمه', 'arma-panel'); ?></label></div>
                                <div>
                                    <input type="color" name="wishlist_heart_color" value="<?php echo esc_attr((string) $settings->get('wishlist.heart_color', '#e11d48')); ?>" style="width: 44px; height: 30px; border: none; cursor: pointer;">
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('رفتار کاربر مهمان (واردنشده)', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="wishlist_guest_behavior" class="arma-select" style="max-width: 300px;">
                                        <option value="login" <?php selected((string) $settings->get('wishlist.guest_behavior', 'login'), 'login'); ?>><?php esc_html_e('نمایش دکمه و هدایت به صفحه ورود پیامکی', 'arma-panel'); ?></option>
                                        <option value="hide" <?php selected((string) $settings->get('wishlist.guest_behavior', 'login'), 'hide'); ?>><?php esc_html_e('مخفی کردن دکمه برای مهمان‌ها', 'arma-panel'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="arma-field-row arma-wide" style="margin-top: 20px; padding-top: 14px; border-top: 2px solid #f1f5f9;">
                                <h4 style="margin: 0 0 8px; font-size: 1rem; color: #0f172a; font-weight: 700;"><?php esc_html_e('صفحه علاقه‌مندی‌های پنل کاربری', 'arma-panel'); ?></h4>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('عنوان صفحه علاقه‌مندی‌ها', 'arma-panel'); ?></label></div>
                                <div>
                                    <input name="wishlist_page_title" type="text" value="<?php echo esc_attr((string) $settings->get('wishlist.page_title', 'علاقه‌مندی‌های من')); ?>" class="arma-input-text" style="max-width: 320px;">
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('تعداد ستون نمایش محصولات', 'arma-panel'); ?></label></div>
                                <div>
                                    <select name="wishlist_columns" class="arma-select" style="max-width: 180px;">
                                        <option value="2" <?php selected((int) $settings->get('wishlist.columns', 3), 2); ?>><?php esc_html_e('۲ ستونه', 'arma-panel'); ?></option>
                                        <option value="3" <?php selected((int) $settings->get('wishlist.columns', 3), 3); ?>><?php esc_html_e('۳ ستونه', 'arma-panel'); ?></option>
                                        <option value="4" <?php selected((int) $settings->get('wishlist.columns', 3), 4); ?>><?php esc_html_e('۴ ستونه', 'arma-panel'); ?></option>
                                    </select>
                                    <p class="arma-field-desc" style="display: inline-block; margin-right: 8px;"><?php esc_html_e('در موبایل همیشه ۲ ستونه نمایش داده می‌شود.', 'arma-panel'); ?></p>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('نمایش قیمت در کارت محصولات', 'arma-panel'); ?></label></div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="wishlist_show_price" type="checkbox" value="1" <?php checked((bool) $settings->get('wishlist.show_price', true)); ?>>
                                        <span><?php esc_html_e('نمایش قیمت هر محصول در لیست علاقه‌مندی‌ها', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="arma-field-row">
                                <div><label class="arma-field-label"><?php esc_html_e('دکمه افزودن به سبد خرید', 'arma-panel'); ?></label></div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="wishlist_show_add_to_cart" type="checkbox" value="1" <?php checked((bool) $settings->get('wishlist.show_add_to_cart', true)); ?>>
                                        <span><?php esc_html_e('نمایش دکمه «افزودن به سبد خرید» روی هر کارت محصول', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                        <!-- 7. MODULES -->
                        <?php elseif ($activeTab === 'modules'): ?>
                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('سیستم تیکتینگ و پشتیبانی', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="module_tickets" type="checkbox" value="1" <?php checked((bool)$settings->get('modules.tickets', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول تیکت‌ها و پشتیبانی مشتریان', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('سیستم اطلاعیه‌ها', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="module_notifications" type="checkbox" value="1" <?php checked((bool)$settings->get('modules.notifications', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول اعلانات و پیام‌های همگانی', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('گردونه شانس', 'arma-panel'); ?></label>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="module_lucky_wheel" type="checkbox" value="1" <?php checked((bool)$settings->get('modules.lucky_wheel', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول گردونه شانس خریداران', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('لیست علاقه‌مندی', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('دکمه قلب در صفحه محصول و صفحه علاقه‌مندی‌ها در پنل کاربری.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="module_wishlist" type="checkbox" value="1" <?php checked((bool)$settings->get('modules.wishlist', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول لیست علاقه‌مندی', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="arma-field-row">
                                <div>
                                    <label class="arma-field-label"><?php esc_html_e('پیامک‌های ووکامرس (سفارش و ارسال)', 'arma-panel'); ?></label>
                                    <p class="arma-field-desc"><?php esc_html_e('با خاموش بودن این ماژول، هیچ پیامک سفارش/ارسالی ارسال نمی‌شود و بخش «ارسال و رهگیری مرسوله» از صفحه سفارش حذف می‌گردد.', 'arma-panel'); ?></p>
                                </div>
                                <div>
                                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input name="module_order_sms" type="checkbox" value="1" <?php checked((bool)$settings->get('modules.order_sms', true)); ?>>
                                        <span><?php esc_html_e('فعال بودن ماژول پیامک‌های ووکامرس', 'arma-panel'); ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div class="arma-admin__card-footer">
                        <button type="submit" class="button button-primary button-hero" style="font-family: inherit; font-weight: 700;">
                            <?php esc_html_e('ذخیره تغییرات', 'arma-panel'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function armaToggleCustomFont(val) {
    var wrap = document.getElementById('arma-custom-font-fields');
    if (wrap) {
        wrap.style.display = (val === 'custom') ? 'block' : 'none';
    }
    armaUpdateFontPreview(val);
}

function armaUpdateFontPreview(val) {
    var preview = document.getElementById('arma-font-preview');
    if (!preview) return;
    var map = {
        'inherit':   'inherit',
        'vazirmatn': "'Vazirmatn', Tahoma, sans-serif",
        'shabnam':   "'Shabnam', Tahoma, sans-serif",
        'iranyekan': "'IRANYekan', Tahoma, sans-serif",
        'yekanbakh': "'YekanBakh', 'Yekan', Tahoma, sans-serif",
        'custom':    (document.getElementById('arma-custom-font-name') ? ("'" + document.getElementById('arma-custom-font-name').value + "', Tahoma, sans-serif") : 'inherit')
    };
    preview.style.fontFamily = map[val] || 'inherit';
}

jQuery(document).ready(function($){
    // ── Lucky-wheel slice chances: live total + hard 100% cap ──
    function armaSyncChanceTotal() {
        var total = 0;
        $('input.arma-slice-chance').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) { total += v; }
        });
        var over = total > 100;
        $('#arma-chance-total-value').text(Math.round(total * 10) / 10 + '٪')
            .css('color', over ? '#b91c1c' : '');
        $('#arma-chance-error').toggle(over);
        $('#arma-settings-form').find('input[type=submit], button[type=submit]').prop('disabled', over);
    }
    $(document).on('input', 'input.arma-slice-chance', armaSyncChanceTotal);

    // The prize-type select drives which coupon-only fields are visible
    // in that row (discount type + amount only make sense for "تخفیف").
    $(document).on('change', 'select.arma-slice-type', function () {
        var row = $(this).closest('tr');
        var isCoupon = ($(this).val() === 'coupon');
        row.find('.arma-slice-coupon-only').toggle(isCoupon);
    });
    armaSyncChanceTotal();

    // ── Dashboard builder: move rows up/down (order = DOM order on submit) ──
    $(document).on('click', '.arma-dash-move', function () {
        var row = $(this).closest('tr');
        var dir = $(this).data('dir');
        if (dir === 'up') {
            var prev = row.prev('tr');
            if (prev.length) { row.insertBefore(prev); }
        } else {
            var next = row.next('tr');
            if (next.length) { row.insertAfter(next); }
        }
    });
    // Server-side guard too: never submit an over-100 configuration.
    $('#arma-settings-form').on('submit', function (e) {
        var total = 0;
        $('input.arma-slice-chance').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) { total += v; }
        });
        if (total > 100) {
            e.preventDefault();
            armaSyncChanceTotal();
            $('#arma-chance-error')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
    });

    // Init font preview
    armaUpdateFontPreview($('#arma-font-family').val() || 'inherit');
    $('#arma-custom-font-name').on('input', function(){ armaUpdateFontPreview('custom'); });

    // ── Dynamic per-provider SMS settings ─────────────────────────────
    // Selecting a gateway reveals only ITS credential block and the
    // named-parameter fields it needs (SMS.ir / FarazSMS).
    function armaToggleProviderSettings() {
        var sel = document.getElementById('arma-sms-provider');
        if (!sel) { return; }
        var provider = sel.value;
        document.querySelectorAll('.arma-provider-settings').forEach(function (el) {
            el.style.display = (el.getAttribute('data-provider') === provider) ? '' : 'none';
        });
        document.querySelectorAll('.arma-param-names').forEach(function (el) {
            var list = (el.getAttribute('data-providers') || '').split(/\s+/);
            el.style.display = (list.indexOf(provider) !== -1) ? '' : 'none';
        });
    }
    armaToggleProviderSettings();
    $(document).on('change', '#arma-sms-provider', armaToggleProviderSettings);

    // Live hex labels under color inputs
    function syncColorLabels() {
        $('input[type="color"]').each(function(){
            var name = $(this).attr('name');
            var label = $('[data-code-for="' + name + '"]');
            if (label.length) { label.text($(this).val().toUpperCase()); }
        });
    }
    $(document).on('input', 'input[type="color"]', syncColorLabels);
    syncColorLabels();

    // Reset all colors to defaults
    $('#arma-reset-colors').on('click', function(e){
        e.preventDefault();
        if (!window.confirm('همه رنگ‌ها به حالت پیش‌فرض بازگردانده شوند؟ (برای اعمال، ذخیره کنید)')) return;
        $('input[type="color"]').each(function(){
            var def = $(this).data('default');
            if (def) { $(this).val(def); }
        });
        syncColorLabels();
    });

    // Logo media picker
    var mediaUploader;
    $('#arma-upload-logo-btn').on('click', function(e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }
        mediaUploader = wp.media({
            title: 'انتخاب لوگو برای صفحه ورود',
            button: { text: 'استفاده از این تصویر' },
            multiple: false
        });
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#arma-logo-id').val(attachment.id);
            $('#arma-logo-preview').html('<img src="' + attachment.url + '" style="max-height:50px; max-width:180px;">');
            $('#arma-remove-logo-btn').show();
        });
        mediaUploader.open();
    });

    $('#arma-remove-logo-btn').on('click', function(e) {
        e.preventDefault();
        $('#arma-logo-id').val('0');
        $('#arma-logo-preview').html('');
        $(this).hide();
    });

    // Font file media picker (upload / select from media library)
    $('#arma-pick-font-btn').on('click', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) {
            window.alert('کتابخانه رسانه در دسترس نیست؛ لطفاً آدرس فایل فونت را دستی وارد کنید.');
            return;
        }
        var fontFrame = wp.media({
            title: 'انتخاب یا بارگذاری فایل فونت (woff2 / woff / ttf)',
            button: { text: 'استفاده از این فونت' },
            multiple: false,
            library: { type: [] }
        });
        fontFrame.on('select', function() {
            var att = fontFrame.state().get('selection').first().toJSON();
            $('#arma-custom-font-url').val(att.url);
            var nameInput = $('#arma-custom-font-name');
            if (!nameInput.val() && att.filename) {
                var base = att.filename.replace(/\.(woff2?|ttf|otf)$/i, '').replace(/[-_]+/g, ' ');
                nameInput.val(base);
            }
            armaUpdateFontPreview('custom');
        });
        fontFrame.open();
    });
});
</script>
