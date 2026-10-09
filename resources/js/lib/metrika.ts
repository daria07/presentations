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
 *   presentation_ready — презентация сгенерирована и показана
 *   payment_success    — пакет генераций оплачен
 */
export function goal(name: string, params?: Record<string, unknown>): void {
    const id = counter();

    if (id) {
        window.ym?.(id, 'reachGoal', name, params);
    }
}
