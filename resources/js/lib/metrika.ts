/*
   Тонкая обёртка над Яндекс.Метрикой.

   Счётчик подключается в resources/views/partials/metrika.blade.php
   и кладёт свой номер в window.__metrikaId. Локально и в тестах
   счётчика нет — тогда все вызовы отсюда просто ничего не делают,
   и из-за аналитики ничего не падает.
*/

declare global {
    interface Window {
        __metrikaId?: number;
        ym?: (id: number, action: string, ...rest: unknown[]) => void;
    }
}

function counter(): number | null {
    const id = window.__metrikaId;

    return id && typeof window.ym === 'function' ? id : null;
}

/** Просмотр страницы: переходы Inertia счётчик сам не видит */
export function hit(url: string = window.location.href): void {
    const id = counter();

    if (id) {
        window.ym?.(id, 'hit', url, { referer: document.referrer });
    }
}

/**
 * Достижение цели.
 *
 * Имена целей — это те самые идентификаторы, которые потом выбираются
 * в Метрике («Цели» → JavaScript-событие) и дальше в Яндекс.Директе:
 *
 *   registration       — аккаунт создан (форма, VK, Mail.ru, Google);
 *                        параметр method — каким способом
 *   presentation_ready — презентация сгенерирована и показана
 *   payment_success    — пакет генераций оплачен
 */
export function goal(name: string, params?: Record<string, unknown>): void {
    const id = counter();

    if (id) {
        window.ym?.(id, 'reachGoal', name, params);
    }
}

/** Цель от сервера: App\Support\MetrikaGoal */
export type ServerGoal = {
    id: string;
    name: string;
    params: Record<string, unknown>;
};

const SENT_KEY = 'metrika_sent_goals';

/**
 * Цели, о которых знает только сервер (регистрация). Приходят в props
 * страницы один раз, но страницу могут обновить или вернуться на неё
 * кнопкой «назад» — поэтому id отправленных помним в sessionStorage.
 */
export function serverGoals(goals: ServerGoal[] | undefined): void {
    if (!goals?.length) return;

    let sent: string[] = [];

    try {
        sent = JSON.parse(sessionStorage.getItem(SENT_KEY) ?? '[]');
    } catch {
        sent = [];
    }

    for (const g of goals) {
        if (sent.includes(g.id)) continue;

        goal(g.name, g.params);
        sent.push(g.id);
    }

    try {
        sessionStorage.setItem(SENT_KEY, JSON.stringify(sent.slice(-20)));
    } catch {
        // Хранилище недоступно (приватный режим) — дубль возможен лишь при обновлении
    }
}
