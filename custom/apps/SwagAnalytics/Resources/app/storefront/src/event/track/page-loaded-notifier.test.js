import { jest } from '@jest/globals';
import { notify } from './page-loaded-notifier.js';

const pageLoadedMock = jest.fn();

jest.unstable_mockModule('./event-module-loader.js', () => ({
    load: async () => ({ pageLoaded: pageLoadedMock }),
}));

describe('src/event/track/page-loaded-notifier.js', () => {
    afterAll(() => {
        jest.restoreAllMocks();
    });

    test.each([
        ['Checkout finish'],
    ])('the events are mapped to a page name and the pageLoaded function of the event is called', async (pageName) => {
        notify(pageName, {});

        await new Promise(process.nextTick);
        expect(pageLoadedMock).toHaveBeenCalled();
    });
});
