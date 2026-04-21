<?php
require_once __DIR__ . '/rb.php';
require_once __DIR__ . '/config.php';

R::setup('sqlite:' . DB_PATH);
R::freeze(false);

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─── Таблица сайтов ───────────────────────────────────────────────────────────
$pdo->exec("
CREATE TABLE IF NOT EXISTS sites (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    domain          TEXT    NOT NULL DEFAULT '',
    site_key        TEXT    NOT NULL UNIQUE,

    -- Внешний вид
    button_color    TEXT    NOT NULL DEFAULT '#25c16f',
    pulse_color     TEXT    NOT NULL DEFAULT 'rgba(37,193,111,0.4)',
    position        TEXT    NOT NULL DEFAULT 'right',
    bottom_offset   TEXT    NOT NULL DEFAULT '30px',
    side_offset     TEXT    NOT NULL DEFAULT '30px',

    -- Тексты попапа
    title           TEXT    NOT NULL DEFAULT 'Перезвоним за 30 секунд',
    subtitle        TEXT    NOT NULL DEFAULT 'Оставьте номер — мы сами позвоним',
    success_text    TEXT    NOT NULL DEFAULT 'Спасибо! Перезвоним в течение 30 секунд.',
    submit_btn_text TEXT    NOT NULL DEFAULT 'Перезвоните мне',
    badge_text      TEXT    NOT NULL DEFAULT 'Перезвонить?',

    -- Политика
    privacy_url     TEXT    NOT NULL DEFAULT '',
    privacy_text    TEXT    NOT NULL DEFAULT 'Политика конфиденциальности',

    -- Таймеры
    show_delay      INTEGER NOT NULL DEFAULT 5,

    -- Авто-открытие
    auto_open           INTEGER NOT NULL DEFAULT 1,
    auto_open_scroll    REAL    NOT NULL DEFAULT 0.75,
    auto_open_time      INTEGER NOT NULL DEFAULT 30,
    auto_open_title     TEXT    NOT NULL DEFAULT 'Остались вопросы?',
    auto_open_subtitle  TEXT    NOT NULL DEFAULT 'Наш специалист проконсультирует вас бесплатно — просто оставьте номер',

    -- Аналитика
    ym_counter_id       TEXT    NOT NULL DEFAULT '',
    ym_goal             TEXT    NOT NULL DEFAULT 'callback_widget',
    ym_access_token     TEXT    NOT NULL DEFAULT '',
    topmailru_id        TEXT    NOT NULL DEFAULT '',
    topmailru_goal      TEXT    NOT NULL DEFAULT '',

    is_active       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);
");

// ─── Таблица заявок ───────────────────────────────────────────────────────────
$pdo->exec("
CREATE TABLE IF NOT EXISTS leads (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id         INTEGER NOT NULL,
    phone           TEXT    NOT NULL,
    page_url        TEXT    NOT NULL DEFAULT '',
    referrer        TEXT    NOT NULL DEFAULT '',
    utm_source      TEXT    NOT NULL DEFAULT '',
    utm_medium      TEXT    NOT NULL DEFAULT '',
    utm_campaign    TEXT    NOT NULL DEFAULT '',
    utm_content     TEXT    NOT NULL DEFAULT '',
    utm_term        TEXT    NOT NULL DEFAULT '',
    ip              TEXT    NOT NULL DEFAULT '',
    user_agent      TEXT    NOT NULL DEFAULT '',
    trigger_type    TEXT    NOT NULL DEFAULT 'manual',
    ym_client_id    TEXT    NOT NULL DEFAULT '',
    goal_sent       INTEGER NOT NULL DEFAULT 0,
    goal_sent_at    TEXT,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);
");

// ─── Миграции (безопасно для существующей БД) ─────────────────────────────────
$migrations = [
    "ALTER TABLE leads ADD COLUMN ym_client_id TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE sites ADD COLUMN tg_token         TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE sites ADD COLUMN tg_chat_id       TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE sites ADD COLUMN b24_webhook      TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE sites ADD COLUMN b24_custom_fields TEXT NOT NULL DEFAULT '{}'",
];
foreach ($migrations as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* колонка уже есть */ }
}

echo "✅ База данных инициализирована: " . DB_PATH . PHP_EOL;
echo "📋 Таблицы созданы: sites, leads" . PHP_EOL;
R::close();
