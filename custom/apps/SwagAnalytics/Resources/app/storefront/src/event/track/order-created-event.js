/**
 * @private
 */
export function pageLoaded() {
    const urlParams = new URLSearchParams(window.location.search);

    window._shopwareAnalytics.track('order:placed', {
        orderId: urlParams.get('orderId'),
    });
}
