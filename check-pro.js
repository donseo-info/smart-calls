// Конфигурация
const TRAFFIC_CONFIG = {
    // Блеклист площадок (части имен для блокировки)
    blacklistedSources: [
        ".vpn",           // Все VPN приложения
        "vpn.",           // VPN в начале
        "vpn-",           // VPN с дефисом
        "app",            // Подозрительные приложения
        ".app",           // Домены .app
    ],
    
    // Настройки отслеживания
    timeThreshold: 31000, // 31 секунда в миллисекундах
    checkInterval: 1000,  // Проверка каждую секунду
    cookieExpiry: 31556926, // 1 год в секундах
    
    // Эксклюзивный режим: отправляется только ОДНА цель (та что первая сработала)
    // true  — lead и eTarget взаимоисключают друг друга
    // false — обе цели могут отправиться независимо
    exclusiveGoalMode: true,
    sharedGoalCookieName: 'trafficCheck_any_goal', // общий флаг "хоть что-то отправлено"
    
    // ID метрик (настроить под свои)
    yandexMetrikaId: 50785609,
    topMailId: 3324042,
    
    // Цели для отправки (можно добавлять любое количество)
    goals: [
        {
            id: 'etarget_rpo',
            name: 'etarget_rpo', 
            description: 'Активный пользователь',
            cookieName: 'trafficCheck_etarget_rpo'
        },
        {
            id: 'lead',
            name: 'lead', 
            description: 'Лид',
            cookieName: 'trafficCheck_lead'
        }
    ]
};

// Состояние трекера (глобально доступное)
window.trafficState = {
    isValidSource: false,
    timeSpent: false,
    scrolled: false,
    programClick: false,
    priceClick: false,
    videoClick: false,
    tabClick: false,
    faqClick: false,
    formSubmitted: false,
    anyGoalSent: false,   // true как только отправлена ЛЮБАЯ цель (для exclusive mode)
    startTime: Date.now()
};

// Локальная ссылка для удобства
const trafficState = window.trafficState;

/**
 * Проверка источника трафика на блеклист
 */
function checkTrafficSource() {
    // Получаем параметр source из URL
    const urlParams = new URLSearchParams(window.location.search);
    const sourceParam = urlParams.get('source');
    
    // Если параметра source нет - считаем трафик валидным
    if (!sourceParam) {
        trafficState.isValidSource = true;
        return true;
    }

    const sourceLower = sourceParam.toLowerCase();
    const isBlacklisted = TRAFFIC_CONFIG.blacklistedSources.some(blocked =>
        sourceLower.includes(blocked.toLowerCase())
    );

    trafficState.isValidSource = !isBlacklisted;
    return !isBlacklisted;
}

/**
 * Отслеживание времени на сайте
 */
function initTimeTracking() {
    const timeInterval = setInterval(() => {
        const elapsedTime = Date.now() - trafficState.startTime;
        
        if (elapsedTime >= TRAFFIC_CONFIG.timeThreshold) {
            trafficState.timeSpent = true;
            clearInterval(timeInterval);
        }
    }, TRAFFIC_CONFIG.checkInterval);
}

/**
 * Отслеживание прокрутки до секции программы
 */
function initScrollTracking() {
    let blockVisible = false;
    
    function checkScroll() {
        const programSection = document.querySelector('.program-section');
        if (!programSection) {
            console.warn('Элемент .program-section не найден на странице');
            return;
        }
        
        const rect = programSection.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        
        // Элемент считается видимым, если:
        // 1. Верхняя часть элемента находится в зоне видимости
        // 2. Или нижняя часть элемента находится в зоне видимости
        // 3. Или элемент полностью покрывает экран
        const isVisible = (
            (rect.top >= 0 && rect.top <= windowHeight) ||
            (rect.bottom >= 0 && rect.bottom <= windowHeight) ||
            (rect.top <= 0 && rect.bottom >= windowHeight)
        );
        
        if (isVisible && !blockVisible) {
            trafficState.scrolled = true;
            blockVisible = true;
        }
    }
    
    // Проверяем при загрузке и при скролле
    window.addEventListener('scroll', checkScroll);
    window.addEventListener('resize', checkScroll); // На случай изменения размера окна
    
    // Проверяем сразу при инициализации
    setTimeout(checkScroll, 100); // Небольшая задержка для загрузки DOM
    checkScroll();
}

