import startShopwareAnalytics from '../start-tracking.js';

/**
 * @private
 * @param {Window} w
 * @param {ShopwareAnalytics} analytics
 */
export default function handleUsercentricsConsent(w, analytics) {
    if (window.useDefaultCookieConsent === true) {
        return;
    }

    // Requires "Shopware Analytics" to be configured under Implementation -> Data Layer & Events -> Window Event
    w.addEventListener('Shopware Analytics', (e) => {
        if (e.detail && e.detail.event === 'consent_status') {
            if (e.detail['Shopware Analytics'] === true) {
                startShopwareAnalytics(analytics);
            } else {
                analytics.clearStorage();
            }
        }
    });
}
