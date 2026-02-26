import startShopwareAnalytics from '../start-tracking.js';

/**
 * @private
 * @param {Window} w
 * @param {ShopwareAnalytics} analytics
 */
export default function handleConsentmanagerConsent(w, analytics) {
    if (w.useDefaultCookieConsent === true) {
        return;
    }

    if (typeof w.__cmp !== 'function') {
        return;
    }

    w.__cmp('addEventListener', ['consent', () => {
        const cmpData = w.__cmp('getCMPData');
        const vendorId = cmpData.vendorsList?.find((vendor) => vendor.name === 'Shopware Analytics')?.id ?? null;

        if (!vendorId) {
            analytics.clearStorage();

            return;
        }

        const consent = cmpData.vendorConsents[vendorId] ?? false;

        if (consent) {
            startShopwareAnalytics(analytics);
        } else {
            analytics.clearStorage();
        }
    }, false]);
}
