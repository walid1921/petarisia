import { load } from './event-module-loader.js';
import * as orderPurchasedEvent from './order-created-event.js';

describe('src/event/track/event-module-loader.js', () => {

    it('load function loads modules correctly', async () => {
        expect(await load('order-created')).toBe(orderPurchasedEvent);
    });
});
