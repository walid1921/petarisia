/**
 * @private
 */
const PAGE_TO_EVENT_MODULE_MAP = {
    'Checkout finish': ['order-created'],
};

/**
 * @private
 * @param {string} pageName
 * @param {Object} context
 */
export async function notify(pageName, context) {
    const modules = PAGE_TO_EVENT_MODULE_MAP[pageName] || [];
    const { load } = await import('./event-module-loader.js');

    // only load the modules that we need to use
    modules.forEach(moduleName => load(moduleName).then(module => module.pageLoaded?.(context)));
}
