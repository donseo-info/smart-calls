(function () {
    'use strict';

    // ─── Конфигурация (дефолты, перекрываются через window.__SCW_CONFIG) ──────────
    var WIDGET_CONFIG = Object.assign({
        submitUrl: '',                         // URL для отправки заявки (заполняется widget.php)
        siteKey: '',                           // ключ сайта (заполняется widget.php)
        ymCounterId: 0,                        // ID счётчика Яндекс.Метрики (0 — не отправлять)
        ymGoal: 'callback_widget',             // название цели в Метрике
        privacyUrl: '',                        // ссылка на политику конфиденциальности ('' — скрыть)
        privacyText: 'Политика конфиденциальности',
        buttonColor: '#25c16f',               // цвет кнопки
        pulseColor: 'rgba(37,193,111,0.4)',   // цвет пульсации
        position: 'right',                    // 'right' | 'left'
        bottomOffset: '30px',
        sideOffset: '30px',
        title: 'Перезвоним за 30 секунд',
        subtitle: 'Оставьте номер — мы сами позвоним',
        successText: 'Спасибо! Перезвоним в течение 30 секунд.',
        submitBtnText: 'Перезвоните мне',
        badgeText: 'Перезвонить?',
        showDelay: 5,                          // задержка появления кнопки в секундах (0 — сразу)
        autoOpen: true,
        autoOpenScroll: 0.75,
        autoOpenTime: 30,
        autoOpenTitle: 'Остались вопросы?',
        autoOpenSubtitle: 'Наш специалист проконсультирует вас бесплатно — просто оставьте номер',
        onSubmit: null,                        // кастомный callback(phone) вместо отправки на сервер
    }, window.__SCW_CONFIG || {}, (function () {
        var s = window._SCW || {};
        var o = {};
        if (s.gate)    o.submitUrl    = s.gate;
        if (s.counter) o.ymCounterId  = parseInt(s.counter, 10) || 0;
        return o;
    })());

    // ─── UTM: сохраняем в sessionStorage при загрузке (сохраняются при навигации) ─
    (function () {
        try {
            var usp = new URLSearchParams(window.location.search);
            ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(function (k) {
                var v = usp.get(k);
                if (v) sessionStorage.setItem('scw_' + k, v);
            });
        } catch (e) {}
    })();

    // ─── YM Client ID: localStorage → cookie → ym() API ─────────────────────────
    var _ymClientId  = '';
    var _ymReady     = false;

    (function resolveYm() {
        try {
            var lsVal = localStorage.getItem('_ym_uid');
            if (lsVal) {
                var m = lsVal.replace(/"/g, '').match(/\d+/);
                if (m) { _ymClientId = m[0]; _ymReady = true; return; }
            }
        } catch (e) {}
        try {
            var cm = document.cookie.match(/_ym_uid=(\d+)/);
            if (cm) { _ymClientId = cm[1]; _ymReady = true; return; }
        } catch (e) {}
        try {
            if (WIDGET_CONFIG.ymCounterId && window.ym) {
                var done = false;
                var t = setTimeout(function () {
                    if (!done) { done = true; _ymReady = true; }
                }, 800);
                ym(WIDGET_CONFIG.ymCounterId, 'getClientID', function (id) {
                    if (!done) { done = true; clearTimeout(t); _ymClientId = id || ''; _ymReady = true; }
                });
                return;
            }
        } catch (e) {}
        _ymReady = true;
    })();

    function getYmClientId(cb) {
        if (_ymReady) { cb(_ymClientId); return; }
        var attempts = 0;
        var timer = setInterval(function () {
            if (_ymReady || ++attempts > 20) { clearInterval(timer); cb(_ymClientId); }
        }, 50);
    }

    // ─── CSS ─────────────────────────────────────────────────────────────────────
    var CSS = '\
    #scw-root * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }\
    \
    #scw-root {\
        position: fixed;\
        bottom: ' + WIDGET_CONFIG.bottomOffset + ';\
        ' + WIDGET_CONFIG.position + ': 0;\
        padding-' + WIDGET_CONFIG.position + ': ' + WIDGET_CONFIG.sideOffset + ';\
        z-index: 2147483647;\
        display: flex;\
        flex-direction: column;\
        align-items: ' + (WIDGET_CONFIG.position === 'right' ? 'flex-end' : 'flex-start') + ';\
        gap: 12px;\
        max-width: 100vw;\
    }\
    \
    /* ── Кнопка ── */\
    #scw-btn {\
        position: relative;\
        width: 60px;\
        height: 60px;\
        border-radius: 50%;\
        background: ' + WIDGET_CONFIG.buttonColor + ';\
        border: none;\
        cursor: pointer;\
        display: flex;\
        align-items: center;\
        justify-content: center;\
        box-shadow: 0 4px 16px rgba(0,0,0,.25);\
        transition: transform .2s, box-shadow .2s;\
        outline: none;\
        flex-shrink: 0;\
    }\
    #scw-btn:hover { transform: scale(1.08); box-shadow: 0 6px 22px rgba(0,0,0,.32); }\
    #scw-btn:active { transform: scale(.96); }\
    #scw-btn svg { width: 28px; height: 28px; fill: #fff; transition: transform .35s; pointer-events: none; }\
    #scw-btn.scw-open svg { transform: rotate(135deg); }\
    \
    /* ── Пульсация ── */\
    #scw-btn::before,\
    #scw-btn::after {\
        content: "";\
        position: absolute;\
        inset: 0;\
        border-radius: 50%;\
        background: ' + WIDGET_CONFIG.pulseColor + ';\
        animation: scw-pulse 2s ease-out infinite;\
        pointer-events: none;\
    }\
    #scw-btn::after  { animation-delay: .8s; }\
    #scw-btn.scw-open::before,\
    #scw-btn.scw-open::after { animation: none; opacity: 0; }\
    @keyframes scw-pulse {\
        0%   { transform: scale(1);   opacity: .9; }\
        70%  { transform: scale(1.7); opacity: 0; }\
        100% { transform: scale(1.7); opacity: 0; }\
    }\
    \
    /* ── Попап ── */\
    #scw-popup {\
        background: #fff;\
        border-radius: 16px;\
        box-shadow: 0 8px 32px rgba(0,0,0,.18);\
        padding: 22px 20px 18px;\
        width: 270px;\
        max-width: calc(100vw - 20px);\
        transform-origin: bottom ' + WIDGET_CONFIG.position + ';\
        transform: scale(.7) translateY(12px);\
        opacity: 0;\
        pointer-events: none;\
        transition: transform .25s cubic-bezier(.34,1.56,.64,1), opacity .2s;\
    }\
    #scw-popup.scw-visible {\
        transform: scale(1) translateY(0);\
        opacity: 1;\
        pointer-events: auto;\
    }\
    \
    #scw-popup-title {\
        font-size: 15px;\
        font-weight: 700;\
        color: #1a1a2e;\
        line-height: 1.3;\
        margin-bottom: 4px;\
    }\
    #scw-popup-sub {\
        font-size: 12px;\
        color: #888;\
        margin-bottom: 14px;\
        line-height: 1.4;\
    }\
    \
    #scw-phone-wrap {\
        position: relative;\
        margin-bottom: 10px;\
    }\
    #scw-phone {\
        width: 100%;\
        height: 44px;\
        border: 2px solid #e8e8e8;\
        border-radius: 10px;\
        padding: 0 14px;\
        font-size: 15px;\
        color: #1a1a2e;\
        outline: none;\
        transition: border-color .2s;\
        background: #fafafa;\
    }\
    #scw-phone:focus { border-color: ' + WIDGET_CONFIG.buttonColor + '; background: #fff; }\
    #scw-phone.scw-error { border-color: #e74c3c; animation: scw-shake .35s; }\
    @keyframes scw-shake {\
        0%,100% { transform: translateX(0); }\
        20%,60% { transform: translateX(-6px); }\
        40%,80% { transform: translateX(6px); }\
    }\
    \
    #scw-submit {\
        width: 100%;\
        height: 44px;\
        border: none;\
        border-radius: 10px;\
        background: ' + WIDGET_CONFIG.buttonColor + ';\
        color: #fff;\
        font-size: 14px;\
        font-weight: 600;\
        cursor: pointer;\
        transition: filter .2s, transform .1s;\
        letter-spacing: .3px;\
    }\
    #scw-submit:hover  { filter: brightness(1.1); }\
    #scw-submit:active { transform: scale(.97); }\
    #scw-submit:disabled { filter: brightness(.85); cursor: default; }\
    \
    /* ── Политика ── */\
    #scw-privacy {\
        display: block;\
        text-align: center;\
        margin-top: 10px;\
        font-size: 11px;\
        color: #bbb;\
        text-decoration: none;\
        transition: color .15s;\
    }\
    #scw-privacy:hover { color: #888; }\
    \
    /* ── Успех ── */\
    #scw-success {\
        display: none;\
        flex-direction: column;\
        align-items: center;\
        gap: 10px;\
        padding: 6px 0 4px;\
        text-align: center;\
    }\
    #scw-success svg { width: 44px; height: 44px; }\
    #scw-success-text {\
        font-size: 13px;\
        color: #444;\
        line-height: 1.45;\
    }\
    \
    /* ── Закрыть ── */\
    #scw-close {\
        position: absolute;\
        top: 10px;\
        right: 10px;\
        width: 22px;\
        height: 22px;\
        border: none;\
        background: none;\
        cursor: pointer;\
        display: flex;\
        align-items: center;\
        justify-content: center;\
        color: #bbb;\
        border-radius: 50%;\
        transition: background .15s, color .15s;\
        font-size: 16px;\
        line-height: 1;\
    }\
    #scw-close:hover { background: #f0f0f0; color: #555; }\
    \
    /* ── Затемнение фона ── */\
    #scw-overlay {\
        position: fixed;\
        inset: 0;\
        background: rgba(0,0,0,0);\
        z-index: 2147483646;\
        pointer-events: none;\
        transition: background .3s ease;\
    }\
    #scw-overlay.scw-overlay-show {\
        background: rgba(0,0,0,.35);\
        pointer-events: auto;\
    }\
    \
    /* ── Плашка "Перезвонить?" ── */\
    #scw-btn-row {\
        display: flex;\
        flex-direction: ' + (WIDGET_CONFIG.position === 'right' ? 'row' : 'row-reverse') + ';\
        align-items: center;\
        gap: 10px;\
    }\
    #scw-badge {\
        position: relative;\
        background: #fff;\
        border-radius: 20px;\
        padding: 9px 14px;\
        font-size: 14px;\
        font-weight: 600;\
        color: #1a1a2e;\
        box-shadow: 0 4px 18px rgba(0,0,0,.15);\
        white-space: nowrap;\
        cursor: pointer;\
        user-select: none;\
        opacity: 0;\
        pointer-events: none;\
        transition: opacity .0s, transform .0s;\
    }\
    #scw-badge::after {\
        content: "";\
        position: absolute;\
        ' + (WIDGET_CONFIG.position === 'right' ? 'right: -7px; border-left: 7px solid #fff; border-right: 0;' : 'left: -7px; border-right: 7px solid #fff; border-left: 0;') + '\
        top: 50%;\
        transform: translateY(-50%);\
        border-top: 7px solid transparent;\
        border-bottom: 7px solid transparent;\
        filter: drop-shadow(' + (WIDGET_CONFIG.position === 'right' ? '2px' : '-2px') + ' 0 2px rgba(0,0,0,.06));\
    }\
    @keyframes scw-badge-in {\
        0%   { opacity: 0; transform: translateX(' + (WIDGET_CONFIG.position === 'right' ? '-20px' : '20px') + '); }\
        60%  { opacity: 1; transform: translateX(' + (WIDGET_CONFIG.position === 'right' ? '5px' : '-5px') + '); }\
        80%  { transform: translateX(' + (WIDGET_CONFIG.position === 'right' ? '-3px' : '3px') + '); }\
        100% { opacity: 1; transform: translateX(0); }\
    }\
    @keyframes scw-badge-out {\
        0%   { opacity: 1; transform: translateX(0); }\
        100% { opacity: 0; transform: translateX(' + (WIDGET_CONFIG.position === 'right' ? '-12px' : '12px') + '); }\
    }\
    #scw-badge.scw-badge-show {\
        animation: scw-badge-in .5s cubic-bezier(.34,1.56,.64,1) forwards;\
        pointer-events: auto;\
    }\
    #scw-badge.scw-badge-hide {\
        animation: scw-badge-out .25s ease forwards;\
        pointer-events: none;\
    }\
    \
    /* ── Появление кнопки с задержкой ── */\
    #scw-root.scw-hidden {\
        opacity: 0;\
        pointer-events: none;\
    }\
    @keyframes scw-entrance {\
        0%   { opacity: 0; transform: scale(0) translateY(0); }\
        50%  { opacity: 1; transform: scale(1.1) translateY(-28px); }\
        70%  { transform: scale(0.95) translateY(6px); }\
        85%  { transform: scale(1.05) translateY(-10px); }\
        100% { opacity: 1; transform: scale(1) translateY(0); }\
    }\
    #scw-root.scw-visible {\
        animation: scw-entrance .7s cubic-bezier(.36,.07,.19,.97) forwards;\
        transform-origin: bottom ' + WIDGET_CONFIG.position + ';\
    }\
    ';

    // ─── SVG иконки ──────────────────────────────────────────────────────────────
    var ICON_PHONE = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24 11.47 11.47 0 003.58.57 1 1 0 011 1V21a1 1 0 01-1 1A18 18 0 013 5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.58a1 1 0 01-.25 1.01z"/></svg>';
    var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    var ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" fill="#25c16f" opacity=".15"/><circle cx="12" cy="12" r="11" stroke="#25c16f" stroke-width="1.5"/><polyline points="7,12.5 10.5,16 17,9" stroke="#25c16f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>';

    // ─── Маска телефона ───────────────────────────────────────────────────────────
    function maskPhone(input) {
        input.addEventListener('input', function () {
            var val = input.value.replace(/\D/g, '');
            if (!val) { input.value = ''; return; }

            if (val[0] === '7' || val[0] === '8') val = val.slice(1);

            var res = '+7 (';
            if (val.length > 0)  res += val.slice(0, 3);
            if (val.length >= 4) res += ') ' + val.slice(3, 6);
            if (val.length >= 7) res += '-' + val.slice(6, 8);
            if (val.length >= 9) res += '-' + val.slice(8, 10);
            input.value = res;
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && input.value === '+7 (') {
                e.preventDefault();
                input.value = '';
            }
        });

        input.addEventListener('focus', function () {
            if (!input.value) input.value = '+7 (';
        });

        input.addEventListener('blur', function () {
            if (input.value === '+7 (') input.value = '';
        });
    }

    // ─── Валидация ────────────────────────────────────────────────────────────────
    function isValidPhone(val) {
        return val.replace(/\D/g, '').length === 11;
    }

    // ─── Построение DOM ──────────────────────────────────────────────────────────
    function buildWidget() {
        // стили
        var style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);

        // затемнение
        var overlay = document.createElement('div');
        overlay.id = 'scw-overlay';
        document.body.appendChild(overlay);

        // корень
        var root = document.createElement('div');
        root.id = 'scw-root';

        // попап
        var popup = document.createElement('div');
        popup.id = 'scw-popup';
        popup.innerHTML =
            '<button id="scw-close" aria-label="Закрыть">' + ICON_CLOSE + '</button>' +
            '<div id="scw-popup-title">' + WIDGET_CONFIG.title + '</div>' +
            '<div id="scw-popup-sub">' + WIDGET_CONFIG.subtitle + '</div>' +
            '<div id="scw-phone-wrap">' +
                '<input id="scw-phone" type="tel" placeholder="+7 (___) ___-__-__" autocomplete="tel">' +
            '</div>' +
            '<input id="scw-hp" type="email" name="email" tabindex="-1" autocomplete="off" style="position:absolute!important;left:-9999px!important;opacity:0!important;height:0!important;pointer-events:none!important;" aria-hidden="true">' +
            '<button id="scw-submit">' + WIDGET_CONFIG.submitBtnText + '</button>' +
            (WIDGET_CONFIG.privacyUrl ? '<a id="scw-privacy" href="' + WIDGET_CONFIG.privacyUrl + '" target="_blank" rel="noopener">' + WIDGET_CONFIG.privacyText + '</a>' : '') +
            '<div id="scw-success">' +
                ICON_CHECK +
                '<div id="scw-success-text">' + WIDGET_CONFIG.successText + '</div>' +
            '</div>';

        // ряд: плашка + кнопка
        var btnRow = document.createElement('div');
        btnRow.id = 'scw-btn-row';

        var badge = document.createElement('div');
        badge.id = 'scw-badge';
        badge.textContent = WIDGET_CONFIG.badgeText;

        var btn = document.createElement('button');
        btn.id = 'scw-btn';
        btn.setAttribute('aria-label', 'Обратный звонок');
        btn.innerHTML = ICON_PHONE;

        btnRow.appendChild(badge);
        btnRow.appendChild(btn);

        root.appendChild(popup);
        root.appendChild(btnRow);

        // скрываем до истечения задержки
        if (WIDGET_CONFIG.showDelay > 0) {
            root.classList.add('scw-hidden');
        }

        document.body.appendChild(root);

        // показываем кнопку после задержки с анимацией прыжка
        setTimeout(function () {
            root.classList.remove('scw-hidden');
            root.classList.add('scw-visible');

            // плашка появляется через 2 сек после кнопки
            setTimeout(function () {
                badge.classList.add('scw-badge-show');
            }, 2000);
        }, WIDGET_CONFIG.showDelay * 1000);

        // refs
        var phoneInput  = document.getElementById('scw-phone');
        var submitBtn   = document.getElementById('scw-submit');
        var closeBtn    = document.getElementById('scw-close');
        var successDiv  = document.getElementById('scw-success');
        var formContent = [
            document.getElementById('scw-popup-title'),
            document.getElementById('scw-popup-sub'),
            document.getElementById('scw-phone-wrap'),
            submitBtn
        ];

        var autoOpened = false; // флаг: попап открыт автоматически
        maskPhone(phoneInput);

        // ── Скрыть плашку ──
        function hideBadge() {
            if (badge.classList.contains('scw-badge-show')) {
                badge.classList.remove('scw-badge-show');
                badge.classList.add('scw-badge-hide');
            }
        }

        // ── Открыть / закрыть попап ──
        function openPopup() {
            hideBadge();
            overlay.classList.add('scw-overlay-show');
            popup.classList.add('scw-visible');
            btn.classList.add('scw-open');
            btn.innerHTML = ICON_CLOSE;
            setTimeout(function () { phoneInput.focus(); }, 200);
        }

        function closePopup() {
            overlay.classList.remove('scw-overlay-show');
            popup.classList.remove('scw-visible');
            btn.classList.remove('scw-open');
            btn.innerHTML = ICON_PHONE;

            // возвращаем исходный текст после закрытия
            var titleEl    = document.getElementById('scw-popup-title');
            var subtitleEl = document.getElementById('scw-popup-sub');
            if (titleEl)    titleEl.textContent    = WIDGET_CONFIG.title;
            if (subtitleEl) subtitleEl.textContent = WIDGET_CONFIG.subtitle;
        }

        btn.addEventListener('click', function () {
            popup.classList.contains('scw-visible') ? closePopup() : openPopup();
        });

        badge.addEventListener('click', openPopup);

        closeBtn.addEventListener('click', closePopup);
        overlay.addEventListener('click', closePopup);

        // ── Отправка ──
        submitBtn.addEventListener('click', function () {
            var phone = phoneInput.value;

            if (!isValidPhone(phone)) {
                phoneInput.classList.add('scw-error');
                phoneInput.addEventListener('animationend', function () {
                    phoneInput.classList.remove('scw-error');
                }, { once: true });
                phoneInput.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправляем...';

            // ── Цель в Яндекс.Метрику — сразу при отправке ──
            try {
                if (WIDGET_CONFIG.ymCounterId && typeof ym === 'function' && !document.cookie.match(/scw_ym_goal=1/)) {
                    ym(WIDGET_CONFIG.ymCounterId, 'reachGoal', WIDGET_CONFIG.ymGoal);
                    var _d = new Date(); _d.setMonth(_d.getMonth() + 1);
                    document.cookie = 'scw_ym_goal=1;expires=' + _d.toUTCString() + ';path=/';
                }
            } catch (e) {}

            // пользовательский обработчик или отправка на сервер
            var handler;
            if (typeof WIDGET_CONFIG.onSubmit === 'function') {
                handler = WIDGET_CONFIG.onSubmit(phone);
            } else if (WIDGET_CONFIG.submitUrl && WIDGET_CONFIG.siteKey) {
                var utms = {};
                try {
                    var usp = new URLSearchParams(window.location.search);
                    ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(function (k) {
                        utms[k] = usp.get(k) || sessionStorage.getItem('scw_' + k) || '';
                    });
                } catch(e) {}

                function doSend(ymId) {
                    return fetch(WIDGET_CONFIG.submitUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        keepalive: true,
                        body: JSON.stringify(Object.assign({
                            site_key:      WIDGET_CONFIG.siteKey,
                            phone:         phone,
                            page_url:      window.location.href,
                            referrer:      document.referrer,
                            trigger_type:  autoOpened ? 'auto' : 'manual',
                            ym_client_id:  ymId,
                            has_ym:        ymId ? 1 : 0,
                            _csrf:         WIDGET_CONFIG.csrfToken || '',
                            email:         (document.getElementById('scw-hp') || {}).value || '',
                        }, utms))
                    }).then(function (r) { if (!r.ok) throw r.status; });
                }

                handler = new Promise(function (resolve, reject) {
                    getYmClientId(function (ymId) {
                        doSend(ymId).then(resolve).catch(function (status) {
                            // сеть упала — не блокируем
                            if (status === undefined) { resolve(); return; }
                            // сервер вернул ошибку — повтор через 5 сек
                            submitBtn.textContent = 'Повторяем...';
                            setTimeout(function () {
                                doSend(ymId).then(resolve).catch(reject);
                            }, 5000);
                        });
                    });
                });
            } else {
                handler = Promise.resolve();
            }

            Promise.resolve(handler).then(function () {
                // показываем успех
                formContent.forEach(function (el) { el.style.display = 'none'; });
                successDiv.style.display = 'flex';

                // через 4 сек закрываем
                setTimeout(function () {
                    closePopup();
                    // сбрасываем форму
                    setTimeout(function () {
                        formContent.forEach(function (el) { el.style.display = ''; });
                        successDiv.style.display = 'none';
                        phoneInput.value = '';
                        submitBtn.disabled = false;
                        submitBtn.textContent = WIDGET_CONFIG.submitBtnText;
                    }, 400);
                }, 4000);
            }).catch(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = WIDGET_CONFIG.submitBtnText;
            });
        });

        // Enter в поле телефона
        phoneInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') submitBtn.click();
        });

        // ── Авто-открытие попапа ──
        if (WIDGET_CONFIG.autoOpen) {
            var timeReached  = false;
            var scrollReached = false;

            var titleEl    = document.getElementById('scw-popup-title');
            var subtitleEl = document.getElementById('scw-popup-sub');

            function tryAutoOpen() {
                if (autoOpened) return;
                if (!timeReached || !scrollReached) return;
                if (popup.classList.contains('scw-visible')) return;
                autoOpened = true; // помечаем для submit

                // подменяем текст на консультационный
                titleEl.textContent    = WIDGET_CONFIG.autoOpenTitle;
                subtitleEl.textContent = WIDGET_CONFIG.autoOpenSubtitle;

                openPopup();
            }

            // Таймер времени на сайте
            setTimeout(function () {
                timeReached = true;
                tryAutoOpen();
            }, WIDGET_CONFIG.autoOpenTime * 1000);

            // Отслеживание прокрутки
            function checkScroll() {
                var scrolled = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight;
                if (scrolled >= WIDGET_CONFIG.autoOpenScroll) {
                    scrollReached = true;
                    window.removeEventListener('scroll', checkScroll);
                    tryAutoOpen();
                }
            }
            window.addEventListener('scroll', checkScroll, { passive: true });
            checkScroll(); // проверить сразу (вдруг страница короткая)
        }
    }

    // ─── Запуск ───────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildWidget);
    } else {
        buildWidget();
    }

    // Публичный API для изменения конфига
    window.CallbackWidget = {
        configure: function (opts) {
            Object.assign(WIDGET_CONFIG, opts);
        }
    };

}());
