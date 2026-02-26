import { EVENTS, TrackEvent } from './events.js';

/**
 * @typedef Customer
 * @property {string} customerGroupId
 * @property {string} customerGroupName
 * @property {bool} guest
 */

/**
 * @private
 * @implements AnalyticsPlugin
 */
export default class ShopwareAnalyticsPlugin {
    /**
     * @param {string} trackingId
     */
    constructor(trackingId) {
        this.name = 'shopware-analytics';
        this._trackingId = trackingId;
        this._trackingURL = 'https://analytics.shopware.io/v1'

        try {
            // AnalyticsGatewayBaseURL is replaced for local development
            this._trackingURL = AnalyticsGatewayBaseURL;
        } catch {
            // webpack config is not processed for older Shopware versions.
            // This is fine because we need AnalyticsGatewayBaseURL only for local development and staging
        }
    }

    /**
     * @param {PageEvent} param0
     * @returns {Promise<void>}
     */
    async page({ payload, config, context, instance }) {
        if (config.debug) {
            this._trace(EVENTS.page, payload, config);
        }

        this._send({
            type: EVENTS.page,
            timestamp: new Date(context.timestamp).toISOString(),
            anonymousId: payload.anonymousId,
            properties: this._removeEmptyValuesFromObjectRecursively({
                ...payload.properties,
                category: payload.category,
                name: payload.name,
                title: context.page.title,
                path: context.page.path,
                url: context.page.url,
                referrer: context.page.referrer,
                search: context.page.search,
            }),
            context,
            customer: await this._getIdentifiedCustomer(instance),
        });
    }

    /**
     * @param {TrackEvent} param0
     */
    async track({ payload, config, context, instance }) {
        if (config.debug) {
            this._trace(EVENTS.track, payload, config);
        }

        this._send({
            type: EVENTS.track,
            timestamp: new Date(context.timestamp).toISOString(),
            anonymousId: payload.anonymousId,
            name: payload.name,
            properties: payload.properties,
            context,
            customer: await this._getIdentifiedCustomer(instance),
        });
    }

    /**
     * @param {IdentifyEvent} param0
     */
    async identify({ payload, config, context, instance }) {
        if (config.debug) {
            this._trace(EVENTS.identify, payload, config);
        }

        this._send({
            type: EVENTS.identify,
            timestamp: new Date(context.timestamp).toISOString(),
            anonymousId: payload.anonymousId,
            context: context,
            customer: await this._getIdentifiedCustomer(instance),
        });
    }

    /**
     * @param {ResetEvent} param0
     */
    reset({ payload, config, instance }) {
        if (config.debug) {
            this._trace(EVENTS.reset, payload, config);
        }

        this.track(new TrackEvent('user:logout', {}, instance));
    }

    loaded() {
        return true;
    }

    install(instance) {
        if (!(globalThis.crypto && globalThis.crypto.randomUUID)) {
            return;
        }

        instance.subscribe(EVENTS.identify, this.identify.bind(this));
        instance.subscribe(EVENTS.page, this.page.bind(this));
        instance.subscribe(EVENTS.reset, this.reset.bind(this));
        instance.subscribe(EVENTS.track, this.track.bind(this));
    }

    /**
     * @param {ShopwareAnalytics} instance
     * @returns {Promise<{Customer>}
     * @private
     */
    async _getIdentifiedCustomer(instance) {
        const { user } = instance;

        if (user.id === null) {
            return null;
        }

        return {
            customerGroupId: instance.user.traits.customerGroupId,
            customerGroupName: instance.user.traits.customerGroupName,
            guest: instance.user.traits.guest,
        }
    }

    /**
     * @private
     */
    _removeEmptyValuesFromObjectRecursively(obj) {
        return Object.fromEntries(
            Object.entries(obj)
                // eslint-disable-next-line no-unused-vars
                .filter(([_, v]) => v != null)
                .map(([k, v]) => [k, v === Object(v) ? this._removeEmptyValuesFromObjectRecursively(v) : v]),
        );
    }

    /**
     * @private
     */
    _send(payload) {
        fetch(`${this._trackingURL}/${payload.type}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Shopware-Tracking-Id': this._trackingId,
            },
            body: JSON.stringify(payload),
        })
    }


    /**
     * @param {string} event
     * @param {AnalyticsEvent#payload} payload
     * @param {AnalyticsEvent#config} config
     * @private
     */
    _trace(event, payload, config) {
        /* eslint-disable-next-line no-console */
        console.dir({
            trackingId: this._trackingId,
            event,
            payload,
            config,
        })
    }
}
