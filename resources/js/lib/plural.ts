/**
 * Русское склонение после числа.
 *
 * Правило одно на весь интерфейс: «1 слайд», «2 слайда», «5 слайдов».
 * Отдельная функция, а не тернарник по месту, потому что исключения
 * тут неочевидны — 11, 12, 13 и 14 ведут себя не как 1, 2, 3 и 4,
 * и по месту про них стабильно забывают.
 *
 * @param forms [для 1, для 2–4, для 5 и больше]
 */
export function plural(n: number, forms: [string, string, string]): string {
    const abs = Math.abs(n);
    const ten = abs % 10;
    const hundred = abs % 100;

    if (hundred >= 11 && hundred <= 14) return forms[2];
    if (ten === 1) return forms[0];
    if (ten >= 2 && ten <= 4) return forms[1];

    return forms[2];
}

/** «6 слайдов» — число вместе со склонённым словом */
export function slides(n: number): string {
    return `${n} ${plural(n, ['слайд', 'слайда', 'слайдов'])}`;
}
