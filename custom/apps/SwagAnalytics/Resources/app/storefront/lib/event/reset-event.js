import AnalyticsEvent from './analytics-event.js';

/**
 * @private
 */
const RESET_EVENT = 'reset';

/**
 * @private
 */
export default class ResetEvent extends AnalyticsEvent {
    /**
     * @param {ShopwareAnalytics} analyticsInstance
     */
    constructor(analyticsInstance) {
        super(
            RESET_EVENT,
            {},
            analyticsInstance,
            analyticsInstance.config,
            analyticsInstance.context,
        )
    }
}

export { RESET_EVENT };
