<?php
/**
 * Динамический генератор виджета
 * Подключение на сайт (async):
 * <script>
 * (function(w,d,s,u){
 *   var el=d.createElement(s);el.async=1;el.src=u;
 *   d.head.appendChild(el);
 * })(window,document,'script','https://your-domain.com/widget.php?key=SITE_KEY');
 * </script>
 */
require_once __DIR__ . '/rb.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');

$key = trim($_GET['key'] ?? '');

if (!$key) {
    echo '/* callback-widget: key is required */';
    exit;
}

R::setup('sqlite:' . DB_PATH);
R::freeze(true);

$site = R::findOne('sites', 'site_key = ? AND is_active = 1', [$key]);

if (!$site) {
    echo '/* callback-widget: site not found or inactive */';
    R::close();
    exit;
}

// Формируем конфиг из настроек сайта
$config = [
    'siteKey'           => $site->site_key,
    'submitUrl'         => BASE_URL . '/api/submit.php',
    'buttonColor'       => $site->button_color,
    'pulseColor'        => $site->pulse_color,
    'position'          => $site->position,
    'bottomOffset'      => $site->bottom_offset,
    'sideOffset'        => $site->side_offset,
    'title'             => $site->title,
    'subtitle'          => $site->subtitle,
    'successText'       => $site->success_text,
    'submitBtnText'     => $site->submit_btn_text,
    'badgeText'         => $site->badge_text,
    'privacyUrl'        => $site->privacy_url,
    'privacyText'       => $site->privacy_text,
    'showDelay'         => (int)$site->show_delay,
    'autoOpen'          => (bool)$site->auto_open,
    'autoOpenScroll'    => (float)$site->auto_open_scroll,
    'autoOpenTime'      => (int)$site->auto_open_time,
    'autoOpenTitle'     => $site->auto_open_title,
    'autoOpenSubtitle'  => $site->auto_open_subtitle,
    'ymCounterId'       => (int)$site->ym_counter_id,
    'ymGoal'            => $site->ym_goal ?: 'callback_widget',
];

R::close();

// CSRF: HMAC по 5-минутному окну + siteKey
$window = (int)floor(time() / 300);
$config['csrfToken'] = hash_hmac('sha256', $window . ':' . $site->site_key, CSRF_SECRET);

// Убираем пустые строки — пусть JS-дефолты сработают
$config = array_filter($config, fn($v) => $v !== '' && $v !== null);

$configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// Выводим конфиг + тело виджета
echo "window.__SCW_CONFIG = {$configJson};\n";
echo file_get_contents(__DIR__ . '/callback-widget.js');
