import startShopwareAnalytics from '../start-tracking.js';

/**
 * @private
 * @param {NativeEventEmitter} emitter
 * @param {ShopwareAnalytics} analytics
 */
export default function handleConsentChangedEvent(emitter, analytics) {
    if (window.useDefaultCookieConsent) {
        return;
    }

    emitter.subscribe('sw-analytics-consent', (e) => {
        if (e.detail?.consent === true) {
            startShopwareAnalytics(analytics);
        } else {
            analytics.clearStorage();
        }
    });
}
