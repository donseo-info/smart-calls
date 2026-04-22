<?php
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['scw_admin'])) { header('Location: login.php'); exit; }

R::setup('sqlite:' . DB_PATH);
R::freeze(true);

// ── AJAX-обработчики ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_site') {
        $site = R::dispense('sites');
        $site->name              = trim($_POST['name'] ?? 'Новый сайт');
        $site->domain            = trim($_POST['domain'] ?? '');
        $site->site_key          = bin2hex(random_bytes(8));
        $site->button_color      = $_POST['button_color']      ?? '#25c16f';
        $site->pulse_color       = 'rgba(37,193,111,0.4)';
        $site->position          = $_POST['position']          ?? 'right';
        $site->bottom_offset     = trim($_POST['bottom_offset'] ?? '30px') ?: '30px';
        $site->side_offset       = trim($_POST['side_offset']   ?? '30px') ?: '30px';
        $site->title             = $_POST['title']             ?? 'Перезвоним за 30 секунд';
        $site->subtitle          = $_POST['subtitle']          ?? 'Оставьте номер — мы сами позвоним';
        $site->success_text      = $_POST['success_text']      ?? 'Спасибо! Перезвоним в течение 30 секунд.';
        $site->submit_btn_text   = $_POST['submit_btn_text']   ?? 'Перезвоните мне';
        $site->badge_text        = $_POST['badge_text']        ?? 'Перезвонить?';
        $site->privacy_url       = $_POST['privacy_url']       ?? '';
        $site->privacy_text      = $_POST['privacy_text']      ?? 'Политика конфиденциальности';
        $site->show_delay        = (int)($_POST['show_delay']  ?? 5);
        $site->auto_open         = isset($_POST['auto_open']) ? 1 : 0;
        $site->auto_open_scroll  = round((float)($_POST['auto_open_scroll'] ?? 0.75), 2);
        $site->auto_open_time    = (int)($_POST['auto_open_time']   ?? 30);
        $site->auto_open_title   = $_POST['auto_open_title']   ?? 'Остались вопросы?';
        $site->auto_open_subtitle= $_POST['auto_open_subtitle'] ?? 'Наш специалист проконсультирует вас бесплатно — просто оставьте номер';
        $site->ym_counter_id     = trim($_POST['ym_counter_id']    ?? '');
        $site->ym_goal           = trim($_POST['ym_goal']           ?? 'callback_widget');
        $site->is_active         = 1;
        $site->created_at        = date('Y-m-d H:i:s');
        $site->updated_at        = date('Y-m-d H:i:s');
        $id = R::store($site);
        echo json_encode(['success' => true, 'id' => $id, 'key' => $site->site_key]);
        R::close(); exit;
    }

    if ($action === 'update_site') {
        $site = R::load('sites', (int)($_POST['id'] ?? 0));
        if (!$site->id) { echo json_encode(['success' => false]); R::close(); exit; }
        $site->name              = trim($_POST['name']            ?? $site->name);
        $site->domain            = trim($_POST['domain']          ?? $site->domain);
        $site->button_color      = $_POST['button_color']         ?? $site->button_color;
        $site->position          = $_POST['position']             ?? $site->position;
        $site->bottom_offset     = trim($_POST['bottom_offset']   ?? $site->bottom_offset) ?: $site->bottom_offset;
        $site->side_offset       = trim($_POST['side_offset']     ?? $site->side_offset)   ?: $site->side_offset;
        $site->title             = $_POST['title']                ?? $site->title;
        $site->subtitle          = $_POST['subtitle']             ?? $site->subtitle;
        $site->success_text      = $_POST['success_text']         ?? $site->success_text;
        $site->submit_btn_text   = $_POST['submit_btn_text']      ?? $site->submit_btn_text;
        $site->badge_text        = $_POST['badge_text']           ?? $site->badge_text;
        $site->privacy_url       = $_POST['privacy_url']          ?? $site->privacy_url;
        $site->privacy_text      = $_POST['privacy_text']         ?? $site->privacy_text;
        $site->show_delay        = (int)($_POST['show_delay']     ?? $site->show_delay);
        $site->auto_open         = isset($_POST['auto_open']) ? 1 : 0;
        $site->auto_open_scroll  = round((float)($_POST['auto_open_scroll'] ?? $site->auto_open_scroll), 2);
        $site->auto_open_time    = (int)($_POST['auto_open_time']    ?? $site->auto_open_time);
        $site->auto_open_title   = $_POST['auto_open_title']    ?? $site->auto_open_title;
        $site->auto_open_subtitle= $_POST['auto_open_subtitle'] ?? $site->auto_open_subtitle;
        $site->ym_counter_id     = trim($_POST['ym_counter_id']   ?? $site->ym_counter_id);
        $site->ym_goal           = trim($_POST['ym_goal']          ?? $site->ym_goal);
        $site->updated_at        = date('Y-m-d H:i:s');
        R::store($site);
        echo json_encode(['success' => true]);
        R::close(); exit;
    }

    if ($action === 'toggle_site') {
        $site = R::load('sites', (int)($_POST['id'] ?? 0));
        $site->is_active = $site->is_active ? 0 : 1;
        R::store($site);
        echo json_encode(['success' => true, 'is_active' => (int)$site->is_active]);
        R::close(); exit;
    }

    if ($action === 'delete_site') {
        R::trash(R::load('sites', (int)($_POST['id'] ?? 0)));
        echo json_encode(['success' => true]);
        R::close(); exit;
    }

    if ($action === 'update_integrations') {
        $site = R::load('sites', (int)($_POST['id'] ?? 0));
        if (!$site->id) { echo json_encode(['success' => false]); R::close(); exit; }
        $site->tg_token          = trim($_POST['tg_token']    ?? '');
        $site->tg_chat_id        = trim($_POST['tg_chat_id']  ?? '');
        $site->b24_webhook       = trim($_POST['b24_webhook']  ?? '');
        // кастомные поля: массив {key:val} из POST
        $customKeys = $_POST['cf_key']   ?? [];
        $customVals = $_POST['cf_value'] ?? [];
        $customFields = [];
        foreach ($customKeys as $i => $k) {
            $k = trim($k); $v = trim($customVals[$i] ?? '');
            if ($k !== '') $customFields[$k] = $v;
        }
        $site->b24_custom_fields = json_encode($customFields, JSON_UNESCAPED_UNICODE);
        $site->updated_at        = date('Y-m-d H:i:s');
        R::store($site);
        echo json_encode(['success' => true]);
        R::close(); exit;
    }

    if ($action === 'test_telegram') {
        $token  = trim($_POST['tg_token']   ?? '');
        $chatId = trim($_POST['tg_chat_id'] ?? '');
        if (!$token || !$chatId) { echo json_encode(['success' => false, 'error' => 'Токен и Chat ID обязательны']); exit; }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.telegram.org/bot' . $token . '/sendMessage',
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => ['chat_id' => $chatId, 'text' => '✅ SmartCall: тестовое сообщение. Интеграция работает!'],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $res = json_decode($raw, true);
        if ($err) { echo json_encode(['success' => false, 'error' => 'cURL: ' . $err]); exit; }
        echo json_encode(['success' => (bool)($res['ok'] ?? false), 'raw' => $res]);
        exit;
    }

    if ($action === 'test_bitrix24') {
        $webhook = trim($_POST['b24_webhook'] ?? '');
        if (!$webhook) { echo json_encode(['success' => false, 'error' => 'Webhook URL обязателен']); exit; }
        $customKeys = $_POST['cf_key']   ?? [];
        $customVals = $_POST['cf_value'] ?? [];
        $customFields = [];
        foreach ($customKeys as $i => $k) {
            $k = trim($k); $v = trim($customVals[$i] ?? '');
            if ($k !== '') $customFields[$k] = $v;
        }
        $fields = array_merge([
            'TITLE'     => 'SmartCall TEST — тестовый лид',
            'PHONE'     => [['VALUE' => '+70000000000', 'VALUE_TYPE' => 'WORK']],
            'SOURCE_ID' => 'WEBFORM',
            'COMMENTS'  => 'Тестовый лид от SmartCall. Можно удалить.',
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
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $res = json_decode($raw, true);
        if ($err) { echo json_encode(['success' => false, 'error' => 'cURL: ' . $err]); exit; }
        $ok = isset($res['result']) && !isset($res['error']);
        echo json_encode(['success' => $ok, 'raw' => $res]);
        exit;
    }

    if ($action === 'delete_lead') {
        R::trash(R::load('leads', (int)($_POST['id'] ?? 0)));
        echo json_encode(['success' => true]);
        R::close(); exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    R::close(); exit;
}

// ── Данные ────────────────────────────────────────────────────────────────────
$tab   = $_GET['tab'] ?? 'dashboard';
$today = date('Y-m-d');

$totalSites   = (int)R::getCell('SELECT COUNT(*) FROM sites');
$activeSites  = (int)R::getCell('SELECT COUNT(*) FROM sites WHERE is_active = 1');
$totalLeads   = (int)R::getCell('SELECT COUNT(*) FROM leads');
$todayLeads   = (int)R::getCell("SELECT COUNT(*) FROM leads WHERE date(created_at) = ?", [$today]);
$goalsSent    = (int)R::getCell('SELECT COUNT(*) FROM leads WHERE goal_sent = 1');
$recentLeads  = R::getAll("SELECT l.*, s.name as site_name FROM leads l LEFT JOIN sites s ON s.id=l.site_id ORDER BY l.created_at DESC LIMIT 8");

$sites = R::getAll('SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.site_id=s.id) as leads_count FROM sites s ORDER BY s.created_at DESC');

$installSiteId = (int)($_GET['install_site'] ?? ($sites[0]['id'] ?? 0));
$installSite   = null;
foreach ($sites as $s) { if ($s['id'] == $installSiteId) { $installSite = $s; break; } }
if (!$installSite && $sites) $installSite = $sites[0];

$leadsPage        = max(1, (int)($_GET['lp'] ?? 1));
$leadsLimit       = 25;
$leadsOffset      = ($leadsPage - 1) * $leadsLimit;
$leadsSiteFilter  = (int)($_GET['site_id'] ?? 0);
$leadsWhere       = $leadsSiteFilter ? 'WHERE l.site_id = ' . $leadsSiteFilter : '';
$leadsTotal       = (int)R::getCell("SELECT COUNT(*) FROM leads l $leadsWhere");
$leadsPages       = max(1, (int)ceil($leadsTotal / $leadsLimit));
$leads            = R::getAll("SELECT l.*, s.name as site_name FROM leads l LEFT JOIN sites s ON s.id=l.site_id $leadsWhere ORDER BY l.created_at DESC LIMIT $leadsLimit OFFSET $leadsOffset");

R::close();

// ── Хелперы ───────────────────────────────────────────────────────────────────
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmtPhone($v): string {
    $d = preg_replace('/\D/', '', $v ?? '');
    if (strlen($d) === 11 && $d[0] === '7') return '+7 (' . substr($d,1,3) . ') ' . substr($d,4,3) . '-' . substr($d,7,2) . '-' . substr($d,9,2);
    return $v ?? '—';
}
function fmtDate($v): string {
    if (!$v) return '—';
    return ($ts = strtotime($v)) ? date('d.m.Y H:i', $ts) : $v;
}
function activeTab($n, $c): string { return $n === $c ? 'active' : ''; }
function buildUrl(array $extra = []): string {
    $p = array_merge($_GET, $extra);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SmartCall · Виджеты</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html,body { height:100%; }
  body { background:#f3f7fb; color:#0f172a; font-size:13px; font-family:'Segoe UI',system-ui,sans-serif; }

  /* ── Шапка ── */
  .ct-header { background:#fff; border-bottom:1px solid #dbeafe; padding:0 24px; height:52px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; box-shadow:0 1px 4px rgba(15,23,42,.05); }
  .ct-logo { display:flex; align-items:center; gap:10px; font-weight:700; font-size:14px; color:#1d4ed8; letter-spacing:-.3px; text-decoration:none; }
  .ct-logo .dot { width:28px; height:28px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; }
  .ct-now { color:#94a3b8; font-size:11px; }

  /* ── Навигация ── */
  .ct-nav { background:#fff; border-bottom:1px solid #dbeafe; padding:0 24px; }
  .ct-nav .nav-link { color:#64748b; font-size:13px; font-weight:500; padding:10px 14px; border-bottom:2px solid transparent; border-radius:0; display:flex; align-items:center; gap:6px; transition:color .15s,border-color .15s; }
  .ct-nav .nav-link:hover { color:#1d4ed8; }
  .ct-nav .nav-link.active { color:#1d4ed8; border-bottom-color:#1d4ed8; background:transparent; }

  /* ── Стат-карточки ── */
  .stat-card { background:#fff; border:1px solid #dbeafe; border-radius:12px; padding:20px 22px; box-shadow:0 4px 12px rgba(15,23,42,.06); position:relative; overflow:hidden; transition:box-shadow .2s; }
  .stat-card:hover { box-shadow:0 6px 20px rgba(15,23,42,.1); }
  .stat-card .stat-icon { position:absolute; top:16px; right:18px; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; }
  .stat-card .stat-val  { font-size:32px; font-weight:700; color:#0f172a; line-height:1; margin-bottom:4px; }
  .stat-card .stat-label{ color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.06em; font-weight:500; }
  .stat-card .stat-sub  { font-size:11px; color:#94a3b8; margin-top:6px; }
  .ic-blue   { background:#eff6ff; color:#3b82f6; }
  .ic-indigo { background:#eef2ff; color:#6366f1; }
  .ic-green  { background:#f0fdf4; color:#22c55e; }
  .ic-amber  { background:#fffbeb; color:#f59e0b; }

  /* ── Карточки-обёртки ── */
  .ct-card { background:#fff; border:1px solid #dbeafe; border-radius:12px; box-shadow:0 4px 12px rgba(15,23,42,.06); overflow:hidden; }
  .ct-card-header { background:#e0f2fe; border-bottom:1px solid #bfdbfe; padding:10px 18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .ct-card-header .ct-title { font-weight:600; font-size:13px; color:#1e3a8a; display:flex; align-items:center; gap:7px; }

  /* ── Таблица ── */
  .ct-table { font-size:12px; margin:0; }
  .ct-table thead th { background:#eff6ff; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #dbeafe; padding:8px 12px; white-space:nowrap; }
  .ct-table tbody td { padding:9px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; color:#334155; }
  .ct-table tbody tr:last-child td { border-bottom:none; }
  .ct-table tbody tr:hover td { background:#f8fbff; }

  /* ── Бейджи ── */
  .badge-on     { background:#dbeafe; color:#1d4ed8; font-weight:600; font-size:10px; }
  .badge-off    { background:#f1f5f9; color:#94a3b8; font-weight:600; font-size:10px; }
  .badge-auto   { background:#fffbeb; color:#b45309; font-weight:600; font-size:10px; }
  .badge-manual { background:#eef2ff; color:#6366f1; font-weight:600; font-size:10px; }
  .badge-sent   { background:#dcfce7; color:#15803d; font-weight:600; font-size:10px; }
  .goal-yes { color:#22c55e; font-size:14px; }
  .goal-no  { color:#cbd5e1; font-size:14px; }
  .goal-err { color:#f59e0b; font-size:14px; }

  .key-tag { font-family:monospace; font-size:11px; background:#eff6ff; color:#1d4ed8; padding:2px 7px; border-radius:5px; }
  .utm-tag { font-size:11px; background:#f0fdf4; color:#15803d; padding:1px 6px; border-radius:4px; display:inline-block; }

  /* ── Пагинация ── */
  .ct-pagination .page-link { font-size:12px; padding:4px 10px; color:#1d4ed8; border-color:#dbeafe; background:#fff; }
  .ct-pagination .page-item.active .page-link { background:#1d4ed8; border-color:#1d4ed8; }
  .ct-pagination .page-item.disabled .page-link { color:#cbd5e1; }

  /* ── Empty state ── */
  .empty-state { text-align:center; padding:48px 24px; color:#94a3b8; }
  .empty-state i { font-size:32px; display:block; margin-bottom:8px; opacity:.4; }

  /* ── Модал ── */
  .modal-content { border:1px solid #dbeafe; border-radius:16px; }
  .modal-header  { background:#e0f2fe; border-bottom:1px solid #bfdbfe; border-radius:16px 16px 0 0; }
  .modal-title   { color:#1e3a8a; font-weight:700; font-size:15px; }
  .modal-footer  { border-top:1px solid #dbeafe; }
  .form-section  { background:#f8fbff; border:1px solid #dbeafe; border-radius:10px; padding:14px 16px; margin-bottom:14px; }
  .form-section-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#3b82f6; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
  .form-label { font-size:12px; color:#475569; font-weight:500; margin-bottom:4px; }
  .form-control, .form-select { font-size:13px; border-color:#bfdbfe; }
  .form-control:focus, .form-select:focus { border-color:#38bdf8; box-shadow:0 0 0 2px rgba(56,189,248,.2); }

  /* ── Embed-блок ── */
  .embed-box { background:#f8fbff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px; font-family:monospace; font-size:12px; color:#1d4ed8; word-break:break-all; }

  /* ── Install ── */
  .install-wrap { background:#fff; border:1px solid #dbeafe; border-radius:12px; box-shadow:0 4px 12px rgba(15,23,42,.06); padding:20px 22px; height:100%; }
  .install-code { background:#0f172a; color:#7dd3fc; border-radius:8px; padding:14px 16px; font-family:monospace; font-size:12px; line-height:1.7; white-space:pre-wrap; word-break:break-all; margin:0; border:none; }
  .copy-btn.copied { background:#dcfce7!important; color:#15803d!important; border-color:#bbf7d0!important; }

  /* ── Toast ── */
  .toast-ct { position:fixed; bottom:24px; right:24px; z-index:9999; min-width:220px; }
</style>
</head>
<body>

<!-- Шапка -->
<header class="ct-header">
  <a class="ct-logo" href="?tab=dashboard">
    <div class="dot"><i class="bi bi-telephone-fill"></i></div>
    SmartCall
  </a>
  <div class="d-flex align-items-center gap-3">
    <span class="ct-now" id="ct-clock"></span>
    <a href="logout.php" class="btn btn-sm" style="border:1px solid #dbeafe;color:#64748b;font-size:12px;">
      <i class="bi bi-box-arrow-right me-1"></i>Выйти
    </a>
  </div>
</header>

<!-- Навигация -->
<nav class="ct-nav">
  <ul class="nav">
    <li class="nav-item">
      <a class="nav-link <?= activeTab('dashboard',$tab) ?>" href="?tab=dashboard">
        <i class="bi bi-speedometer2"></i> Дашборд
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= activeTab('sites',$tab) ?>" href="?tab=sites">
        <i class="bi bi-globe"></i> Сайты
        <?php if ($totalSites): ?><span class="badge rounded-pill ms-1" style="background:#dbeafe;color:#1d4ed8;font-size:10px;"><?= $totalSites ?></span><?php endif ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= activeTab('leads',$tab) ?>" href="?tab=leads">
        <i class="bi bi-telephone-inbound"></i> Заявки
        <?php if ($todayLeads): ?><span class="badge rounded-pill ms-1" style="background:#dcfce7;color:#15803d;font-size:10px;"><?= $todayLeads ?> сегодня</span><?php endif ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= activeTab('integrations',$tab) ?>" href="?tab=integrations">
        <i class="bi bi-plug"></i> Интеграции
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= activeTab('install',$tab) ?>" href="?tab=install">
        <i class="bi bi-code-slash"></i> Установка
      </a>
    </li>
  </ul>
</nav>

<div class="container-fluid px-4 py-4" style="max-width:1400px;">

<!-- ══ ДАШБОРД ══════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'dashboard'): ?>

<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['val'=>$totalLeads,  'label'=>'Всего заявок',    'sub'=>'за всё время',       'icon'=>'bi-telephone-inbound','cls'=>'ic-blue'],
    ['val'=>$todayLeads,  'label'=>'Заявок сегодня',  'sub'=>date('d.m.Y'),         'icon'=>'bi-calendar-check',   'cls'=>'ic-green'],
    ['val'=>$goalsSent,   'label'=>'Целей отправлено','sub'=>'в Яндекс.Метрику',   'icon'=>'bi-graph-up-arrow',   'cls'=>'ic-indigo'],
    ['val'=>$activeSites, 'label'=>'Активных сайтов', 'sub'=>'из '.$totalSites.' всего','icon'=>'bi-globe',       'cls'=>'ic-amber'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon <?= $c['cls'] ?>"><i class="bi <?= $c['icon'] ?>"></i></div>
      <div class="stat-val"><?= $c['val'] ?></div>
      <div class="stat-label"><?= $c['label'] ?></div>
      <div class="stat-sub"><?= $c['sub'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="ct-card">
  <div class="ct-card-header">
    <div class="ct-title"><i class="bi bi-clock-history"></i> Последние заявки</div>
    <a href="?tab=leads" class="btn btn-sm" style="border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;">Все заявки →</a>
  </div>
  <table class="table ct-table mb-0">
    <thead><tr><th>Телефон</th><th>Сайт</th><th>Источник</th><th>Страница</th><th>Тип</th><th>Дата</th><th>Цель</th></tr></thead>
    <tbody>
    <?php if (!$recentLeads): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>Заявок пока нет</div></td></tr>
    <?php endif; ?>
    <?php foreach ($recentLeads as $l): ?>
      <tr>
        <td><strong><?= esc(fmtPhone($l['phone'])) ?></strong></td>
        <td><?= esc($l['site_name'] ?? '—') ?></td>
        <td><?= $l['utm_source'] ? '<span class="utm-tag">'.esc($l['utm_source']).'</span>' : '<span style="color:#cbd5e1">—</span>' ?></td>
        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?php if ($l['page_url']): ?><a href="<?= esc($l['page_url']) ?>" target="_blank" style="color:#94a3b8;font-size:11px;" title="<?= esc($l['page_url']) ?>"><?= esc(parse_url($l['page_url'], PHP_URL_PATH) ?: '/') ?></a><?php else: echo '—'; endif; ?>
        </td>
        <td><?= $l['trigger_type']==='auto' ? '<span class="badge badge-auto rounded-pill">авто</span>' : '<span class="badge badge-manual rounded-pill">ручной</span>' ?></td>
        <td style="color:#64748b;"><?= fmtDate($l['created_at']) ?></td>
        <td><?php
          if ($l['goal_sent']==1) echo '<i class="bi bi-check-circle-fill goal-yes" title="Отправлена"></i>';
          elseif ($l['goal_sent']==2) echo '<i class="bi bi-exclamation-circle-fill goal-err" title="Ошибка"></i>';
          else echo '<i class="bi bi-dash-circle goal-no"></i>';
        ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ══ САЙТЫ ════════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'sites'): ?>

<div class="ct-card">
  <div class="ct-card-header">
    <div class="ct-title"><i class="bi bi-globe"></i> Сайты (<?= $totalSites ?>)</div>
    <button class="btn btn-sm btn-primary" style="font-size:12px;" onclick="openCreateModal()">
      <i class="bi bi-plus-lg me-1"></i>Добавить сайт
    </button>
  </div>
  <table class="table ct-table mb-0">
    <thead><tr><th>Название / Домен</th><th>Ключ</th><th>Заявок</th><th>Статус</th><th>Создан</th><th class="text-end">Действия</th></tr></thead>
    <tbody>
    <?php if (!$sites): ?>
      <tr><td colspan="6"><div class="empty-state"><i class="bi bi-globe"></i>Сайтов пока нет — <a href="#" onclick="openCreateModal();return false">добавьте первый</a></div></td></tr>
    <?php endif; ?>
    <?php foreach ($sites as $s): ?>
    <tr>
      <td>
        <div style="font-weight:600;color:#1e3a8a;"><?= esc($s['name']) ?></div>
        <?php if ($s['domain']): ?><div style="font-size:11px;color:#94a3b8;"><?= esc($s['domain']) ?></div><?php endif ?>
      </td>
      <td><span class="key-tag"><?= esc($s['site_key']) ?></span></td>
      <td><?= (int)$s['leads_count'] ?></td>
      <td>
        <button class="btn btn-sm p-0 border-0 bg-transparent" onclick="toggleSite(<?= $s['id'] ?>)">
          <?= $s['is_active'] ? '<span class="badge badge-on rounded-pill">активен</span>' : '<span class="badge badge-off rounded-pill">выключен</span>' ?>
        </button>
      </td>
      <td style="color:#64748b;"><?= fmtDate($s['created_at']) ?></td>
      <td class="text-end" style="white-space:nowrap;">
        <button class="btn btn-sm border-0 bg-transparent text-secondary" title="Код подключения" onclick="showEmbed(<?= esc(json_encode($s['site_key'])) ?>,<?= esc(json_encode((string)($s['ym_counter_id'] ?? ''))) ?>)"><i class="bi bi-code-slash"></i></button>
        <button class="btn btn-sm border-0 bg-transparent text-primary" title="Редактировать" onclick='openEditModal(<?= esc(json_encode($s)) ?>)'><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm border-0 bg-transparent text-danger" title="Удалить" onclick="deleteSite(<?= $s['id'] ?>)"><i class="bi bi-trash"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ══ ЗАЯВКИ ═══════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'leads'): ?>

<div class="ct-card">
  <div class="ct-card-header">
    <div class="ct-title"><i class="bi bi-telephone-inbound"></i> Заявки (<?= $leadsTotal ?>)</div>
    <select class="form-select form-select-sm" style="width:200px;border-color:#bfdbfe;font-size:12px;" onchange="location='?tab=leads&site_id='+this.value">
      <option value="">Все сайты</option>
      <?php foreach ($sites as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $leadsSiteFilter==$s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <table class="table ct-table mb-0">
    <thead><tr><th>Телефон</th><th>Сайт</th><th>Страница</th><th>Источник</th><th>Кампания</th><th>Тип</th><th>YM ClientID</th><th>IP</th><th>Дата</th><th></th></tr></thead>
    <tbody>
    <?php if (!$leads): ?>
      <tr><td colspan="10"><div class="empty-state"><i class="bi bi-inbox"></i>Заявок нет</div></td></tr>
    <?php endif; ?>
    <?php foreach ($leads as $l): ?>
    <tr>
      <td><strong style="color:#1e3a8a;"><?= esc(fmtPhone($l['phone'])) ?></strong></td>
      <td><?= esc($l['site_name'] ?? '—') ?></td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php if ($l['page_url']): ?><a href="<?= esc($l['page_url']) ?>" target="_blank" style="color:#94a3b8;font-size:11px;" title="<?= esc($l['page_url']) ?>"><?= esc(parse_url($l['page_url'], PHP_URL_PATH) ?: '/') ?></a><?php else: echo '<span style="color:#cbd5e1">—</span>'; endif; ?>
      </td>
      <td><?= $l['utm_source'] ? '<span class="utm-tag">'.esc($l['utm_source']).'</span>' : '<span style="color:#cbd5e1">—</span>' ?></td>
      <td style="font-size:11px;color:#64748b;"><?= $l['utm_campaign'] ? esc($l['utm_campaign']) : '—' ?></td>
      <td><?= $l['trigger_type']==='auto' ? '<span class="badge badge-auto rounded-pill">авто</span>' : '<span class="badge badge-manual rounded-pill">ручной</span>' ?></td>
      <td style="font-size:11px;font-family:monospace;color:#64748b;" title="<?= esc($l['ym_client_id'] ?? '') ?>"><?= $l['ym_client_id'] ? esc($l['ym_client_id']) : '<span style="color:#cbd5e1">—</span>' ?></td>
      <td style="font-size:11px;color:#94a3b8;"><?= esc($l['ip']) ?></td>
      <td style="color:#64748b;white-space:nowrap;"><?= fmtDate($l['created_at']) ?></td>
      <td><button class="btn btn-sm border-0 bg-transparent text-danger" onclick="deleteLead(<?= $l['id'] ?>)"><i class="bi bi-trash"></i></button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($leadsPages > 1): ?>
<nav class="mt-3">
  <ul class="pagination ct-pagination pagination-sm">
    <?php for ($p = 1; $p <= $leadsPages; $p++): ?>
      <li class="page-item <?= $p==$leadsPage ? 'active' : '' ?>">
        <a class="page-link" href="<?= buildUrl(['tab'=>'leads','lp'=>$p]) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php elseif ($tab === 'integrations'): ?>

<div class="ct-card">
  <div class="ct-card-header">
    <div class="ct-title"><i class="bi bi-plug"></i> Интеграции по сайтам</div>
  </div>
  <table class="table ct-table mb-0">
    <thead><tr><th>Сайт</th><th>Домен</th><th>Telegram</th><th>Bitrix24</th><th></th></tr></thead>
    <tbody>
    <?php if (!$sites): ?>
      <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i>Сайтов нет</div></td></tr>
    <?php endif; ?>
    <?php foreach ($sites as $s): ?>
    <tr>
      <td><strong style="color:#1e3a8a;"><?= esc($s['name']) ?></strong></td>
      <td style="font-size:11px;color:#94a3b8;"><?= esc($s['domain']) ?: '—' ?></td>
      <td>
        <?php if (!empty($s['tg_token']) && !empty($s['tg_chat_id'])): ?>
          <span class="badge rounded-pill" style="background:#dcfce7;color:#15803d;font-size:11px;"><i class="bi bi-check-circle me-1"></i>Настроен</span>
        <?php else: ?>
          <span class="badge rounded-pill" style="background:#f1f5f9;color:#94a3b8;font-size:11px;">Не настроен</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if (!empty($s['b24_webhook'])): ?>
          <span class="badge rounded-pill" style="background:#dcfce7;color:#15803d;font-size:11px;"><i class="bi bi-check-circle me-1"></i>Настроен</span>
        <?php else: ?>
          <span class="badge rounded-pill" style="background:#f1f5f9;color:#94a3b8;font-size:11px;">Не настроен</span>
        <?php endif; ?>
      </td>
      <td class="text-end">
        <button class="btn btn-sm border-0 bg-transparent text-primary" onclick='openIntModal(<?= esc(json_encode($s)) ?>)'><i class="bi bi-pencil"></i> Настроить</button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'install'): ?>

<?php
  $iProtoDetect = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $iBaseDetect  = $iProtoDetect . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
  $iWidgetUrl = $iBaseDetect . '/widget.php?key=' . ($installSite['site_key'] ?? 'SITE_KEY');
  $iGateUrl   = $iBaseDetect . '/api/submit.php';
  $iCounter   = $installSite['ym_counter_id'] ?? '';

  $codeSimple = '<script src="' . $iWidgetUrl . '"' . "\n"
    . '        data-gate="' . $iGateUrl . '"' . "\n"
    . '        data-counter="' . ($iCounter ?: 'XXXXXXXX') . '"></script>';

  $codeAsync = '<script>' . "\n"
    . '(function(w,d,s,u,g,c){' . "\n"
    . '  w._SCW={gate:g,counter:c};' . "\n"
    . '  var el=d.createElement(s);el.async=1;el.src=u;' . "\n"
    . '  d.head.appendChild(el);' . "\n"
    . '})(window,document,\'script\',' . "\n"
    . '  \'' . $iWidgetUrl . '\',' . "\n"
    . '  \'' . $iGateUrl . '\',' . "\n"
    . '  \'' . ($iCounter ?: 'XXXXXXXX') . '\');' . "\n"
    . '</script>';

  $codeGtm = $codeAsync;

  $iHost  = parse_url($iWidgetUrl, PHP_URL_HOST);
  $iLen   = strlen($iHost);
  $p1     = (int)round($iLen / 3);
  $p2     = (int)round($iLen * 2 / 3);
  $iH1    = substr($iHost, 0, $p1);
  $iH2    = substr($iHost, $p1, $p2 - $p1);
  $iH3    = substr($iHost, $p2);
  $iProto = parse_url($iWidgetUrl, PHP_URL_SCHEME) . '://';
  $iBasePath = dirname(parse_url($iWidgetUrl, PHP_URL_PATH));
  $iPLen  = strlen($iBasePath);
  $pp1    = (int)round($iPLen / 3);
  $pp2    = (int)round($iPLen * 2 / 3);
  $iP1    = substr($iBasePath, 0, $pp1);
  $iP2    = substr($iBasePath, $pp1, $pp2 - $pp1);
  $iP3    = substr($iBasePath, $pp2);
  $iWName = '/' . pathinfo(parse_url($iWidgetUrl, PHP_URL_PATH), PATHINFO_FILENAME);
  $iGName = '/api/' . pathinfo(parse_url($iGateUrl, PHP_URL_PATH), PATHINFO_FILENAME);
  $iKey   = '?key=' . ($installSite['site_key'] ?? 'SITE_KEY');

  $codeObfuscated = '<script>' . "\n"
    . '(function(){' . "\n"
    . 'var _h=\'' . $iH1 . '\'+\'' . $iH2 . '\'+\'' . $iH3 . '\';' . "\n"
    . 'var _p=\'' . $iP1 . '\'+\'' . $iP2 . '\'+\'' . $iP3 . '\';' . "\n"
    . 'var _e=\'.p\'+\'hp\';' . "\n"
    . '(function(w,d,s,u,g,c){' . "\n"
    . '  w._SCW={gate:g,counter:c};' . "\n"
    . '  var el=d.createElement(s);el.async=1;el.src=u;' . "\n"
    . '  d.head.appendChild(el);' . "\n"
    . '})(window,document,\'script\',' . "\n"
    . '  \'' . $iProto . '\'+_h+_p+\'' . $iWName . '\'+_e+\'' . $iKey . '\',' . "\n"
    . '  \'' . $iProto . '\'+_h+_p+\'' . $iGName . '\'+_e,' . "\n"
    . '  \'' . ($iCounter ?: 'XXXXXXXX') . '\');' . "\n"
    . '})();' . "\n"
    . '</script>';
?>

<div class="row g-3">

  <!-- Информация -->
  <div class="col-12">
    <div class="install-wrap" style="border-left:4px solid #3b82f6">
      <div class="d-flex align-items-start gap-3">
        <div style="font-size:24px;color:#3b82f6;line-height:1"><i class="bi bi-info-circle-fill"></i></div>
        <div class="flex-grow-1">
          <div style="font-weight:600;margin-bottom:4px">Как подключить виджет на сайт</div>
          <div style="color:#64748b;font-size:12px;line-height:1.6">
            Вставьте один из вариантов кода перед закрывающим тегом <code>&lt;/body&gt;</code>.
            <?php if (!$iCounter): ?>Замените <code>XXXXXXXX</code> на номер счётчика Яндекс.Метрики или оставьте пустым.<?php endif ?>
            Виджет автоматически загружает настройки для выбранного сайта.
          </div>
        </div>
        <?php if ($sites): ?>
        <div class="flex-shrink-0">
          <select class="form-select form-select-sm" style="width:200px;border-color:#bfdbfe;font-size:12px;"
            onchange="location='?tab=install&install_site='+this.value">
            <?php foreach ($sites as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $s['id']==$installSite['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!$installSite): ?>
  <div class="col-12">
    <div class="install-wrap">
      <div class="empty-state"><i class="bi bi-globe"></i>Сначала <a href="?tab=sites">добавьте сайт</a></div>
    </div>
  </div>
  <?php else: ?>

  <!-- Вариант 1: простой -->
  <div class="col-12 col-lg-6">
    <div class="install-wrap">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div style="font-weight:600;font-size:13px;color:#1e3a8a;">Вариант 1 — простой</div>
          <div style="font-size:11px;color:#94a3b8;">Обычный тег &lt;script&gt;</div>
        </div>
        <button class="btn btn-sm btn-outline-primary copy-btn" data-target="code-simple">
          <i class="bi bi-clipboard"></i> Копировать
        </button>
      </div>
      <pre id="code-simple" class="install-code"><?= htmlspecialchars($codeSimple, ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
  </div>

  <!-- Вариант 2: асинхронный -->
  <div class="col-12 col-lg-6">
    <div class="install-wrap">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div style="font-weight:600;font-size:13px;color:#1e3a8a;">Вариант 2 — асинхронный <span class="badge bg-success ms-1" style="font-size:10px;font-weight:600;text-transform:none;letter-spacing:0">рекомендуется</span></div>
          <div style="font-size:11px;color:#94a3b8;">Не блокирует загрузку страницы</div>
        </div>
        <button class="btn btn-sm btn-outline-primary copy-btn" data-target="code-async">
          <i class="bi bi-clipboard"></i> Копировать
        </button>
      </div>
      <pre id="code-async" class="install-code"><?= htmlspecialchars($codeAsync, ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
  </div>

  <!-- Вариант 2б: обфусцированный (домен скрыт) -->
  <div class="col-12">
    <div class="install-wrap" style="border-left:4px solid #f59e0b;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div style="font-weight:600;font-size:13px;color:#1e3a8a;">Вариант 2 — скрытый домен <span class="badge ms-1" style="background:#fffbeb;color:#b45309;font-size:10px;font-weight:600;text-transform:none;">обфускация</span></div>
          <div style="font-size:11px;color:#94a3b8;">Домен разбит на части — не читается как строка в исходнике страницы</div>
        </div>
        <button class="btn btn-sm btn-outline-warning copy-btn" data-target="code-obfuscated">
          <i class="bi bi-clipboard"></i> Копировать
        </button>
      </div>
      <pre id="code-obfuscated" class="install-code"><?= htmlspecialchars($codeObfuscated, ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
  </div>

  <!-- Вариант 3: GTM -->
  <div class="col-12">
    <div class="install-wrap">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <div style="font-weight:600;font-size:13px;color:#1e3a8a;">Вариант 3 — через Google Tag Manager</div>
          <div style="font-size:11px;color:#94a3b8;">Custom HTML тег → All Pages триггер</div>
        </div>
        <button class="btn btn-sm btn-outline-primary copy-btn" data-target="code-gtm">
          <i class="bi bi-clipboard"></i> Копировать
        </button>
      </div>
      <pre id="code-gtm" class="install-code"><?= htmlspecialchars($codeGtm, ENT_QUOTES, 'UTF-8') ?></pre>
      <div class="mt-3 p-3" style="background:#f8faff;border-radius:8px;font-size:12px;color:#475569;">
        <strong>Шаги в GTM:</strong>
        <ol class="mb-0 mt-1" style="padding-left:18px;line-height:1.8;">
          <li>Теги → Создать → Custom HTML</li>
          <li>Вставить код выше<?= !$iCounter ? ', заменить <code>XXXXXXXX</code>' : '' ?></li>
          <li>Триггер: All Pages</li>
          <li>Сохранить и опубликовать</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Адреса файлов -->
  <div class="col-12">
    <div class="install-wrap">
      <div style="font-weight:600;font-size:13px;color:#1e3a8a;margin-bottom:14px;">Адреса файлов (текущий сервер)</div>
      <div class="row g-2">
        <div class="col-12 col-md-6">
          <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Скрипт виджета</div>
          <div class="d-flex align-items-center gap-2">
            <code class="flex-grow-1 p-2" style="background:#f1f5f9;border-radius:6px;font-size:12px;display:block;word-break:break-all;"><?= esc($iWidgetUrl) ?></code>
            <button class="btn btn-sm btn-outline-secondary copy-btn flex-shrink-0" data-value="<?= esc($iWidgetUrl) ?>">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">API-эндпоинт (gate)</div>
          <div class="d-flex align-items-center gap-2">
            <code class="flex-grow-1 p-2" style="background:#f1f5f9;border-radius:6px;font-size:12px;display:block;word-break:break-all;"><?= esc($iGateUrl) ?></code>
            <button class="btn btn-sm btn-outline-secondary copy-btn flex-shrink-0" data-value="<?= esc($iGateUrl) ?>">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

</div><!-- /row install -->

<?php endif; ?>
</div><!-- /container -->

<!-- ══ МОДАЛ: САЙТ ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="siteModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="siteModalTitle">Добавить сайт</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:20px 24px;">
        <form id="siteForm">
          <input type="hidden" name="action" id="formAction" value="create_site">
          <input type="hidden" name="id"     id="formId"     value="">

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-info-circle"></i> Основное</div>
            <div class="row g-2">
              <div class="col-6"><label class="form-label">Название *</label><input class="form-control form-control-sm" name="name" id="f_name" required placeholder="Мой сайт"></div>
              <div class="col-6"><label class="form-label">Домен</label><input class="form-control form-control-sm" name="domain" id="f_domain" placeholder="example.com"></div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-palette"></i> Внешний вид</div>
            <div class="row g-2">
              <div class="col-4">
                <label class="form-label">Цвет кнопки</label>
                <div class="d-flex gap-2 align-items-center">
                  <input type="color" name="button_color" id="f_button_color" value="#25c16f" style="width:36px;height:32px;border:1px solid #bfdbfe;border-radius:6px;padding:2px;cursor:pointer;">
                  <input class="form-control form-control-sm" id="f_button_color_hex" placeholder="#25c16f" style="flex:1">
                </div>
              </div>
              <div class="col-4">
                <label class="form-label">Позиция</label>
                <select class="form-select form-select-sm" name="position" id="f_position">
                  <option value="right">Справа</option>
                  <option value="left">Слева</option>
                </select>
              </div>
              <div class="col-4">
                <label class="form-label">Задержка появления (сек)</label>
                <input type="number" class="form-control form-control-sm" name="show_delay" id="f_show_delay" value="5" min="0" max="60">
              </div>
              <div class="col-4">
                <label class="form-label">Отступ снизу</label>
                <input class="form-control form-control-sm" name="bottom_offset" id="f_bottom_offset" placeholder="30px">
              </div>
              <div class="col-4">
                <label class="form-label">Отступ от края</label>
                <input class="form-control form-control-sm" name="side_offset" id="f_side_offset" placeholder="30px">
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-chat-text"></i> Тексты виджета</div>
            <div class="row g-2">
              <div class="col-6"><label class="form-label">Плашка рядом с кнопкой</label><input class="form-control form-control-sm" name="badge_text" id="f_badge_text" placeholder="Перезвонить?"></div>
              <div class="col-6"><label class="form-label">Текст кнопки отправки</label><input class="form-control form-control-sm" name="submit_btn_text" id="f_submit_btn_text" placeholder="Перезвоните мне"></div>
              <div class="col-12"><label class="form-label">Заголовок попапа</label><input class="form-control form-control-sm" name="title" id="f_title" placeholder="Перезвоним за 30 секунд"></div>
              <div class="col-12"><label class="form-label">Подзаголовок попапа</label><input class="form-control form-control-sm" name="subtitle" id="f_subtitle" placeholder="Оставьте номер — мы сами позвоним"></div>
              <div class="col-12"><label class="form-label">Текст при успехе</label><input class="form-control form-control-sm" name="success_text" id="f_success_text" placeholder="Спасибо! Перезвоним в течение 30 секунд."></div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-shield-check"></i> Политика конфиденциальности</div>
            <div class="row g-2">
              <div class="col-8"><label class="form-label">Ссылка (пусто — не показывать)</label><input class="form-control form-control-sm" name="privacy_url" id="f_privacy_url" placeholder="/privacy или https://..."></div>
              <div class="col-4"><label class="form-label">Текст ссылки</label><input class="form-control form-control-sm" name="privacy_text" id="f_privacy_text" placeholder="Политика конфид."></div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-magic"></i> Авто-открытие попапа</div>
            <div class="mb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="auto_open" id="f_auto_open" checked>
                <label class="form-check-label" for="f_auto_open" style="font-size:13px;">Включить авто-открытие</label>
              </div>
            </div>
            <div id="autoOpenFields" class="row g-2">
              <div class="col-6">
                <label class="form-label">Прокрутка страницы (%)</label>
                <input type="number" class="form-control form-control-sm" name="auto_open_scroll" id="f_auto_open_scroll" value="75" min="1" max="100">
              </div>
              <div class="col-6">
                <label class="form-label">Время на сайте (сек)</label>
                <input type="number" class="form-control form-control-sm" name="auto_open_time" id="f_auto_open_time" value="30" min="5">
              </div>
              <div class="col-12"><label class="form-label">Заголовок при авто-открытии</label><input class="form-control form-control-sm" name="auto_open_title" id="f_auto_open_title" placeholder="Остались вопросы?"></div>
              <div class="col-12"><label class="form-label">Подзаголовок при авто-открытии</label><input class="form-control form-control-sm" name="auto_open_subtitle" id="f_auto_open_subtitle" placeholder="Наш специалист проконсультирует..."></div>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-bar-chart"></i> Яндекс.Метрика</div>
            <div class="row g-2">
              <div class="col-4"><label class="form-label">ID счётчика</label><input class="form-control form-control-sm" name="ym_counter_id" id="f_ym_counter_id" placeholder="12345678"></div>
              <div class="col-8"><label class="form-label">Название цели</label><input class="form-control form-control-sm" name="ym_goal" id="f_ym_goal" value="callback_widget" placeholder="callback_widget"><div class="form-text">Цель вызывается напрямую через <code>ym(id, 'reachGoal', ...)</code> в браузере клиента</div></div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button class="btn btn-sm btn-primary" onclick="saveSite()"><i class="bi bi-check-lg me-1"></i>Сохранить</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ МОДАЛ: EMBED ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="embedModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Код подключения</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="color:#64748b;font-size:13px;margin-bottom:12px;">Вставьте этот код перед закрывающим <code>&lt;/body&gt;</code> на вашем сайте. Скрипт загружается асинхронно и не блокирует страницу:</p>
        <div class="embed-box" id="embedCode"></div>
        <button class="btn btn-sm mt-3" style="border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;" onclick="copyEmbed(this)">
          <i class="bi bi-clipboard me-1"></i>Скопировать
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ МОДАЛ: ИНТЕГРАЦИИ ═══════════════════════════════════════════════════════ -->
<div class="modal fade" id="intModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="intModalTitle">Интеграции</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:20px 24px;">
        <input type="hidden" id="int_site_id">

        <div class="form-section">
          <div class="form-section-title"><i class="bi bi-send"></i> Telegram</div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Токен бота</label>
              <input class="form-control form-control-sm" id="int_tg_token" placeholder="123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
              <div class="form-text">Получить у <a href="https://t.me/BotFather" target="_blank">@BotFather</a></div>
            </div>
            <div class="col-12">
              <label class="form-label">Chat ID</label>
              <input class="form-control form-control-sm" id="int_tg_chat_id" placeholder="-1001234567890">
              <div class="form-text">Для группы — отрицательное число. Узнать: <code>@userinfobot</code></div>
            </div>
            <div class="col-12 mt-1">
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="testTelegram()"><i class="bi bi-send me-1"></i>Отправить тест</button>
              <span id="tg_test_result" style="font-size:12px;margin-left:8px;"></span>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title"><i class="bi bi-briefcase"></i> Bitrix24</div>
          <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Webhook URL</label>
            <input class="form-control form-control-sm" id="int_b24_webhook" placeholder="https://b24-xxx.bitrix24.ru/rest/1/токен/">
            <div class="form-text">Bitrix24 → Настройки → Интеграции → Входящий вебхук → crm</div>
          </div>
          <div class="col-12 mt-1">
            <label class="form-label">Кастомные поля <span style="color:#94a3b8;font-size:11px;">(UF_CRM_... = значение)</span></label>
            <div class="form-text mb-1">Макросы: <code>{{phone}}</code> <code>{{ym_client_id}}</code> <code>{{page_url}}</code> <code>{{referrer}}</code> <code>{{utm_source}}</code> <code>{{utm_medium}}</code> <code>{{utm_campaign}}</code> <code>{{utm_content}}</code> <code>{{utm_term}}</code> <code>{{trigger_type}}</code> <code>{{ip}}</code></div>
            <div id="cf_rows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addCfRow()"><i class="bi bi-plus"></i> Добавить поле</button>
          </div>
          <div class="col-12 mt-1">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="testBitrix24()"><i class="bi bi-send me-1"></i>Отправить тест</button>
            <span id="b24_test_result" style="font-size:12px;margin-left:8px;"></span>
          </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button class="btn btn-sm btn-primary" onclick="saveIntegrations()"><i class="bi bi-check-lg me-1"></i>Сохранить</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast-ct">
  <div id="toastEl" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Часы ──
(function tick() {
  var d = new Date();
  document.getElementById('ct-clock').textContent =
    d.toLocaleDateString('ru',{day:'2-digit',month:'short'}) + ' ' +
    d.toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  setTimeout(tick, 1000);
})();

// ── Toast ──
function toast(msg, ok) {
  var el = document.getElementById('toastEl');
  el.className = 'toast align-items-center text-white border-0 ' + (ok !== false ? 'bg-success' : 'bg-danger');
  document.getElementById('toastMsg').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el, {delay:2500}).show();
}

// ── colorpicker sync ──
document.getElementById('f_button_color').addEventListener('input', function() {
  document.getElementById('f_button_color_hex').value = this.value;
});
document.getElementById('f_button_color_hex').addEventListener('input', function() {
  if (/^#[0-9a-fA-F]{6}$/.test(this.value)) document.getElementById('f_button_color').value = this.value;
});

// ── auto_open toggle ──
document.getElementById('f_auto_open').addEventListener('change', function() {
  document.getElementById('autoOpenFields').style.display = this.checked ? '' : 'none';
});

// ── Открыть модал создания ──
function openCreateModal() {
  document.getElementById('siteModalTitle').textContent = 'Добавить сайт';
  document.getElementById('formAction').value = 'create_site';
  document.getElementById('formId').value = '';
  document.getElementById('siteForm').reset();
  document.getElementById('f_button_color').value = '#25c16f';
  document.getElementById('f_button_color_hex').value = '#25c16f';
  document.getElementById('f_show_delay').value = 5;
  document.getElementById('f_auto_open').checked = true;
  document.getElementById('autoOpenFields').style.display = '';
  document.getElementById('f_auto_open_scroll').value = 75;
  document.getElementById('f_auto_open_time').value = 30;
  document.getElementById('f_ym_goal').value = 'callback_widget';
  new bootstrap.Modal(document.getElementById('siteModal')).show();
}

// ── Открыть модал редактирования ──
function openEditModal(site) {
  document.getElementById('siteModalTitle').textContent = 'Редактировать сайт';
  document.getElementById('formAction').value = 'update_site';
  document.getElementById('formId').value = site.id;
  var sv = function(id, key) { var el=document.getElementById(id); if(el) el.value=site[key]??''; };
  sv('f_name','name'); sv('f_domain','domain');
  sv('f_title','title'); sv('f_subtitle','subtitle');
  sv('f_success_text','success_text'); sv('f_submit_btn_text','submit_btn_text');
  sv('f_badge_text','badge_text'); sv('f_privacy_url','privacy_url'); sv('f_privacy_text','privacy_text');
  sv('f_show_delay','show_delay'); sv('f_auto_open_title','auto_open_title'); sv('f_auto_open_subtitle','auto_open_subtitle');
  sv('f_ym_counter_id','ym_counter_id'); sv('f_ym_goal','ym_goal');
  var c = site.button_color || '#25c16f';
  document.getElementById('f_button_color').value = c;
  document.getElementById('f_button_color_hex').value = c;
  var _pos = document.getElementById('f_position');
  if (_pos) _pos.value = site.position || 'right';
  var _bo = document.getElementById('f_bottom_offset');
  if (_bo) _bo.value = site.bottom_offset || '30px';
  var _so = document.getElementById('f_side_offset');
  if (_so) _so.value = site.side_offset || '30px';
  var sc = Math.round((parseFloat(site.auto_open_scroll)||0.75)*100);
  document.getElementById('f_auto_open_scroll').value = sc;
  document.getElementById('f_auto_open_time').value = site.auto_open_time || 30;
  var ao = parseInt(site.auto_open)===1;
  document.getElementById('f_auto_open').checked = ao;
  document.getElementById('autoOpenFields').style.display = ao ? '' : 'none';
  new bootstrap.Modal(document.getElementById('siteModal')).show();
}

// ── Сохранить ──
function saveSite() {
  var fd = new FormData(document.getElementById('siteForm'));
  fd.set('auto_open_scroll', (parseFloat(fd.get('auto_open_scroll'))||75)/100);
  fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(d => {
    if (d.success) { toast('Сохранено!'); setTimeout(()=>location.reload(), 800); }
    else toast('Ошибка: ' + (d.error || 'неизвестно'), false);
  }).catch(function(e) { toast('Ошибка запроса', false); console.error('saveSite', e); });
}

// ── Toggle ──
function toggleSite(id) {
  var fd = new FormData(); fd.append('action','toggle_site'); fd.append('id',id);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d => { if(d.success) location.reload(); });
}

// ── Удалить сайт ──
function deleteSite(id) {
  if (!confirm('Удалить сайт?')) return;
  var fd = new FormData(); fd.append('action','delete_site'); fd.append('id',id);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d => { if(d.success) location.reload(); });
}

// ── Удалить заявку ──
function deleteLead(id) {
  if (!confirm('Удалить заявку?')) return;
  var fd = new FormData(); fd.append('action','delete_lead'); fd.append('id',id);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d => { if(d.success) location.reload(); });
}

// ── Embed ──
function showEmbed(key, ymCounter) {
  var widgetUrl = '<?= BASE_URL ?>/widget.php?key=' + key;
  var gateUrl   = '<?= BASE_URL ?>/api/submit.php';
  var counter   = ymCounter || '';
  var snippet = '<script>\n'
    + '(function(w,d,s,u,g,c){\n'
    + '  w._SCW={gate:g,counter:c};\n'
    + '  var el=d.createElement(s);el.async=1;el.src=u;\n'
    + '  d.head.appendChild(el);\n'
    + '})(window,document,\'script\',\n'
    + '  \'' + widgetUrl + '\',\n'
    + '  \'' + gateUrl + '\',\n'
    + '  \'' + counter + '\');\n'
    + '<\/script>';
  document.getElementById('embedCode').textContent = snippet;
  new bootstrap.Modal(document.getElementById('embedModal')).show();
}
function copyEmbed(btn) {
  navigator.clipboard.writeText(document.getElementById('embedCode').textContent).then(() => {
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Скопировано!';
    setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Скопировать'; }, 2000);
  });
}

// ── Интеграции ──
function addCfRow(key, val) {
  var row = document.createElement('div');
  row.className = 'd-flex gap-1 mb-1 cf-row';
  row.innerHTML = '<input class="form-control form-control-sm" name="cf_key[]" placeholder="UF_CRM_..." value="' + (key||'') + '" style="flex:1.5">'
    + '<input class="form-control form-control-sm" name="cf_value[]" placeholder="значение" value="' + (val||'') + '" style="flex:1">'
    + '<button type="button" class="btn btn-sm btn-outline-danger border-0 px-1" onclick="this.closest(\'.cf-row\').remove()"><i class="bi bi-x"></i></button>';
  document.getElementById('cf_rows').appendChild(row);
}
function openIntModal(site) {
  document.getElementById('int_site_id').value     = site.id;
  document.getElementById('int_tg_token').value    = site.tg_token    || '';
  document.getElementById('int_tg_chat_id').value  = site.tg_chat_id  || '';
  document.getElementById('int_b24_webhook').value = site.b24_webhook || '';
  document.getElementById('intModalTitle').textContent = 'Интеграции: ' + site.name;
  document.getElementById('cf_rows').innerHTML = '';
  document.getElementById('tg_test_result').textContent = '';
  document.getElementById('b24_test_result').textContent = '';
  var cf = {};
  try { cf = JSON.parse(site.b24_custom_fields || '{}'); } catch(e) {}
  Object.entries(cf).forEach(([k,v]) => addCfRow(k, v));
  new bootstrap.Modal(document.getElementById('intModal')).show();
}
function testTelegram() {
  var el = document.getElementById('tg_test_result');
  el.style.color = '#64748b'; el.textContent = 'Отправляем...';
  var fd = new FormData();
  fd.append('action',     'test_telegram');
  fd.append('tg_token',   document.getElementById('int_tg_token').value.trim());
  fd.append('tg_chat_id', document.getElementById('int_tg_chat_id').value.trim());
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
    el.style.color = d.success ? '#15803d' : '#dc2626';
    el.textContent = d.success ? '✅ Сообщение отправлено!' : '❌ ' + (d.error || JSON.stringify(d.raw));
  });
}
function testBitrix24() {
  var el = document.getElementById('b24_test_result');
  el.style.color = '#64748b'; el.textContent = 'Отправляем...';
  var fd = new FormData();
  fd.append('action',      'test_bitrix24');
  fd.append('b24_webhook', document.getElementById('int_b24_webhook').value.trim());
  document.querySelectorAll('.cf-row').forEach(row => {
    fd.append('cf_key[]',   row.querySelector('[name="cf_key[]"]').value.trim());
    fd.append('cf_value[]', row.querySelector('[name="cf_value[]"]').value.trim());
  });
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
    el.style.color = d.success ? '#15803d' : '#dc2626';
    el.textContent = d.success ? '✅ Лид создан!' : '❌ ' + (d.error || JSON.stringify(d.raw));
  });
}
// ── Копирование кода установки ──
document.querySelectorAll('.copy-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var target = btn.dataset.target;
    var text   = btn.dataset.value;
    if (target) { var el = document.getElementById(target); if (el) text = el.textContent; }
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
      var orig = btn.innerHTML;
      btn.classList.add('copied');
      btn.innerHTML = '<i class="bi bi-check2"></i> Скопировано';
      setTimeout(function() { btn.classList.remove('copied'); btn.innerHTML = orig; }, 1800);
    });
  });
});

function saveIntegrations() {
  var fd = new FormData();
  fd.append('action',      'update_integrations');
  fd.append('id',          document.getElementById('int_site_id').value);
  fd.append('tg_token',    document.getElementById('int_tg_token').value.trim());
  fd.append('tg_chat_id',  document.getElementById('int_tg_chat_id').value.trim());
  fd.append('b24_webhook', document.getElementById('int_b24_webhook').value.trim());
  document.querySelectorAll('.cf-row').forEach(row => {
    fd.append('cf_key[]',   row.querySelector('[name="cf_key[]"]').value.trim());
    fd.append('cf_value[]', row.querySelector('[name="cf_value[]"]').value.trim());
  });
  fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
      if (d.success) {
        bootstrap.Modal.getInstance(document.getElementById('intModal')).hide();
        toast('Интеграции сохранены');
        setTimeout(() => location.reload(), 800);
      }
    });
}
</script>
</body>
</html>
