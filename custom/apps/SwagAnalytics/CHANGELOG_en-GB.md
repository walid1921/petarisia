# 2.9.0
- Added new track event `oder:placed`
- Fixed an error when a randomUUID function is not available in older browsers

# 2.8.0
- Fixed a bug where logout events were sent on every page load

# 2.7.0
- Added permission "system_config:update"

# 2.6.0
- Changed logout behaviour. Logged out customers will not be treated as new visitors anymore.

# 2.5.6
- Fixed a bug that made it impossible to recompile the app in shops with versions below 6.6.1.0

# 2.5.5
- Added translations for en-US so that it is now possible to install the app when the system language is set to english, en-US

# 2.5.4
- Fixed a JavaScript bug where the tracking was started even though the trackingId was missing 
- Added en-GB fallback translations for numerous languages

# 2.5.3
- Fixed a JavaScript bug that tried to access a property of an undefined object due to a cached Twig template not being rendered

# 2.5.2
- Added en-US translations for the Storefront

# 2.5.1
- Fixed a JavaScript bug that broke the Storefront due to a cached Twig template not being rendered

# 2.5.0
- Added webhook for "app.installed"
- Added webhook for "app.deleted"

# 2.4.0
- Added KPI "Unique visitors"
- Added KPI "Page views"
- General UI/UX improvements
- Native event tracking for standard storefronts

# 1.9.0
- Added new track event `oder:placed`
- Fixed an error when a randomUUID function is not available in older browsers

# 1.8.0
- Fixed a bug where logout events were sent on every page load

# 1.7.0
- Added permission "system_config:update"

# 1.6.0
- Changed logout behaviour. Logged out customers will not be treated as new visitors anymore.

# 1.5.6
- Fixed a bug that made it impossible to recompile the app Shopware versions below 6.6.1.0

# 1.5.5
- Added translations for en-US so that it is now possible to install the app when the system language is set to english, en-US

# 1.5.4
- Fixed a JavaScript bug where the tracking was started even though the trackingId was missing
- Added en-GB fallback translations for numerous languages

# 1.5.3
- Fixed a JavaScript bug that tried to access a property of an undefined object due to a cached Twig template not being rendered

# 1.5.2
- Added en-US translations for the Storefront

# 1.5.1
- Fixed a JavaScript bug that broke the Storefront due to a cached Twig template not being rendered

# 1.5.0
- Added webhook for "app.installed"
- Added webhook for "app.deleted"

# 1.4.0
- Added KPI "Unique visitors"
- Added KPI "Page views"
- General UI/UX improvements
- Native event tracking for standard storefronts

# 1.3.0

- Added a new KPI "Sales distribution by shipping methods"
- Added new sidebar filters for filtering by order/transactions/delivery states
- Added a new sidebar filter for filtering by stats by guest or registered customers
- Added OAuth authentication
- Fixed content jumped when opening the filter sidebar
- Some UI/UX improvements

# 1.2.0

- Added a new KPI "Sales by country"
- Added a new KPI "Best selling products"
- Some UI enhancements

# 1.1.0

- Added the possibility to show a logo in table charts
- Added a new KPI "Promotion codes"
- Added a new KPI "Sales by manufacturer"
- Technical Improvements

# 1.0.2

- Multi calendar date picker: The improved UI makes it more accessible to select a start and end timeframe.
- Filter configuration is now remembered when a new timeframe is selected
- Improvement of the export function
- General UI Improvements
- Technical Improvements

# 1.0.1
- Fixed a bug where Zero Values were not shown correctly in the chart
- Average order value KPI now excludes discount, promotion, credits and shipping costs
- Date picker now always requires a start and end date

# 1.0.0
- First release of `SwagAnalytics`
