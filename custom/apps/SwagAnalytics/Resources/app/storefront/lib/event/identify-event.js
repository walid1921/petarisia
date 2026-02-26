import AnalyticsEvent from './analytics-event.js';

/**
 * @private
 */
const IDENTIFY_EVENT = 'identify';

/**
 * @private
 */
export default class IdentifyEvent extends AnalyticsEvent {
    /**
     * @param {string} id
     * @param {object} traits
     * @param {ShopwareAnalytics} analyticsInstance
     */
    constructor(id, traits, analyticsInstance) {
        super(IDENTIFY_EVENT,
            {
                id,
                traits,
            },
            analyticsInstance,
            analyticsInstance.config,
            analyticsInstance.context,
        )
    }
}

export { IDENTIFY_EVENT };
