import { jest } from '@jest/globals';
import ShopwareAnalytics from '../../lib/shopware-analytics.js'

const startShopwareAnalytics = jest.fn();
jest.unstable_mockModule('../start-tracking.js', () => ({
    default: startShopwareAnalytics,
}));

const { default: handleConsentChangedEvent } = await import('./subscriber.js');

describe('src/consent/subscriber.js', () => {
    beforeEach(() => {
        window.useDefaultCookieConsent = false;

        window.shopwareAnalytics = {
            merchantConsent: true,
        };
    });

    test('does not subscribe to sw-analytics-consent event if default cookie consent is enabled', () => {
        window.useDefaultCookieConsent = true;

        const emitter = {
            subscribe: jest.fn(),
        };
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        handleConsentChangedEvent(window, shopwareAnalytics);

        expect(emitter.subscribe).not.toHaveBeenCalled();
        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('subscribes to sw-analytics-event if default cookie consent is not used', () => {
        const emitter = {
            subscribe: jest.fn(),
        };
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        handleConsentChangedEvent(emitter, shopwareAnalytics);

        expect(emitter.subscribe).toHaveBeenCalledWith('sw-analytics-consent', expect.any(Function));
        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('starts Shopware Analytics when consent is given', async () => {
        const eventTarget = new EventTarget();
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        const emitter =  {
            subscribe: (eventName, callback) => {
                eventTarget.addEventListener(eventName, callback)
            },
            publish: (eventName, detail) => {
                eventTarget.dispatchEvent(new CustomEvent(eventName, { detail }))
            },
        }

        handleConsentChangedEvent(emitter, shopwareAnalytics);

        emitter.publish('sw-analytics-consent', {
            consent: true,
        });

        expect(startShopwareAnalytics).toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('resets Shopware Analytics when consent is revoked', async () => {
        const eventTarget = new EventTarget();

        const emitter =  {
            subscribe: (eventName, callback) => {
                eventTarget.addEventListener(eventName, callback)
            },
            publish: (eventName, detail) => {
                eventTarget.dispatchEvent(new CustomEvent(eventName, { detail }))
            },
        }

        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleConsentChangedEvent(emitter, analytics);

        emitter.publish('sw-analytics-consent', {
            consent: false,
        });

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });
});
