import { mapPageCategoryAndName } from './page-mapping.js';
import { notify as notifyOfPageLoad } from './event/track/page-loaded-notifier.js';

/**
 * @private
 * @param {ShopwareAnalytics} analytics
 * @returns {Promise<void>}
 */
export default async function startShopwareAnalytics(analytics) {
    const customer = await _fetchCustomer();
    if (customer !== null && customer.id !== analytics.user.id) {
        analytics.identify(customer.id, {
            firstName: customer.firstName,
            lastName: customer.lastName,
            email: customer.email,
            customerGroupId: customer.customerGroupId,
            customerGroupName: customer.customerGroupName,
            guest: customer.guest,
        });
    }

    if (customer === null && analytics.user.id !== null) {
        analytics.reset();

        return;
    }

    const { category, name } = mapPageCategoryAndName(
        window.shopwareAnalytics.storefrontController,
        window.shopwareAnalytics.storefrontAction,
        window.shopwareAnalytics.storefrontCmsPageType,
    );

    analytics.page(category, name, {
        storefrontController: _lowercaseFirstLetter(window.shopwareAnalytics.storefrontController),
        storefrontAction: window.shopwareAnalytics.storefrontAction,
        storefrontRoute: window.shopwareAnalytics.storefrontRoute,
        storefrontCmsPageType: window.shopwareAnalytics.storefrontCmsPageType ?? null,
    });

    // TODO: notify only in case it is not a page refresh
    notifyOfPageLoad(name, {});
}

/**
 * @private
 */
async function _fetchCustomer() {
    const response = await fetch(window.router['frontend.shopware_analytics.customer.data'])
    const json = await response.json()

    return json?.customer ?? null;
}

/**
 * @private
 * @param {string} string
 * @returns {string}
 */
function _lowercaseFirstLetter(string) {
    return string.charAt(0).toLowerCase() + string.slice(1);
}
