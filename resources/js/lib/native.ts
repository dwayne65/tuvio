import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';

/**
 * Glue for the Android and iOS apps (Capacitor shells around this site). Everything
 * here is a no-op in a normal browser.
 */
export const isNativeApp = Capacitor.isNativePlatform();

/** Status bar backgrounds on Android, matching the light and dark page canvas. */
const STATUS_BAR_COLORS = { light: '#f5f7fb', dark: '#07080c' } as const;

export function startNativeApp(): void {
    if (!isNativeApp) {
        return;
    }

    document.documentElement.classList.add('is-native-app', `is-${Capacitor.getPlatform()}`);

    // Follow the day/night theme, which toggles the "dark" class on <html>.
    syncStatusBar();
    new MutationObserver(syncStatusBar).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    // Android hardware back: go back a page, or leave the app from the first screen.
    App.addListener('backButton', ({ canGoBack }) => (canGoBack ? window.history.back() : App.exitApp()));

    SplashScreen.hide({ fadeOutDuration: 250 });
}

function syncStatusBar(): void {
    const dark = document.documentElement.classList.contains('dark');

    // Style.Dark means light text, for dark backgrounds.
    StatusBar.setStyle({ style: dark ? Style.Dark : Style.Light }).catch(() => {});

    if (Capacitor.getPlatform() === 'android') {
        StatusBar.setBackgroundColor({ color: dark ? STATUS_BAR_COLORS.dark : STATUS_BAR_COLORS.light }).catch(() => {});
    }
}
