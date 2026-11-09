/**
 * Time-of-day theming: the light theme by day, dark theme at night, using the
 * visitor's local clock. resources/views/app.blade.php applies the same rule
 * inline before first paint (to avoid a flash); this module keeps it current
 * while the page stays open.
 */

/** Local hour (0–23) at which night mode starts. */
export const NIGHT_STARTS_AT = 19;
/** Local hour (0–23) at which day mode starts again. */
export const DAY_STARTS_AT = 6;

const THEME_COLORS = { light: '#f5f7fb', dark: '#07080c' } as const;

export function isNight(date: Date = new Date()): boolean {
    const hour = date.getHours();
    return hour >= NIGHT_STARTS_AT || hour < DAY_STARTS_AT;
}

export function applyTimeTheme(date: Date = new Date()): void {
    const dark = isNight(date);
    document.documentElement.classList.toggle('dark', dark);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? THEME_COLORS.dark : THEME_COLORS.light);
}

/** Apply now, then re-check every minute and whenever the tab becomes visible again. */
export function startTimeTheme(): void {
    applyTimeTheme();
    window.setInterval(() => applyTimeTheme(), 60_000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            applyTimeTheme();
        }
    });
}
