import AnalyticsEvent from './analytics-event.js';

/**
 * @private
 */
const TRACK_EVENT = 'track';

/**
 * @private
 */
export default class TrackEvent extends AnalyticsEvent {
    /**
     * @param {string} name
     * @param {object} properties
     * @param {ShopwareAnalytics} analyticsInstance
     */
    constructor(name, properties, analyticsInstance) {
        super(TRACK_EVENT,
            {
                name,
                properties,
            },
            analyticsInstance,
            analyticsInstance.config,
            analyticsInstance.context,
        )
    }
}

export { TRACK_EVENT };
