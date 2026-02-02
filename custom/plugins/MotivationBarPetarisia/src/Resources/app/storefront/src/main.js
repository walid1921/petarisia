// Import all necessary Storefront plugins
// import ExamplePlugin from './example-plugin/example-plugin.plugin';
// import CustomAddToCartPlugin from './custom-add-to-cart/custom-add-to-cart.plugin';
import MotivationProgressPlugin from './motivation-progress/motivation-progress.plugin';

// Register your plugin via the existing PluginManager
// const PluginManager = window.PluginManager;

// PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');
// PluginManager.override('AddToCart', CustomAddToCartPlugin, '[data-add-to-cart]');
PluginManager.register('MotivationBar', MotivationProgressPlugin, '[data-motivation-bar]');
