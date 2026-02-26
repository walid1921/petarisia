import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper.js';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin.js';
import startShopwareAnalytics from '../start-tracking.js';

/**
 * @private
 */
export const COOKIE_ENABLED_NAME = '_swa_consent_enabled';

/**
 * @private
 * @param {NativeEventEmitter} emitter - see @storefront/src/helper/emitter.helper.js
 * @param {ShopwareAnalytics} analytics
 */
export default function handleDefaultCookieConsent(emitter, analytics) {
    if (!window.useDefaultCookieConsent) {
        return;
    }

    if (CookieStorageHelper.getItem(COOKIE_ENABLED_NAME)) {
        startShopwareAnalytics(analytics);
    } else {
        analytics.clearStorage();
    }

    emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, handleCookies.bind({ instance: analytics }));
}

/**
 * @private
 * @param {CustomEvent} cookieUpdateEvent
 */
function handleCookies(cookieUpdateEvent) {
    const updatedCookies = cookieUpdateEvent.detail;

    if (typeof updatedCookies !== 'object' || !Object.prototype.hasOwnProperty.call(updatedCookies, COOKIE_ENABLED_NAME)) {
        return;
    }

    if (updatedCookies[COOKIE_ENABLED_NAME]) {
        startShopwareAnalytics(this.instance);
    } else {
        this.instance.clearStorage();
        removeCookies();
    }
}

/**
 * @private
 */
function removeCookies() {
    const allCookies = document.cookie.split(';');
    const cookieRegex = /^_swa_.*$/;

    allCookies.forEach(cookie => {
        const cookieName = cookie.split('=')[0].trim();
        if (!cookieName.match(cookieRegex)) {
            return;
        }

        CookieStorageHelper.removeItem(cookieName);
    });
}
