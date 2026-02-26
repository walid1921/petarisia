import { jest } from '@jest/globals';
import ShopwareAnalytics from '../../lib/shopware-analytics.js'

const startShopwareAnalytics = jest.fn();
jest.unstable_mockModule('../start-tracking.js', () => ({
    default: startShopwareAnalytics,
}));

const { default: handleCookiebotConsent } = await import('./cookiebot.js');

globalThis.Cookiebot = {
    consent: {
        statistics: false,
    },
};

describe('src/consent/cookiebot.js', () => {
    beforeEach(() => {
        window.useDefaultCookieConsent = false;

        globalThis.Cookiebot.consent.statistics = false;
    });

    test('does not add event listener if default cookie consent is enabled', () => {
        window.useDefaultCookieConsent = true;

        const eventTarget = new EventTarget();
        const addEventListenerSpy = jest.spyOn(eventTarget, 'addEventListener');

        handleCookiebotConsent(window, new ShopwareAnalytics);

        expect(addEventListenerSpy).not.toHaveBeenCalled();
    });

    test('adds event listener if default cookie consent is disabled', () => {
        const eventTarget = new EventTarget();
        const addEventListenerSpy = jest.spyOn(eventTarget, 'addEventListener');

        handleCookiebotConsent(eventTarget, new ShopwareAnalytics());

        expect(addEventListenerSpy).toHaveBeenCalledWith('CookiebotOnAccept', expect.any(Function));
        expect(addEventListenerSpy).toHaveBeenCalledWith('CookiebotOnDecline', expect.any(Function));
    });

    test('starts Shopware Analytics if statistics consent is given', async () => {
        globalThis.Cookiebot.consent.statistics = true;

        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleCookiebotConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('CookiebotOnAccept'));

        expect(startShopwareAnalytics).toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('resets Shopware Analytics if statistics consent is not given', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleCookiebotConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('CookiebotOnAccept'));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });

    test('resets Shopware Analytics if statistics consent is revoked', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleCookiebotConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('CookiebotOnDecline'));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });
});
