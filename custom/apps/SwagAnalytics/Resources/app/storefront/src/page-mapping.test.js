import { mapPageCategoryAndName } from './page-mapping.js';

describe('src/page-mapping.js', () => {
    test.each([
        ['AccountOrder', 'Account'],
        ['AccountPayment', 'Account'],
        ['AccountProfile', 'Account'],
        ['Address', 'Account'],
        ['Auth', 'Account'],
        ['Checkout', 'Checkout'],
        ['Error', 'Error'],
        ['LandingPage', 'Landing page'],
        ['Maintenance', 'Maintenance'],
        ['Navigation', 'Navigation'],
        ['Newsletter', 'Newsletter'],
        ['Product', 'Product'],
        ['Register', 'Account'],
        ['Search', 'Search'],
        ['Wishlist', 'Wish list'],
    ])('maps the controller to a page category', (controller, expectedCategory) => {''
        const { category } = mapPageCategoryAndName(controller, '', null)

        expect(category).toBe(expectedCategory)
    });

    test.each([
        ['AccountOrder', 'orderOverview', { category: 'Account', name: 'Order overview' }],
        ['AccountOrder', 'orderSingleOverview', { category: 'Account', name: 'Order details' }],
        ['AccountOrder', 'editOrder', { category: 'Account', name: 'Order edit' }],
        ['AccountPayment', 'paymentOverview', { category: 'Account', name: 'Payment overview' }],
        ['AccountProfile', 'index', { category: 'Account', name: 'Account overview' }],
        ['AccountProfile', 'profileOverview', { category: 'Account', name: 'Profile overview' }],
        ['Address', 'accountAddressOverview', { category: 'Account', name: 'Address overview' }],
        ['Address', 'accountCreateAddress', { category: 'Account', name: 'Address create' }],
        ['Auth', 'loginPage', { category: 'Account', name: 'Sign in' }],
        ['Auth', 'guestLoginPage', { category: 'Account', name: 'Guest sign in' }],
        ['Auth', 'logout', { category: 'Account', name: 'Sign out' }],
        ['Auth', 'recoverAccountForm', { category: 'Account', name: 'Account recovery' }],
        ['Checkout', 'cartPage', { category: 'Checkout', name: 'Cart' }],
        ['Checkout', 'finishPage', { category: 'Checkout', name: 'Checkout finish' }],
        ['Checkout', 'confirmPage', { category: 'Checkout', name: 'Checkout confirm' }],
        ['Error', 'error', { category: 'Error', name: 'Error page' }],
        ['LandingPage', 'index', { category: 'Landing page', name: 'Landing page' }],
        ['Maintenance', 'renderMaintenancePage', { category: 'Maintenance', name: 'Maintenance' }],
        ['Navigation', 'home', { category: 'Navigation', name: 'Home' }],
        ['Navigation', 'index', { category: 'Navigation', name: 'Product listing' }],
        ['Newsletter', 'subscribeMail', { category: 'Newsletter', name: 'Newsletter subscription' }],
        ['Product', 'index', { category: 'Product', name: 'Product detail' }],
        ['Register', 'accountRegisterPage', { category: 'Account', name: 'Sign up' }],
        ['Register', 'customerGroupRegistration', { category: 'Account', name: 'Business sign up' }],
        ['Register', 'checkoutRegisterPage', { category: 'Account', name: 'Checkout sign up' }],
        ['Register', 'confirmRegistration', { category: 'Account', name: 'Sign up confirmation' }],
        ['Search', 'search', { category: 'Search', name: 'Search' }],
        ['Wishlist', 'index', { category: 'Wish list', name: 'Wish list' }],
    ])('maps the controller and action to a page category and name', (controller, action, expectedCategoryAndName) => {
        const categoryAndName = mapPageCategoryAndName(controller, action, null)

        expect(categoryAndName).toEqual(expectedCategoryAndName);
    });

    test.each([
        ['page', 'Shop page'],
        ['landingpage', 'Landing page'],
        ['product_list', 'Product listing'],
        ['product_detail', 'Product detail'],
    ])('maps to CMS page if a CMS page type is provided', (cmsPageType, expectedName) => {
        const categoryAndName = mapPageCategoryAndName('Account', 'orderOverview', cmsPageType)

        expect(categoryAndName).toEqual({ category: 'CMS', name: expectedName });
    });

    it('leaves out page name if no matching mapping for action is found', () => {
        const categoryAndName = mapPageCategoryAndName('AccountOrder', 'nonExistingAction', null)

        expect(categoryAndName).toEqual({ category: 'Account', name: null });
    });

    it('leaves out page category and name if no matching mapping for controller and action is found', () => {
        const categoryAndName = mapPageCategoryAndName('NonExistingController', 'nonExistingAction', null)

        expect(categoryAndName).toEqual({ category: null, name: null });
    });

    it('casts controller and action to string and does not throw an error', () => {
        const categoryAndName = mapPageCategoryAndName()

        expect(categoryAndName).toEqual({ category: null, name: null });
    });
});
