import { jest } from '@jest/globals';
import { PageEvent, IdentifyEvent, TrackEvent, ResetEvent, EVENTS } from './events.js';
import { ANONYMOUS_ID, USER_ID, USER_TRAITS } from './constants.js';
import ShopwareAnalyticsPlugin from './analytics-plugin-shopware.js';
import ShopwareAnalytics from './shopware-analytics.js';

globalThis.AnalyticsGatewayBaseURL = 'https://analytics.shopware.io/v1';

describe('lib/analytics-plugin-shopware.js', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        fetch.reset();
        jest.useRealTimers();
        localStorage.removeItem(USER_ID);
        localStorage.removeItem(USER_TRAITS);
        localStorage.removeItem(ANONYMOUS_ID);
    });

    describe('tracking functions', () => {
        test('page', async () => {
            document.title = 'Page title';

            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });

            const plugin = new ShopwareAnalyticsPlugin('test');

            await plugin.page(new PageEvent('test-category', 'page-name', { prop: 'test' }, shopwareAnalytics))

            expect(fetch).toHaveBeenCalled();
            expect(fetch.mock.lastCall).toBeAnalyticsCall(
                'https://analytics.shopware.io/v1/page',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Shopware-Tracking-Id': 'test',
                    },
                    body: expect.objectContaining({
                        type: 'page',
                        anonymousId: shopwareAnalytics.anonymousId,
                        properties: {
                            category: 'test-category',
                            name: 'page-name',
                            prop: 'test',
                            title: 'Page title',
                            url: 'http://localhost/',
                            path: '/',
                            referrer: '',
                            search: '',
                        },
                        context: shopwareAnalytics.context,
                    }),
                },
            );
        });

        test('track', async () => {
            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });

            const plugin = new ShopwareAnalyticsPlugin('test');

            await plugin.track(new TrackEvent('added-to-cart', { prop: 'test' }, shopwareAnalytics))

            expect(fetch).toHaveBeenCalled();
            expect(fetch.mock.lastCall).toBeAnalyticsCall(
                'https://analytics.shopware.io/v1/track',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Shopware-Tracking-Id': 'test',
                    },
                    body: expect.objectContaining({
                        type: 'track',
                        anonymousId: shopwareAnalytics.anonymousId,
                        name: 'added-to-cart',
                        properties: expect.objectContaining({
                            prop: 'test',
                        }),
                        customer: null,
                        context: shopwareAnalytics.context,
                    }),
                },
            );
        });

        test('identify', async () => {
            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });

            const plugin = new ShopwareAnalyticsPlugin('test');

            await plugin.identify(new IdentifyEvent('customer-id', {
                firstName: 'hello',
                lastName: 'world',
                email: 'hello@world.com',
                creditCardNumber: 'abcdefg',
            }, shopwareAnalytics))

            expect(fetch).toHaveBeenCalled();
            expect(fetch.mock.lastCall).toBeAnalyticsCall(
                'https://analytics.shopware.io/v1/identify',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Shopware-Tracking-Id': 'test',
                    },
                    body: expect.objectContaining({
                        type: 'identify',
                        anonymousId: shopwareAnalytics.anonymousId,
                        customer: null,
                        context: shopwareAnalytics.context,
                    }),
                },
            );
        })

        test('reset', async () => {
            const plugin = new ShopwareAnalyticsPlugin('test-id');
            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });

            plugin.reset(new ResetEvent(shopwareAnalytics));
        })
    });

    describe('user handling', () => {
        test('it updates user data if not set', async () => {
            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });
            const traits = {
                firstName: 'hello',
                lastName: 'world',
                email: 'hello@world.de',
            };

            shopwareAnalytics._storage.setItem(USER_TRAITS, JSON.stringify(traits));

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            await plugin.identify(new IdentifyEvent('customer-id', traits, shopwareAnalytics));
        });

        test('it uses existing puid', async () => {
            const shopwareAnalytics = new ShopwareAnalytics({
                debug: false,
                app: 'test-app',
            });
            const traits = {
                firstName: 'hello',
                lastName: 'world',
                email: 'hello@world.de',
            };

            shopwareAnalytics._storage.setItem(USER_ID, JSON.stringify('customer-id'));
            shopwareAnalytics._storage.setItem(USER_TRAITS, JSON.stringify(traits));

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            await plugin.identify(new IdentifyEvent('customer-id', traits, shopwareAnalytics));
        });
    });

    test('plugin is always loaded', () => {
        expect(new ShopwareAnalyticsPlugin('test-id').loaded()).toBe(true);
    });

    test('plugin subscribes to events', () => {
        const emitter = {
            subscribe: jest.fn(),
        };

        const plugin = new ShopwareAnalyticsPlugin('test-id');
        plugin.install(emitter);

        expect(emitter.subscribe).toHaveBeenCalledTimes(4);
        expect(emitter.subscribe).toHaveBeenNthCalledWith(1, EVENTS.identify, expect.anything());
        expect(emitter.subscribe).toHaveBeenNthCalledWith(2, EVENTS.page, expect.anything());
        expect(emitter.subscribe).toHaveBeenNthCalledWith(3, EVENTS.reset, expect.anything());
        expect(emitter.subscribe).toHaveBeenNthCalledWith(4, EVENTS.track, expect.anything());
    });

    test('plugin does not subscribe to any events if randomUUID function is not available', () => {
        const originalRandomUUID = globalThis.crypto.randomUUID;
        delete globalThis.crypto.randomUUID;

        const emitter = {
            subscribe: jest.fn(),
        };

        const plugin = new ShopwareAnalyticsPlugin('test-id');
        plugin.install(emitter);

        expect(emitter.subscribe).not.toHaveBeenCalled();

        globalThis.crypto.randomUUID = originalRandomUUID; // Restore original function
    });

    describe('logs data if debug is true', () => {
        test('page', () => {
            const consoleSpy = jest.spyOn(console, 'dir').mockImplementation(() => {});

            const config = {
                debug: true,
                app: 'test-app',
            };
            const shopwareAnalytics = new ShopwareAnalytics(config);

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            plugin.page(new PageEvent('test-category', 'page-name', { prop: 'test' }, shopwareAnalytics));

            expect(consoleSpy).toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith({
                trackingId: 'test-id',
                event: EVENTS.page,
                payload: expect.objectContaining({
                    category: 'test-category',
                    properties: { prop: 'test' },
                }),
                config,
            })
        })

        test('track', () => {
            const consoleSpy = jest.spyOn(console, 'dir').mockImplementation(() => {});

            const config = {
                debug: true,
                app: 'test-app',
            };
            const shopwareAnalytics = new ShopwareAnalytics(config);

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            plugin.track(new TrackEvent('cart-added', { prop: 'test' }, shopwareAnalytics));

            expect(consoleSpy).toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith({
                trackingId: 'test-id',
                event: EVENTS.track,
                payload: expect.objectContaining({
                    name: 'cart-added',
                    properties: { prop: 'test' },
                }),
                config,
            });
        });

        test('identify', () => {
            const consoleSpy = jest.spyOn(console, 'dir').mockImplementation(() => {});

            const config = {
                debug: true,
                app: 'test-app',
            };
            const shopwareAnalytics = new ShopwareAnalytics(config);

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            plugin.identify(new IdentifyEvent('customer-id', {
                firstName: 'hello',
                lastName: 'world',
                email: 'hello@world.com',
            }, shopwareAnalytics));

            expect(consoleSpy).toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith({
                trackingId: 'test-id',
                event: EVENTS.identify,
                payload: expect.objectContaining({
                    id: 'customer-id',
                    traits: {
                        firstName: 'hello',
                        lastName: 'world',
                        email: 'hello@world.com',
                    },
                    user: {
                        id: null,
                        traits: null,
                    },
                }),
                config,
            });
        });

        test('reset', () => {
            const consoleSpy = jest.spyOn(console, 'dir').mockImplementation(() => {});

            const config = {
                debug: true,
                app: 'test-app',
            };
            const shopwareAnalytics = new ShopwareAnalytics(config);

            const plugin = new ShopwareAnalyticsPlugin('test-id');

            plugin.reset(new ResetEvent(shopwareAnalytics));

            expect(consoleSpy).toHaveBeenCalled();
            expect(consoleSpy).toHaveBeenCalledWith({
                trackingId: 'test-id',
                event: EVENTS.reset,
                payload: expect.anything(),
                config,
            });
        });
    })
});
