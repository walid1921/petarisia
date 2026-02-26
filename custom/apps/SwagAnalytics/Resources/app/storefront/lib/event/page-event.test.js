import PageEvent, { PAGE_EVENT } from './page-event.js';
import ShopwareAnalytics from '../shopware-analytics.js';

describe('page-event.js', () => {
    test('constructor', () => {
        const shopwareAnalytics = new ShopwareAnalytics({
            debug: false,
            app: 'test-app',
        });

        const pageEvent = new PageEvent('test-category', 'test-name', { test: 'test' }, shopwareAnalytics);

        expect(pageEvent.type).toBe(PAGE_EVENT);
        expect(pageEvent.payload).toEqual(expect.objectContaining({
            category: 'test-category',
            name: 'test-name',
            properties: {
                test: 'test',
            },
        }));
    });
})
