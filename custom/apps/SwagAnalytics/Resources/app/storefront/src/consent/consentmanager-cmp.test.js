import { jest } from '@jest/globals';
import ShopwareAnalytics from '../../lib/shopware-analytics.js'

const startShopwareAnalytics = jest.fn();
jest.unstable_mockModule('../start-tracking.js', () => ({
    default: startShopwareAnalytics,
}));

const { default: handleConsentmanagerConsent } = await import('./consentmanager-cmp.js');

describe('src/consent/consentmanager-cmp.js', () => {
    beforeEach(() => {
        window.useDefaultCookieConsent = false;

        window.__cmp = undefined;
    });

    test('does not add event listener if default cookie consent is enabled', () => {
        window.useDefaultCookieConsent = true;
        window.__cmp = jest.fn();

        handleConsentmanagerConsent(window, new ShopwareAnalytics);

        expect(window.__cmp).not.toHaveBeenCalled();
        expect(startShopwareAnalytics).not.toHaveBeenCalled();
    });

    test('does not add event listener if cmp is not a function', () => {
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        handleConsentmanagerConsent({}, new ShopwareAnalytics());

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('adds event listener if default cookie consent is disabled', () => {
        window.__cmp = jest.fn();
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        handleConsentmanagerConsent(window, new ShopwareAnalytics());

        expect(window.__cmp).toHaveBeenCalledWith('addEventListener', ['consent', expect.any(Function), false]);
        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });

    test('resets Shopware Analytics if vendor is missing', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        window.__cmp = jest.fn().mockImplementation((command, parameters) => {
            if (command === 'addEventListener') {
                eventTarget.addEventListener(...parameters)
            }

            if (command === 'getCMPData') {
                return {};
            }
        })

        handleConsentmanagerConsent(window, analytics);

        eventTarget.dispatchEvent(new CustomEvent('consent'));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });

    test('resets Shopware Analytics if vendor consent is missing', async () => {
        const eventTarget = new EventTarget();
        const analytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(analytics, 'clearStorage');

        window.__cmp = jest.fn().mockImplementation((command, parameters) => {
            if (command === 'addEventListener') {
                eventTarget.addEventListener(...parameters)
            }

            if (command === 'getCMPData') {
                return {
                    vendorsList: [{
                        name: 'Shopware Analytics',
                        id: 'id123',
                    }],
                    vendorConsents: {},
                };
            }
        })

        handleConsentmanagerConsent(window, analytics);

        eventTarget.dispatchEvent(new CustomEvent('consent'));

        expect(startShopwareAnalytics).not.toHaveBeenCalled();
        expect(clearStorageSpy).toHaveBeenCalled();
    });

    test('starts Shopware Analytics if vendor consent is given', async () => {
        const eventTarget = new EventTarget();
        const shopwareAnalytics = new ShopwareAnalytics();
        const clearStorageSpy = jest.spyOn(shopwareAnalytics, 'clearStorage');

        window.__cmp = jest.fn().mockImplementation((command, parameters) => {
            if (command === 'addEventListener') {
                eventTarget.addEventListener(...parameters)
            }

            if (command === 'getCMPData') {
                return {
                    vendorsList: [{
                        name: 'Shopware Analytics',
                        id: 'id123',
                    }],
                    vendorConsents: {
                        id123: true,
                    },
                };
            }
        })

        handleConsentmanagerConsent(window, shopwareAnalytics);

        eventTarget.dispatchEvent(new CustomEvent('consent'));

        expect(startShopwareAnalytics).toHaveBeenCalled();
        expect(clearStorageSpy).not.toHaveBeenCalled();
    });
});
