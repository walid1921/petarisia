import AddToCartPlugin from 'src/plugin/add-to-cart/add-to-cart.plugin';

export default class CustomAddToCartPlugin extends AddToCartPlugin {
    _openOffCanvasCart() {
        // Override the method to prevent opening the offcanvas cart
        console.log('Item added to cart without opening offcanvas');
    }

}