/**
 * Отслеживание кликов по элементам
 */
function initClickTracking() {
    // Программа курса (новый селектор)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.program-download')) trafficState.programClick = true;
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest('.pricing-toggle'))   trafficState.priceClick = true;
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest('.play-button'))      trafficState.videoClick = true;
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest('.tab-btn'))          trafficState.tabClick = true;
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest('.faq-question'))     trafficState.faqClick = true;
    });
}

/**
 * Отслеживание отправки форм
 */
function initFormTracking() {
    document.addEventListener('submit', function(e) {
        trafficState.formSubmitted = true;
    });
}

/**
 * Проверка качества трафика
 */
function isQualityTraffic() {
    const { timeSpent, scrolled, programClick, priceClick, videoClick, tabClick, faqClick, formSubmitted } = trafficState;
    
    // Форма отправлена - всегда качественный трафик
    if (formSubmitted) return true;
    
    // Базовая активность: время + прокрутка
    const interactions = [programClick, priceClick, videoClick, tabClick, faqClick].filter(Boolean).length;
    if (timeSpent && scrolled && interactions >= 1) return true;
    
    // Время + любые 2 взаимодействия
    if (timeSpent && interactions >= 2) return true;
    
    // Прокрутка + любые 2 взаимодействия
    if (scrolled && interactions >= 2) return true;
    
    // Видео + программа
    if (videoClick && programClick) return true;
    
    // Табы + любое другое взаимодействие
    if (tabClick && (programClick || priceClick || videoClick || faqClick)) return true;
    
    // FAQ + любое другое взаимодействие
    if (faqClick && (programClick || priceClick || videoClick || tabClick)) return true;
    
    return false;
}

/**
 * Проверка cookie на дубли
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, maxAge) {
    document.cookie = `${name}=${value}; path=/; max-age=${maxAge}`;
}

/**
 * Exclusive mode: проверка и установка общего флага "хоть одна цель уже отправлена"
 */
function isAnyGoalSent() {
    return getCookie(TRAFFIC_CONFIG.sharedGoalCookieName) === 'sent';
}

function markAnyGoalSent() {
    setCookie(TRAFFIC_CONFIG.sharedGoalCookieName, 'sent', TRAFFIC_CONFIG.cookieExpiry);
    trafficState.anyGoalSent = true;
}

/**
 * Отправка целей в аналитику (с проверкой дублей)
 */
function sendGoalSafely(goalIds = null) {
    // Если goalIds не указаны, отправляем основную цель
    const goalsToSend = goalIds ? 
        TRAFFIC_CONFIG.goals.filter(goal => goalIds.includes(goal.id)) :
        [TRAFFIC_CONFIG.goals[0]]; // Первая цель по умолчанию
    
    const sentGoals = [];
    const skippedGoals = [];

    // Exclusive mode: если уже отправлена любая цель — блокируем все остальные
    if (TRAFFIC_CONFIG.exclusiveGoalMode && isAnyGoalSent()) {
        return { sent: [], skipped: goalsToSend.map(g => g.id), success: false };
    }
    
    goalsToSend.forEach(goal => {
        // Проверяем куки перед отправкой каждой цели
        if (getCookie(goal.cookieName) === 'sent') {
            skippedGoals.push(goal.id);
            return;
        }

        try {
            setCookie(goal.cookieName, 'sent', TRAFFIC_CONFIG.cookieExpiry);

            if (typeof ym !== 'undefined') {
                ym(TRAFFIC_CONFIG.yandexMetrikaId, 'reachGoal', goal.name);
            }

            if (typeof _tmr !== 'undefined') {
                _tmr.push({ type: 'reachGoal', id: TRAFFIC_CONFIG.topMailId, goal: goal.name });
            }

            sentGoals.push(goal.id);

            if (TRAFFIC_CONFIG.exclusiveGoalMode) {
                markAnyGoalSent();
            }

        } catch (error) {
            console.error(`[TrafficAnalyzer] goal send error ${goal.id}:`, error);
            setCookie(goal.cookieName, '', -1);
        }
    });
    
    return {
        sent: sentGoals,
        skipped: skippedGoals,
        success: sentGoals.length > 0
    };
}

