<?php
// ─── Путь к БД ────────────────────────────────────────────────────────────────
define('DB_PATH',   __DIR__ . '/db/widget.db');
define('LOG_FILE',  __DIR__ . '/logs/leads.txt');

// ─── Авторизация в админке ────────────────────────────────────────────────────
define('ADMIN_LOGIN',    'admin');
define('ADMIN_PASSWORD', 'admin123');   // сменить после первого входа

// ─── Базовый URL проекта (без слеша на конце) ────────────────────────────────
define('BASE_URL', 'http://smart-call.loc');

// ─── CSRF: секрет для HMAC-токенов (сменить на продакшене) ───────────────────
define('CSRF_SECRET', 'change-me-on-production-' . md5(__FILE__));
