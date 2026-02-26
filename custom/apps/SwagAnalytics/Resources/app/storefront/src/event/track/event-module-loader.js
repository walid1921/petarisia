/**
 * @private
 */
export function load(moduleName) {
    return import(`./${moduleName}-event.js`);
}
