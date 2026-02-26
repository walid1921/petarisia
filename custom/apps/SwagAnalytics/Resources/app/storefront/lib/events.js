import IdentifyEvent, { IDENTIFY_EVENT } from './event/identify-event.js';
import PageEvent, { PAGE_EVENT } from './event/page-event.js';
import ResetEvent, { RESET_EVENT } from './event/reset-event.js';
import TrackEvent, { TRACK_EVENT } from './event/track-event.js';

/**
 * @private
 * @name AnalyticsEvents
 * @enum {string}
 */
const EVENTS = {
    identify: IDENTIFY_EVENT,
    page: PAGE_EVENT,
    reset: RESET_EVENT,
    track: TRACK_EVENT,
}

export {
    IdentifyEvent,
    PageEvent,
    ResetEvent,
    TrackEvent,
    EVENTS,
};
