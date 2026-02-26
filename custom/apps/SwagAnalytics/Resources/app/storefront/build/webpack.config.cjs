const webpack = require('webpack');

module.exports = () => {
    const analyticsGatewayBaseURL = process.env.ANALYTICS_GATEWAY_BASE_URL || 'https://analytics.shopware.io/v1';

    return {
        plugins: [
            new webpack.DefinePlugin({
                AnalyticsGatewayBaseURL: JSON.stringify(analyticsGatewayBaseURL),
            }),
        ],
    };
};
