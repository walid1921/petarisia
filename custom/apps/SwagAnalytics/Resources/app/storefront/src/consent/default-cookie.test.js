import { jest } from '@jest/globals';
import ShopwareAnalytics from '../../lib/shopware-analytics.js'

const startShopwareAnalytics = jest.fn();
jest.unstable_mockModule('../start-tracking.js', () => ({
    default: startShopwareAnalytics,
}));

const { default: handleDefaultCookieConsent } = await import('./default-cookie.js');

describe('src/consent/default-cookie.js', () => {
    beforeEach(() => {
        window.shopwareAnalytics ??= {};
        window.useDefaultCookieConsent = true;

        document.cookie = '_swa_consent_enabled= ;expires=Thu, 01 Jan 1970 00:00:00 UTC';
        document.cookie = '_swa_something=1';
        document.cookie = 'wrong_prefix_swa_consent_enabled=1';
        document.cookie = 'completely_different=1';
    });

    test('it registers a cookie change event', () => {
        const emitter = {
            subscribe: jest.fn(),
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        expect(emitter.subscribe).toHaveBeenCalledWith('CookieConfiguration_Update', expect.any(Function));
    });

    test('it does not register a cookie change event if default cookie consent is disabled', () => {
        window.useDefaultCookieConsent = false;

        const emitter = {
            subscribe: jest.fn(),
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        expect(emitter.subscribe).not.toHaveBeenCalledWith('CookieConfiguration_Update', expect.any(Function));
    });

    test('starts Shopware Analytics if consent cookie is set', () => {
        document.cookie = '_swa_consent_enabled=1; expires=Fri, 31 Dec 9999 23:59:59 GMT';

        const emitter = {
            subscribe(eventName, listener) {
                this.listener = listener;
            },
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        expect(startShopwareAnalytics).toHaveBeenCalled();
    });

    test('resets Shopware Analytics if consent cookie is missing', () => {
        const emitter = {
            subscribe(eventName, listener) {
                this.listener = listener;
            },
        };

        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleDefaultCookieConsent(emitter, analytics);

        expect(clearStorageSpy).toHaveBeenCalled();
    });

    test('does nothing if cookie is not in details', () => {
        const emitter = {
            subscribe(eventName, listener) {
                this.listener = listener;
            },
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        emitter.listener(new CustomEvent('CookieConfiguration_Update', { detail: {} }));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
    });

    test('starts Shopware Analytics if cookie is enabled', () => {
        const emitter = {
            subscribe(eventName, listener) {
                this.listener = listener;
            },
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        emitter.listener(new CustomEvent(
            'CookieConfiguration_Update', {
                detail: {
                    '_swa_consent_enabled': 1,
                },
            },
        ));

        expect(startShopwareAnalytics).toHaveBeenCalled();
        expect(startShopwareAnalytics).toHaveBeenCalledWith(analytics);
    });

    test('removes correct cookies if cookie is disabled', () => {
        const emitter = {
            subscribe(eventName, listener) {
                this.listener = listener;
            },
        };

        const analytics = new ShopwareAnalytics();

        handleDefaultCookieConsent(emitter, analytics);

        emitter.listener(new CustomEvent(
            'CookieConfiguration_Update', {
                detail: {
                    '_swa_consent_enabled': 0,
                },
            },
        ));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();

        expect(document.cookie).not.toContain('_swa_something');
        expect(document.cookie).toContain('wrong_prefix_swa_consent_enabled');
        expect(document.cookie).toContain('completely_different');
    });
});
