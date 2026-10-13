/** Длительность генерации для админки: «42 с», «3 мин 05 с» */
export function duration(seconds: number | null | undefined): string {
    if (!seconds) return '';

    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return m ? `${m} мин ${String(s).padStart(2, '0')} с` : `${s} с`;
}