/**
 * Основной чекер качества трафика
 */
function startTrafficChecker() {
    const checkerInterval = setInterval(() => {
        if (!trafficState.isValidSource && !trafficState.formSubmitted) return;

        if (isQualityTraffic()) {
            clearInterval(checkerInterval);
            sendGoalSafely(['etarget_rpo']);
        }
    }, TRAFFIC_CONFIG.checkInterval);
}

/**
 * Глобальные функции для внешнего управления
 */
window.TrafficAnalyzer = {
    // Установить отправку формы
    setFormSubmitted: function() {
        window.trafficState.formSubmitted = true;
    },
    
    // Получить текущее состояние
    getState: function() {
        return window.trafficState;
    },
    
    // Отправить конкретные цели
    sendGoals: function(goalIds) {
        if (!goalIds || !Array.isArray(goalIds)) return false;
        if (isQualityTraffic()) return sendGoalSafely(goalIds);
        return { sent: [], skipped: [], success: false };
    },

    forceSendGoals: function(goalIds) {
        if (!goalIds || !Array.isArray(goalIds)) return false;
        return sendGoalSafely(goalIds);
    },

    forceSendGoal: function() {
        const mainGoal = TRAFFIC_CONFIG.goals[0];
        if (getCookie(mainGoal.cookieName) === 'sent') return false;
        if (isQualityTraffic()) return sendGoalSafely();
        return false;
    },
    
    // Проверить качество трафика
    checkQuality: function() {
        return isQualityTraffic();
    },
    
    // Проверить, были ли цели уже отправлены
    isGoalSent: function(goalId = null) {
        if (goalId) {
            const goal = TRAFFIC_CONFIG.goals.find(g => g.id === goalId);
            return goal ? getCookie(goal.cookieName) === 'sent' : false;
        } else {
            // Проверяем основную цель
            return getCookie(TRAFFIC_CONFIG.goals[0].cookieName) === 'sent';
        }
    },
    
    // Получить список всех целей
    getAvailableGoals: function() {
        return TRAFFIC_CONFIG.goals.map(goal => ({
            id: goal.id,
            name: goal.name,
            description: goal.description,
            sent: getCookie(goal.cookieName) === 'sent'
        }));
    },
    
    // Сбросить куки конкретной цели (для тестирования)
    resetGoalCookie: function(goalId = null) {
        if (goalId) {
            const goal = TRAFFIC_CONFIG.goals.find(g => g.id === goalId);
            if (goal) {
                setCookie(goal.cookieName, '', -1);
            }
        } else {
            // Сбрасываем все цели + общий exclusive-флаг
            TRAFFIC_CONFIG.goals.forEach(goal => {
                setCookie(goal.cookieName, '', -1);
            });
            setCookie(TRAFFIC_CONFIG.sharedGoalCookieName, '', -1);
            trafficState.anyGoalSent = false;
        }
    },

    // Проверить: была ли уже отправлена любая цель (exclusive mode)
    isAnyGoalSent: function() {
        return isAnyGoalSent();
    },

    // Отметить что внешняя цель отправлена — заблокирует eTarget в exclusive mode
    markAnyGoalSent: function() {
        markAnyGoalSent();
    }
};
function initTrafficAnalyzer() {
    checkTrafficSource();
    initTimeTracking();
    initScrollTracking();
    initClickTracking();
    initFormTracking();
    startTrafficChecker();
}

// Автозапуск при загрузке DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTrafficAnalyzer);
} else {
    initTrafficAnalyzer();
}

