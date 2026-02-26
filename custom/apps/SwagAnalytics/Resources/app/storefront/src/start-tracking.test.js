import { jest } from '@jest/globals';
import ShopwareAnalytics from '../lib/index.js';
import startShopwareAnalytics from './start-tracking.js';
import { USER_ID } from '../lib/constants.js';

let customerResponse = null;

global.fetch = () => {
    return Promise.resolve({
        json: () => Promise.resolve({ customer: customerResponse }),
    })
};

const createShopwareAnalytics = () => {
    const shopwareAnalytics = new ShopwareAnalytics();

    shopwareAnalytics.identify = jest.fn();
    shopwareAnalytics.reset = jest.fn();
    shopwareAnalytics.page = jest.fn();
    shopwareAnalytics.track = jest.fn();

    return shopwareAnalytics;
}

describe('src/start-tracking.js', () => {
    beforeEach(() => {
        window.shopwareAnalytics = {
            trackingId: 'tracking-id',
            merchantConsent: true,
            storefrontController: 'AccountProfile',
            storefrontAction: 'index',
            storefrontRoute: 'frontend.account.home.page',
        }
        window.router = {
            'frontend.shopware_analytics.customer.data': 'storefront/script/swag-onsite-tracking-customer',
        }

        document.cookie = '_swa_consent_enabled=1'
    });

    afterEach(() => {
        jest.resetAllMocks();

        global.localStorage.clear();

        customerResponse = null;
    })

    it('calls the identify method if a logged in customer is fetched', async () => {
        const shopwareAnalytics = createShopwareAnalytics();

        customerResponse = {
            id: '76cedcd24d624176b772d894b7e8ca12',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@doe.com',
            customerGroupId: '6311ea0e19da43c8962b499f7a70a59b',
            customerGroupName: 'Standard customer group',
            guest: false,
        }

        await startShopwareAnalytics(shopwareAnalytics);

        expect(shopwareAnalytics.identify).toHaveBeenCalledWith('76cedcd24d624176b772d894b7e8ca12', {
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@doe.com',
            customerGroupId: '6311ea0e19da43c8962b499f7a70a59b',
            customerGroupName: 'Standard customer group',
            guest: false,
        });
        expect(shopwareAnalytics.reset).not.toHaveBeenCalled();
        expect(shopwareAnalytics.page).toHaveBeenCalled();
    });

    it('does not call the identify method if the same logged in customer is fetched', async () => {
        const shopwareAnalytics = createShopwareAnalytics();

        global.localStorage.setItem(USER_ID, JSON.stringify('76cedcd24d624176b772d894b7e8ca12'));

        customerResponse = {
            id: '76cedcd24d624176b772d894b7e8ca12',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@doe.com',
            customerGroupId: '6311ea0e19da43c8962b499f7a70a59b',
            customerGroupName: 'Standard customer group',
            guest: false,
        }

        await startShopwareAnalytics(shopwareAnalytics);

        expect(shopwareAnalytics.identify).not.toHaveBeenCalled();
        expect(shopwareAnalytics.reset).not.toHaveBeenCalled();
        expect(shopwareAnalytics.page).toHaveBeenCalled();
    });

    it('calls reset if no customer is fetched but a user is still in storage', async () => {
        const shopwareAnalytics = createShopwareAnalytics();

        global.localStorage.setItem(USER_ID, JSON.stringify('76cedcd24d624176b772d894b7e8ca12'));

        await startShopwareAnalytics(shopwareAnalytics);

        expect(shopwareAnalytics.identify).not.toHaveBeenCalled();
        expect(shopwareAnalytics.reset).toHaveBeenCalled();
        expect(shopwareAnalytics.page).not.toHaveBeenCalled();
    });
});
