import AnalyticsEvent from './analytics-event.js';

/**
 * @private
 */
const PAGE_EVENT = 'page';

/**
 * @private
 */
export default class PageEvent extends AnalyticsEvent {
    /**
     * @param {string} category
     * @param {string} name
     * @param {object} properties
     * @param {ShopwareAnalytics} analyticsInstance
     */
    constructor(category, name, properties, analyticsInstance) {
        super(PAGE_EVENT,
            {
                category,
                name,
                properties,
            },
            analyticsInstance,
            analyticsInstance.config,
            analyticsInstance.context,
        )
    }
}

export { PAGE_EVENT };
