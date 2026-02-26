import startShopwareAnalytics from '../start-tracking.js';

/**
 * @private
 * @param {Window} w
 * @param {ShopwareAnalytics} analytics
 */
export default function handleCookiebotConsent(w, analytics) {
    if (window.useDefaultCookieConsent === true) {
        return;
    }

    w.addEventListener('CookiebotOnAccept', () => {
        /* eslint-disable no-undef */
        if (Cookiebot.consent.statistics) {
            startShopwareAnalytics(analytics);
        } else {
            analytics.clearStorage();
        }
    });

    w.addEventListener('CookiebotOnDecline', () => {
        analytics.clearStorage();
    });
}
