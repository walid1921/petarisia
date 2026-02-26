import { jest } from '@jest/globals';
import EventEmitter from '../lib/event-emitter.js';

const emitter = new EventEmitter();

describe('lib/event-emitter.js', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    it('registers a listener', () => {
        const listener = jest.fn();
        emitter.addEventListener('test', listener);

        expect(emitter._subscriber.test).toContain(listener);
    });

    it('removes a listener', () => {
        const listener = jest.fn();
        emitter.addEventListener('test', listener);
        emitter.removeEventListener('test', listener);

        expect(emitter._subscriber.test).not.toContain(listener);
    });

    it('dispatches an event', () => {
        const listener = jest.fn();
        emitter.addEventListener('test', listener);
        const event = new Event('test');

        jest.useFakeTimers();
        emitter.dispatchEvent(event);
        jest.runAllTimers();

        expect(listener).toHaveBeenCalledWith(event);
    });
});
