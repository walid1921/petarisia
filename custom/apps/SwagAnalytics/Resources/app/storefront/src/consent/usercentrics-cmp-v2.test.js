import { jest } from '@jest/globals';
import ShopwareAnalytics from '../../lib/shopware-analytics.js'

const startShopwareAnalytics = jest.fn();
jest.unstable_mockModule('../start-tracking.js', () => ({
    default: startShopwareAnalytics,
}));

const { default: handleUsercentricsConsent } = await import('./usercentrics-cmp-v2.js');

describe('src/consent/usercentrics-cmp-v2.js', () => {
    beforeEach(() => {
        window.useDefaultCookieConsent = false;
    });

    test('does not add event listener if default cookie consent is enabled', () => {
        window.useDefaultCookieConsent = true;

        const eventTarget = new EventTarget();
        const addEventListenerSpy = jest.spyOn(eventTarget, 'addEventListener');

        handleUsercentricsConsent(window, new ShopwareAnalytics);

        expect(addEventListenerSpy).not.toHaveBeenCalled();
    });

    test('adds event listener if default cookie consent is disabled', () => {
        const eventTarget = new EventTarget();
        const addEventListenerSpy = jest.spyOn(eventTarget, 'addEventListener');

        handleUsercentricsConsent(eventTarget, new ShopwareAnalytics());

        expect(addEventListenerSpy).toHaveBeenCalledWith('Shopware Analytics', expect.any(Function));
    });

    test('starts Shopware Analytics if consent is given', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleUsercentricsConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('Shopware Analytics', {
            detail: {
                event: 'consent_status',
                'Shopware Analytics': true,
            },
        }));

        expect(startShopwareAnalytics).toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('does not Shopware Analytics if consent event misses details', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleUsercentricsConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('Shopware Analytics', {
            detail: {},
        }));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('resets Shopware Analytics if consent is revoked', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        handleUsercentricsConsent(eventTarget, analytics);

        eventTarget.dispatchEvent(new CustomEvent('Shopware Analytics', {
            detail: {
                event: 'consent_status',
                'Shopware Analytics': false,
            },
        }));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });
});
