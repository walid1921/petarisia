import jestMockFetch from 'jest-mock-fetch';

global.fetch = jestMockFetch.default;

if (typeof globalThis.TextEncoder === "undefined" ||
    typeof globalThis.TextDecoder === "undefined") {
    const { TextEncoder, TextDecoder } = await import('node:util');

    globalThis.TextEncoder = globalThis.TextEncoder ?? TextEncoder;
    globalThis.TextDecoder = globalThis.TextDecoder ?? TextDecoder;
    globalThis.Uint8Array = Uint8Array;
}

if (typeof globalThis.crypto !== "undefined" &&
    typeof globalThis.crypto.subtle === "undefined") {
    const ncrypto = await import('node:crypto');

    Object.assign(globalThis.crypto, ncrypto);
}

expect.extend({
    toBeAnalyticsCall(received, expectedResource, expectedOptions = {}) {
        if (!Array.isArray(received) || received.length !== 2) {
            return {
                pass: false,
                message: () => 'Received value must be a list of arguments passed to fetch.\n' +
                 'Received: ' + this.utils.printReceived(received),
            }
        }

        const [resource, options] = received;

        if (!this.equals(resource, expectedResource)) {
            return {
                pass: false,
                message: () => `Expected resource to be "${expectedResource}", but received "${resource}".`,
            }
        }

        let match = true;

        if (expectedOptions.method && !this.equals(options.method, expectedOptions.method)) {
            match = false;
        }

        if (expectedOptions.headers && !this.equals(options.headers, expectedOptions.headers)) {
            match = false;
        }

        if (expectedOptions.body && typeof options.body === 'string' && !this.equals(JSON.parse(options.body), expectedOptions.body)) {
            match = false;
        }

        return match
            ? {
                pass: true,
                message: () => '',
            } : {
                pass: false,
                message: () => 'Expected fetch to be called with the correct options.\n' +
                    'Diff: ' + this.utils.diff(expectedOptions, { ...options, body: JSON.parse(options.body) }),
            }
    },
})
