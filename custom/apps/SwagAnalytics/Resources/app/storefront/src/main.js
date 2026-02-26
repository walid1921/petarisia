import handleDefaultCookieConsent from './consent/default-cookie.js';
import handleUsercentricsConsent from './consent/usercentrics-cmp-v2.js';

import ShopwareAnalytics from '../lib/shopware-analytics.js';
import ShopwareAnalyticsPlugin from '../lib/analytics-plugin-shopware.js';
import handleConsentmanagerConsent from './consent/consentmanager-cmp.js';
import handleConsentChangedEvent from './consent/subscriber.js';
import handleCookiebotConsent from './consent/cookiebot.js';

const analytics = window._shopwareAnalytics = new ShopwareAnalytics({
    debug: window.shopwareAnalytics?.debug ?? false,
    app: 'storefront',
});

if (window.shopwareAnalytics && window.shopwareAnalytics.trackingId !== '') {
    const shopwareAnalyticsPlugin = new ShopwareAnalyticsPlugin(window.shopwareAnalytics.trackingId);

    analytics.use(shopwareAnalyticsPlugin);

    if (window.shopwareAnalytics.merchantConsent === true) {
        // Shopware default cookie consent
        handleDefaultCookieConsent(document.$emitter, analytics);

        // Usercentrics Web CMP v2
        handleUsercentricsConsent(window, analytics);

        // Cookiebot
        handleCookiebotConsent(window, analytics);

        // consentmanager CMP
        handleConsentmanagerConsent(window, analytics)

        // Generic consent change event
        handleConsentChangedEvent(document.$emitter, analytics)
    }
} else {
    analytics.clearStorage();
}
