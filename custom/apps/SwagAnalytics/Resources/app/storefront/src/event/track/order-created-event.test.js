import { jest } from '@jest/globals';
import * as orderPurchasedEvent from './order-created-event.js';

describe('src/event/track/order-created-event.js', () => {

    it('the event has a pageLoaded function', async () => {
        expect(orderPurchasedEvent.pageLoaded).toBeDefined();
    });

    it('pageLoaded function calls track()', async () => {
        window._shopwareAnalytics = { track: jest.fn() };

        orderPurchasedEvent.pageLoaded({})
        expect(window._shopwareAnalytics.track).toHaveBeenCalled();
    });
});
