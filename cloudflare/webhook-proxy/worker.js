/**
 * Runs on the route tuvio.org/webhooks/* in front of the "coming soon" Pages site and
 * forwards provider webhooks (Paytota, Flutterwave, USSD) to the Laravel app behind the
 * app.tuvio.org tunnel. The body is passed through byte for byte and the headers are
 * kept, so signature checks in the app still see exactly what the provider signed.
 */
const ORIGIN = 'https://app.tuvio.org';

export default {
    async fetch(request) {
        const url = new URL(request.url);

        if (!url.pathname.startsWith('/webhooks/')) {
            return new Response('Not found', { status: 404 });
        }

        const upstream = new Request(ORIGIN + url.pathname + url.search, {
            method: request.method,
            headers: request.headers,
            body: ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer(),
            redirect: 'manual',
        });

        try {
            return await fetch(upstream);
        } catch {
            // Laptop or tunnel offline: a 5xx tells the provider to retry the delivery later.
            return new Response('Webhook receiver unavailable', { status: 503 });
        }
    },
};
