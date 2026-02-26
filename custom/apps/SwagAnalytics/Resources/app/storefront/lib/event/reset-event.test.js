import ResetEvent, { RESET_EVENT } from './reset-event';
import ShopawareAnalytics from '../shopware-analytics';

describe('reset-event.js', () => {
    test('constructor', () => {
        const shopwareAnalytics = new ShopawareAnalytics({
            debug: false,
            app: 'test-app',
        });

        const resetEvent = new ResetEvent(shopwareAnalytics);

        expect(resetEvent.type).toEqual(RESET_EVENT);
    });
});
