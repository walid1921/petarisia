# Shopware Analytics

## Event data

Some data is collected and sent with every event.
This data cannot be overridden and can be viewed as the [base data](#base-data).

### Base data

#### Context data

| Key                      | Description                                             | Example                                                                                                                                                                       |
|--------------------------|---------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `context.app`            | The type of the storefront (Storefront / Frontends)     | `'storefront' \| 'frontends'`                                                                                                                                                 |
| `context.locale`         | The shopper's preferred language                        | `'en-GB'`                                                                                                                                                                     |
| `context.page.path`      | The page path on which the event occurred               | `'/Aerodynamic-Aluminum-PANEL-Performance-Area-Network-Electronic-Links’`                                                                                                     |
| `context.page.referrer`  | The page referrer on which the event occurred           | `'https://www.shopware.com/en/'`                                                                                                                                              |
| `context.page.search`    | The page search on which the event occurred             | `'?search=query'`                                                                                                                                                             |
| `context.page.title`     | The page referrer on which the event occurred           | `'Electronics'`                                                                                                                                                               |
| `context.page.url`       | The page URL on which the event occurred                | `http://localhost:9998/Aerodynamic-Aluminum-PANEL-Performance-Area-Network-Electronic-Links`                                                                                  |'
| `context.screen.density` | The shopper's screen pixel ratio                        | `1.2`                                                                                                                                                                         |
| `context.screen.height`  | The shopper's screen height (in pixels)                 | `1080`                                                                                                                                                                        |
| `context.screen.width`   | The shopper's screen width (in pixels)                  | `1920`                                                                                                                                                                        |
| `context.timestamp`      | The timestamp (milliseconds) of when the event occurred | `1709559530285` (`Date.now()`)                                                                                                                                                |
| `context.timezone`       | The timezone of the shopper                             | `'Europe/Berlin'`                                                                                                                                                             |
| `context.userAgent`      | The user agent of the shopper                           | `'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'`                                                       |
| `context.userAgentData`  | The user agent data of the shopper (if available)       | `{"brands": [{"brand": "Not/A)Brand", "version": "8"}, {"brand": "Chromium", "version": "126"}, {"brand": "Brave", "version": "126"}], "mobile": false, "platform": "macOS"}` |
| `properties`             | The properties of the event                             | `{Object}`                                                                                                                                                                    |
| `timestamp`              | The timestamp (ISO string) of when the event occurred   | `'2024-03-04T13:38:50.285Z'`                                                                                                                                                  |'
| `trackingId`             | The tracking ID                                         | `'SW-8IY68RGJ7YM4LGYB'`                                                                                                                                                       |'
| `type`                   | The type of event that occurred                         | `'page' \| 'track' \| 'identify'`                                                                                                                                             |
| `anonymousId`            | The visitor ID (not a customer specific PUID)           | `'ed2bed5b-5185-4812-8ccf-ecd2bbb95e4d'`                                                                                                                                      |

#### Customer data

When a customer is logged in, the following additional data is sent:

| Key                          | Description                                            | Example                              |
|------------------------------|--------------------------------------------------------|--------------------------------------|
| `customer.customerGroupName` | The shopper's customer group name                      | `'Standard customer group'`          |
| `customer.customerGroupId`   | The shopper's customer group ID                        | `'fe8d2c9b0a3f4df3a6bf8e2edcc28bc2'` |
| `customer.guest`             | Boolean to identify if this is a guest customer or not | `false`                              |

### Page view data

When a page view is tracked, the following properties are sent:

| Key                   | Description              | Example                                                                                                                            |
|-----------------------|--------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| `properties.category` | The category of the page | `'Home page' \| 'Landing page' \| 'Product listing' \| 'Product detail' \| 'Checkout' \| 'Cart' \| 'Other'` (list to be completed) |
| `properties.name`     | The name of the page     | `'Finish checkout'` (list to be completed)                                                                                         |
| `properties.title`    | The title of the page    | `'Home'` (`document.title`)                                                                                                        |
| `properties.path`     | The path of the page     | `'/Aerodynamic-Steel-SpotOn/018da6ccad3673c0b52c0dbe78cccfdd'` (`location.pathname`)                                               |
| `properties.url`      | The url of the page      | `'http://localhost:9998/Aerodynamic-Steel-SpotOn/018da6ccad3673c0b52c0dbe78cccfdd'` (`location.href`)                              |
| `properties.referrer` | The referrer of the page | `'https://shopware.com'` (`document.referrer`)                                                                                     |
| `properties.search`   | The search               | `'?search=query'` (`location.search`)                                                                                              |

The Storefront integration adds the additional properties:

| Key                                | Description                  | Example                                                         |
|------------------------------------|------------------------------|-----------------------------------------------------------------|
| `properties.storefrontAction`      | The Storefront action        | `'home'`                                                        |
| `properties.storefrontCmsPageType` | (Optional) The CMS page type | `'page' \| 'landingpage' \| 'product_list' \| 'product_detail'` |
| `properties.storefrontController`  | The Storefront controller    | `'navigation'`                                                  |
| `properties.storefrontRoute`       | The Storefront route         | `'frontend.home.page'`                                          |
