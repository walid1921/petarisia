import IdentifyEvent, { IDENTIFY_EVENT } from './identify-event.js';
import ShopwareAnalytics from '../shopware-analytics.js';

describe('identify-event.js', () => {
    test('constructor', () => {
        const shopwareAnalytics = new ShopwareAnalytics({
            debug: false,
            app: 'test-app',
        })

        const identifyEvent = new IdentifyEvent(
            'customer-id', {
                trait: 'value',
            }, shopwareAnalytics,
        );

        expect(identifyEvent.type).toBe(IDENTIFY_EVENT);
        expect(identifyEvent.payload).toEqual(expect.objectContaining({
            id: 'customer-id',
            traits: {
                trait: 'value',
            },
        }));
    })
});
