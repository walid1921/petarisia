import { jest } from '@jest/globals';
import AnalyticsEvent from './analytics-event.js'
import ShopwareAnalytics from '../shopware-analytics.js';

describe('analytics-event.js', () => {
    test('constructor', () => {
        jest.useFakeTimers();

        const config  = {
            debug: false,
            app: 'test-app',
        }
        const shopwareAnalytics = new ShopwareAnalytics(config);

        const event = new AnalyticsEvent(
            'test-event',
            { test: 'test' },
            shopwareAnalytics,
            config,
            { test: 'test' },

        );

        expect(event.config).toBe(config);
        expect(event.instance).toBe(shopwareAnalytics);
        expect(event.context).toEqual({ test: 'test' });
        expect(event.payload).toEqual({
            context: shopwareAnalytics.context,
            screen: shopwareAnalytics.screen,
            user: shopwareAnalytics.user,
            test: 'test',
            anonymousId: shopwareAnalytics.anonymousId,
        });

        jest.useRealTimers();
    })
})
