<?php
/**
 * API приёма заявок от виджета обратного звонка
 * POST /api/submit.php — JSON body
 */
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Парсинг тела запроса ──────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    $body = $_POST; // fallback на form-data
}

// ─── Honeypot: боты заполняют скрытое поле ────────────────────────────────────
if (!empty($body['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'bad request']);
    exit;
}

$siteKey     = trim($body['site_key']     ?? '');
$phone       = trim($body['phone']        ?? '');
$pageUrl     = trim($body['page_url']     ?? '');
$referrer    = trim($body['referrer']     ?? '');
$triggerType = trim($body['trigger_type'] ?? 'manual');
$ymClientId  = trim($body['ym_client_id'] ?? '');
$utmSource   = trim($body['utm_source']   ?? '');
$utmMedium   = trim($body['utm_medium']   ?? '');
$utmCampaign = trim($body['utm_campaign'] ?? '');
$utmContent  = trim($body['utm_content']  ?? '');
$utmTerm     = trim($body['utm_term']     ?? '');

// ─── Валидация ─────────────────────────────────────────────────────────────────
if (!$siteKey || !$phone) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'site_key and phone are required']);
    exit;
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 12) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
    exit;
}

// ─── Подключение к БД ──────────────────────────────────────────────────────────
R::setup('sqlite:' . DB_PATH);
R::freeze(true);

$site = R::findOne('sites', 'site_key = ? AND is_active = 1', [$siteKey]);

if (!$site) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Site not found']);
    R::close();
    exit;
}

// ─── CSRF: проверяем HMAC по текущему и предыдущему 5-минутному окну ──────────
$csrfToken = trim($body['_csrf'] ?? '');
$window    = (int)floor(time() / 300);
$valid = false;
foreach ([$window, $window - 1, $window - 2] as $w) {
    if (hash_equals(hash_hmac('sha256', $w . ':' . $siteKey, CSRF_SECRET), $csrfToken)) {
        $valid = true; break;
    }
}
if (!$valid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'invalid token']);
    R::close(); exit;
}

// ─── Сохранение заявки ────────────────────────────────────────────────────────
$lead = R::dispense('leads');
$lead->site_id      = (int)$site->id;
$lead->phone        = $phone;
$lead->page_url     = $pageUrl;
$lead->referrer     = $referrer;
$lead->utm_source   = $utmSource;
$lead->utm_medium   = $utmMedium;
$lead->utm_campaign = $utmCampaign;
$lead->utm_content  = $utmContent;
$lead->utm_term     = $utmTerm;
$lead->ip           = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$lead->user_agent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$lead->trigger_type = $triggerType;
$lead->ym_client_id = $ymClientId;
$lead->created_at   = date('Y-m-d H:i:s');
$leadId = R::store($lead);

// ─── Логирование ──────────────────────────────────────────────────────────────
log_lead("[LEAD] site={$siteKey} phone={$phone} trigger={$triggerType} utm_source={$utmSource} url={$pageUrl}");

// ─── Интеграции ───────────────────────────────────────────────────────────────
$tgToken  = trim($site->tg_token    ?? '');
$tgChat   = trim($site->tg_chat_id  ?? '');
$b24Hook  = trim($site->b24_webhook ?? '');

if ($tgToken && $tgChat) {
    send_telegram($tgToken, $tgChat, $phone, $triggerType, $pageUrl, $utmSource, $utmMedium, $utmCampaign, $site->name);
}
if ($b24Hook) {
    $b24Custom = json_decode($site->b24_custom_fields ?? '{}', true) ?: [];
    send_bitrix24($b24Hook, $phone, $triggerType, $pageUrl, $utmSource, $utmMedium, $utmCampaign, $site->domain ?: $site->name, $_SERVER['REMOTE_ADDR'] ?? '', $b24Custom, $ymClientId, $referrer, $utmContent, $utmTerm);
}

R::close();

// Цель в Метрику отправляется клиентски через ym() прямо в виджете
echo json_encode([
    'success' => true,
    'lead_id' => $leadId,
]);

// ─── Хелпер логирования ───────────────────────────────────────────────────────
function log_lead(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function send_telegram(string $token, string $chatId, string $phone, string $trigger, string $url, string $utmSource, string $utmMedium, string $utmCampaign, string $siteName): void
{
    $lines = ["📞 <b>Новая заявка!</b>", ""];
    $lines[] = "📱 <b>Телефон:</b> " . htmlspecialchars($phone);
    $lines[] = "🌐 <b>Сайт:</b> "    . htmlspecialchars($siteName);
    if ($url)         $lines[] = "📄 <b>Страница:</b> " . htmlspecialchars($url);
    if ($utmSource)   $lines[] = "🔗 <b>Источник:</b> " . htmlspecialchars($utmSource) . ($utmMedium ? ' / ' . htmlspecialchars($utmMedium) : '');
    if ($utmCampaign) $lines[] = "📊 <b>Кампания:</b> " . htmlspecialchars($utmCampaign);
    $lines[] = "🎯 <b>Тип:</b> "     . ($trigger === 'auto' ? 'Авто-открытие' : 'Ручной');
    $lines[] = "🕐 <b>Время:</b> "   . date('d.m.Y H:i:s');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.telegram.org/bot' . $token . '/sendMessage',
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS     => [
            'chat_id'    => $chatId,
            'text'       => implode("\n", $lines),
            'parse_mode' => 'HTML',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function send_bitrix24(string $webhook, string $phone, string $trigger, string $url, string $utmSource, string $utmMedium, string $utmCampaign, string $title, string $ip, array $customFields = [], string $ymClientId = '', string $referrer = '', string $utmContent = '', string $utmTerm = ''): void
{
    $macros = [
        '{{phone}}'        => $phone,
        '{{page_url}}'     => $url,
        '{{referrer}}'     => $referrer,
        '{{ym_client_id}}' => $ymClientId,
        '{{trigger_type}}' => $trigger,
        '{{ip}}'           => $ip,
        '{{utm_source}}'   => $utmSource,
        '{{utm_medium}}'   => $utmMedium,
        '{{utm_campaign}}' => $utmCampaign,
        '{{utm_content}}'  => $utmContent,
        '{{utm_term}}'     => $utmTerm,
    ];
    foreach ($customFields as $k => $v) {
        $customFields[$k] = str_replace(array_keys($macros), array_values($macros), $v);
    }

    $comments = array_filter([
        $url         ? 'Страница: '  . $url         : '',
        $utmSource   ? 'UTM source: '. $utmSource   : '',
        $utmMedium   ? 'UTM medium: '. $utmMedium   : '',
        $utmCampaign ? 'Кампания: '  . $utmCampaign : '',
        'Тип: '      . ($trigger === 'auto' ? 'авто-открытие' : 'ручной'),
        $ip          ? 'IP: '        . $ip          : '',
    ]);

    $fields = array_merge([
        'TITLE'     => 'Заявка | ' . $title,
        'PHONE'     => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
        'SOURCE_ID' => 'WEBFORM',
        'COMMENTS'  => implode("\n", $comments),
    ], $customFields);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => rtrim($webhook, '/') . '/crm.lead.add.json',
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS     => http_build_query(['fields' => $fields]),
    ]);
    curl_exec($ch);
    curl_close($ch);
}
