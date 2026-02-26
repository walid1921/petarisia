export default {
    removeItem(key) {
        deleteCookie(key)
    },

    getItem(key) {
        return getCookie(key)
    },
}

function getCookie(name){
    return document.cookie.split(';').some(c => {
        return c.trim().startsWith(name + '=');
    });
}

function deleteCookie( name, path, domain ) {
    if( getCookie( name ) ) {
        document.cookie = name + '=' +
            ((path) ? ';path=' + path : '') +
            ((domain) ? ';domain=' + domain : '') +
            ';expires=Thu, 01 Jan 1970 00:00:01 GMT';
    }
}
