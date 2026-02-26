import TrackEvent, { TRACK_EVENT } from './track-event';
import ShopwareAnalytics from '../shopware-analytics.js';

describe('track-event.js', () => {
    test('constructor', () => {
        const shopwareAnalytics = new ShopwareAnalytics({
            debug: false,
            app: 'test-app',
        });

        const pageEvent = new TrackEvent('test-event', { test: 'test' }, shopwareAnalytics);

        expect(pageEvent.type).toBe(TRACK_EVENT);
        expect(pageEvent.payload).toEqual(expect.objectContaining({
            name: 'test-event',
            properties: {
                test: 'test',
            },
        }));
    });
})
