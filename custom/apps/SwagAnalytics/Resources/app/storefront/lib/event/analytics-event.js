/**
 * @private
 */
export default class AnalyticsEvent extends Event {
    /**
     * @param {string} eventType
     * @param {object} payload
     * @param {ShopwareAnalytics} analyticsInstance
     * @param {AnalyticsConfig} config
     * @param {object} context
     */
    constructor(eventType, payload, analyticsInstance, config, context) {
        super(eventType);
        this.payload = {
            context: analyticsInstance.context,
            screen: analyticsInstance.screen,
            user: analyticsInstance.user,
            anonymousId: analyticsInstance.anonymousId,
            ...payload,
        };
        this.instance = analyticsInstance;
        this.config = config;
        this.context = context;
    }
}
