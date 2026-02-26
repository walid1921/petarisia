/**
 * @private
 */
export default class extends EventTarget {
    _subscriber = [];

    addEventListener(eventType, callback) {
        const events = this._subscriber[eventType] || [];
        events.push(callback);
        this._subscriber[eventType] = events;
    }

    removeEventListener(eventType, callback) {
        this._subscriber[eventType] = this._subscriber[eventType]?.filter(cb => cb !== callback);
    }

    dispatchEvent(event) {
        this._subscriber[event.type]?.forEach(callback => setTimeout(() => callback(event), 0));
    }
}
