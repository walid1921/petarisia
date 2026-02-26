import {
    LIBRARY_NAME,
    LIBRARY_VERSION,
    ANONYMOUS_ID,
    USER_ID,
    USER_TRAITS,
} from './constants.js';
import { IdentifyEvent, PageEvent, ResetEvent, TrackEvent, EVENTS } from './events.js';
import EventTarget from './event-emitter.js';

/**
 * @interface AnalyticsPlugin
 */

/**
 * @function AnalyticsPlugin#install
 * @param {ShopwareAnalytics} instance
 */

/**
 * @callback AnalyticsCallback
 * @param {AnalyticsEvent} event
 */

/**
 * @typedef {{debug: boolean, app: string}} AnalyticsConfig
 */

/**
 * @private
 * @module shopware-analytics
 */
export default class ShopwareAnalytics {
    /**
     * @type {Storage}
     * @private
     */
    _storage = null;

    /**
     * @param {AnalyticsConfig} [config={debug: false, app: 'storefront'}]
     */
    constructor(config = { debug: false, app: 'storefront' }) {
        this._config = config;
        this._emitter = new EventTarget();
        this._storage = globalThis.localStorage;
    }

    /**
     * @param {string} id
     * @param {object} traits
     * @returns {Promise<void>}
     */
    async identify(id, traits) {
        this._updateUser(id, traits);

        this._emitter.dispatchEvent(new IdentifyEvent(id, traits, this));
    }

    /**
     *
     * @param {string} category
     * @param {string} name
     * @param {object} properties
     * @returns {Promise<void>}
     */
    async page(category, name, properties) {
        this._emitter.dispatchEvent(new PageEvent(category, name, properties, this));
    }

    /**
     * @param {string} event
     * @param {object} data
     * @returns {Promise<void>}
     */
    async track(event, data) {
        this._emitter.dispatchEvent(new TrackEvent(event, data, this));
    }

    /**
     * @returns {Promise<void>}
     */
    async reset() {
        const cleanUp = () => {
            this.clearStorage();
            this._emitter.removeEventListener(EVENTS.reset, cleanUp);
        }

        this._emitter.addEventListener(EVENTS.reset, cleanUp);

        this._emitter.dispatchEvent(new ResetEvent(this));
    }

    clearStorage() {
        this._storage.removeItem(USER_ID);
        this._storage.removeItem(USER_TRAITS);
        this._storage.removeItem(ANONYMOUS_ID);
    }

    /**
     * @param {AnalyticsPlugin} plugin
     */
    use(plugin) {
        plugin.install(this);
    }

    /**
     * @param {AnalyticsEvents} event
     * @param {AnalyticsCallback} callback
     */
    subscribe(event, callback) {
        this._emitter.addEventListener(event, callback);
    }

    /**
     * @return {AnalyticsConfig}
     */
    get config() {
        return { ...this._config };
    }

    /**
     * @returns {string}
     */
    get anonymousId() {
        if (!this._storage.getItem(ANONYMOUS_ID)) {
            this._storage.setItem(ANONYMOUS_ID, JSON.stringify(globalThis.crypto.randomUUID()));
        }

        return JSON.parse(this._storage.getItem(ANONYMOUS_ID));
    }

    get user() {
        return {
            id: JSON.parse(this._storage.getItem(USER_ID)),
            traits: JSON.parse(this._storage.getItem(USER_TRAITS)),
        }
    }

    get screen() {
        return globalThis.screen ? {
            width: globalThis.screen.width,
            height: globalThis.screen.height,
            density: globalThis.devicePixelRatio,
        } : {};
    }

    get context() {
        return {
            app: this._config.app,
            library: {
                name: LIBRARY_NAME,
                version: LIBRARY_VERSION,
            },
            locale: globalThis.navigator.language,
            page: this.getPage(),
            screen: this.screen,
            timestamp: Date.now(),
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            userAgent: globalThis.navigator.userAgent,
            userAgentData: globalThis.navigator.userAgentData ?? null,
        }
    }

    getPage() {
        return {
            path: globalThis.location.pathname,
            referrer: document.referrer,
            search: globalThis.location.search,
            title: document.title,
            url: globalThis.location.href,
        };
    }

    _updateUser(id, traits) {
        this._storage.setItem(USER_ID, JSON.stringify(id));
        this._storage.setItem(USER_TRAITS, JSON.stringify(traits));
    }
}
