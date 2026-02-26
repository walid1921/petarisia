import { jest } from '@jest/globals';
import ShopwareAnalytics from '../lib/shopware-analytics.js'
import { ANONYMOUS_ID } from './constants.js';
import {
    ResetEvent,
    IdentifyEvent,
    PageEvent,
    TrackEvent,
    EVENTS,
} from './events.js';

describe('lib/shopware-analytics.js', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    it('has a config', () => {
        const config = { debug: true, app: 'storefront' };
        const analytics = new ShopwareAnalytics(config);
        expect(analytics.config).toEqual(config);
    });

    it('does not fail if there is no screen', () => {
        const analytics = new ShopwareAnalytics();
        jest.spyOn(globalThis, 'screen', 'get').mockReturnValue(undefined);
        expect(analytics.screen).toEqual({});
    })

    it('creates an anonymousId if not set', () => {
        const expectedAnonymousId = 'foobar1234'
        const analytics = new ShopwareAnalytics();
        const storage = {
            getItem: jest.fn()
                .mockReturnValueOnce(null)
                .mockReturnValue(JSON.stringify(expectedAnonymousId)),
            setItem: jest.fn(),
        };
        analytics._storage = storage;

        jest.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(expectedAnonymousId);
        const anonymousId = analytics.anonymousId;

        expect(anonymousId).toEqual(expectedAnonymousId);
        expect(storage.setItem).toHaveBeenCalledWith(ANONYMOUS_ID, JSON.stringify(expectedAnonymousId));
    });

    it('installs a plugin', () => {
        const analytics = new ShopwareAnalytics();
        const plugin = {
            install: jest.fn(),
        };
        analytics.use(plugin);
        expect(plugin.install).toHaveBeenCalledWith(analytics);
    });

    it('sets user on identify and resets on reset', () => {
        jest.useFakeTimers();

        const analytics = new ShopwareAnalytics();
        const userBeforeIdentify = analytics.user;
        const emptyUser = {
            id: null,
            traits: null,
        }
        expect(userBeforeIdentify).toEqual(emptyUser);

        const user = {
            id: '1234',
            traits: { email: 'foo@bar.baz' },
        };

        analytics.identify(user.id, user.traits);
        const userAfterIdentify = analytics.user;
        expect(userAfterIdentify).toEqual(user);

        analytics.reset();
        jest.runAllTimers();

        const userAfterReset = analytics.user;
        expect(userAfterReset).toEqual(emptyUser);

        jest.useRealTimers();
    })

    describe('emitting and subscribing to events', () => {
        it('subscribes to the identify event', () => {
            const subscriber = jest.fn();
            const analytics = new ShopwareAnalytics();
            analytics.subscribe(EVENTS.identify, subscriber);

            jest.useFakeTimers();
            analytics.identify('userId', { email: 'foo@bar.baz' });
            jest.runAllTimers();
            expect(subscriber).toHaveBeenCalled();
            expect(subscriber.mock.lastCall[0]).toBeInstanceOf(IdentifyEvent);
        });

        it('subscribes to the reset event', () => {
            const subscriber = jest.fn();
            const analytics = new ShopwareAnalytics();
            analytics.subscribe(EVENTS.reset, subscriber);

            jest.useFakeTimers();
            analytics.reset();
            jest.runAllTimers();
            expect(subscriber).toHaveBeenCalled();
            expect(subscriber.mock.lastCall[0]).toBeInstanceOf(ResetEvent);
        });

        it('subscribes to the page event', () => {
            const subscriber = jest.fn();
            const analytics = new ShopwareAnalytics();
            analytics.subscribe(EVENTS.page, subscriber);

            jest.useFakeTimers();
            analytics.page('category', 'name', { foo: 'bar' });
            jest.runAllTimers();
            expect(subscriber).toHaveBeenCalled();
            expect(subscriber.mock.lastCall[0]).toBeInstanceOf(PageEvent);
        });

        it('subscribes to the track event', () => {
            const subscriber = jest.fn();
            const analytics = new ShopwareAnalytics();
            analytics.subscribe(EVENTS.track, subscriber);

            jest.useFakeTimers();
            analytics.track('event', { foo: 'bar' });
            jest.runAllTimers();
            expect(subscriber).toHaveBeenCalled();
            expect(subscriber.mock.lastCall[0]).toBeInstanceOf(TrackEvent);
        });
    });
});
