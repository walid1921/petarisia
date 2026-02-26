/**
 * @private
 * Maps the controller and action name to a page category.
 */
const CONTROLLER_ACTION_TO_CATEGORY_MAPPING = {
    accountOrder: 'Account',
    accountPayment: 'Account',
    accountProfile: 'Account',
    address: 'Account',
    auth: 'Account',
    checkout: 'Checkout',
    error: 'Error', // Really? :D
    landingPage: 'Landing page',
    maintenance: 'Maintenance',
    navigation: 'Navigation',
    newsletter: 'Newsletter',
    product: 'Product',
    register: 'Account',
    search: 'Search',
    wishlist: 'Wish list',
}

/**
 * @private
 * Maps the (lowercase) controller and action name to a page name.
 */
const CONTROLLER_ACTION_TO_NAME_MAPPING = {
    accountOrder: {
        orderOverview: 'Order overview',
        orderSingleOverview: 'Order details',
        editOrder: 'Order edit',
    },
    accountPayment: {
        paymentOverview: 'Payment overview',
    },
    accountProfile: {
        index: 'Account overview',
        profileOverview: 'Profile overview',
    },
    address: {
        accountAddressOverview: 'Address overview',
        accountCreateAddress: 'Address create',
    },
    auth: {
        loginPage: 'Sign in',
        guestLoginPage: 'Guest sign in',
        logout: 'Sign out',
        recoverAccountForm: 'Account recovery',
    },
    checkout: {
        cartPage: 'Cart',
        finishPage: 'Checkout finish',
        confirmPage: 'Checkout confirm',
    },
    error: {
        error: 'Error page', // Really? :D
    },
    landingPage: {
        index: 'Landing page',
    },
    maintenance: {
        renderMaintenancePage: 'Maintenance',
    },
    navigation: {
        home: 'Home',
        index: 'Product listing',
    },
    newsletter: {
        subscribeMail: 'Newsletter subscription',
    },
    product: {
        index: 'Product detail',
    },
    register: {
        accountRegisterPage: 'Sign up',
        customerGroupRegistration: 'Business sign up',
        checkoutRegisterPage: 'Checkout sign up',
        confirmRegistration: 'Sign up confirmation',
    },
    search: {
        search: 'Search',
    },
    wishlist: {
        index: 'Wish list',
    },
}

/**
 * @private
 * @param {string} controller
 * @param {string} action
 * @param {string|null} cmsPageType
 * @returns {category: string, name: string}
 */
export function mapPageCategoryAndName(controller, action, cmsPageType) {
    const controllerName = _lowercaseFirstLetter(String(controller));
    const actionName = _lowercaseFirstLetter(String(action));

    if (typeof cmsPageType === 'string') {
        return _mapCmsPageTypeToCategoryAndName(cmsPageType)
    }

    const category = _mapPageCategory(controllerName)
    const name = _mapPageName(controllerName, actionName)

    return {
        category,
        name,
    }
}

/**
 * @private
 * @param {string} controllerName
 * @returns {string|null}
 */
function _mapPageCategory(controllerName) {
    const lowercaseControllerName = _lowercaseFirstLetter(controllerName);

    return CONTROLLER_ACTION_TO_CATEGORY_MAPPING[lowercaseControllerName] ?? null;
}

/**
 * @private
 * @param {string} controllerName
 * @param {string} actionName
 * @returns {string|null}
 */
function _mapPageName(controllerName, actionName) {
    const lowercaseControllerName = _lowercaseFirstLetter(controllerName);
    const lowercaseActionName = _lowercaseFirstLetter(actionName);

    return CONTROLLER_ACTION_TO_NAME_MAPPING[lowercaseControllerName]?.[lowercaseActionName] ?? null;
}

/**
 * @private
 * @param {string} cmsPageType
 * @returns {category: string, name: string}
 */
function _mapCmsPageTypeToCategoryAndName(cmsPageType) {
    const category = 'CMS';
    let name = null;

    switch (cmsPageType) {
        case 'page':
            name = 'Shop page';
            break;
        case 'landingpage':
            name = 'Landing page';
            break;
        case 'product_list':
            name = 'Product listing';
            break;
        case 'product_detail':
            name = 'Product detail';
            break;
    }

    return {
        category,
        name,
    }
}

/**
 * @private
 * @param {string} string
 * @returns {string}
 */
function _lowercaseFirstLetter(string) {
    return string.charAt(0).toLowerCase() + string.slice(1);
}
