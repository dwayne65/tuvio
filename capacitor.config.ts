import type { CapacitorConfig } from '@capacitor/cli';

/**
 * The TUVIO Android and iOS apps are native shells around the web app: they load
 * CAP_SERVER_URL, so every feature ships to the apps the moment the site deploys.
 *
 *   Android emulator, local server:  CAP_SERVER_URL=http://10.0.2.2:8000 npx cap sync android
 *   Production:                      CAP_SERVER_URL=https://tuvio.app npx cap sync
 *
 * resources/native-shell holds the screens shown before the site loads and when the
 * phone is offline.
 */
const serverUrl = process.env.CAP_SERVER_URL ?? 'http://10.0.2.2:8000';

const config: CapacitorConfig = {
    appId: 'com.tuvio.app',
    appName: 'TUVIO',
    webDir: 'resources/native-shell',
    backgroundColor: '#6418f5',
    server: {
        url: serverUrl,
        // Plain http is only for local testing; production must be https.
        cleartext: serverUrl.startsWith('http://'),
        errorPath: 'offline.html',
        // Hosted payment pages stay inside the app so the buyer returns to their tickets.
        allowNavigation: ['*.paytota.com', 'checkout.flutterwave.com', '*.flutterwave.com'],
    },
    android: {
        allowMixedContent: serverUrl.startsWith('http://'),
    },
    ios: {
        contentInset: 'never',
        scheme: 'TUVIO',
    },
    plugins: {
        SplashScreen: {
            launchAutoHide: false,
            launchShowDuration: 8000,
            backgroundColor: '#6418f5',
            showSpinner: false,
            androidScaleType: 'CENTER_INSIDE',
        },
        StatusBar: {
            // Android gives the status bar its own strip; iOS always draws the page beneath it.
            overlaysWebView: false,
            backgroundColor: '#f5f7fb',
            style: 'LIGHT',
        },
    },
};

export default config;
