Ext.ns('Ext.ux.state');

/**
 * @class Ext.ux.state.LocalStorageProvider
 * @extends Ext.state.Provider
 * A Provider implementation which saves and retrieves state via the HTML5 localStorage object.
 * If the browser does not support local storage, an exception will be thrown upon instantiating
 * this class.
 * <br />Usage:
 <pre><code>
 Ext.state.Manager.setProvider(new Ext.ux.state.LocalStorageProvider({prefix: 'my-'}));
 </code></pre>
 * @cfg {String} prefix The application-wide prefix for the stored objects
 * @constructor
 * Create a new LocalStorageProvider
 * @param {Object} config The configuration object
 */
Ext.ux.state.LocalStorageProvider = Ext.extend(Ext.state.Provider, {

    constructor: function (config) {
        Ext.ux.state.LocalStorageProvider.superclass.constructor.call(this);
        Ext.apply(this, config);
        this.store = this.getStorageObject();
        this.state = this.readLocalStorage();
    },

    readLocalStorage: function () {
        var store = this.store,
            i = 0,
            prefix = this.prefix,
            prefixLen = prefix.length,
            data = {},
            key;

        if (store !== false) {
            var len = store.length;
            for (; i < len; ++i) {
                key = store.key(i);
                if (key.substring(0, prefixLen) == prefix) {
                    data[key.substr(prefixLen)] = this.decodeValue(store.getItem(key));
                }
            }
        }

        return data;
    },

    set: function (name, value) {
        this.clear(name);
        if (typeof value == "undefined" || value === null) {
            return;
        }
        this.store.setItem(this.prefix + name, this.encodeValue(value));

        Ext.ux.state.LocalStorageProvider.superclass.set.call(this, name, value);
    },

    // private
    clear: function (name) {
        this.store.removeItem(this.prefix + name);

        Ext.ux.state.LocalStorageProvider.superclass.clear.call(this, name);
    },

    isStorageAvailable: function (type) {
        var storage;
        try {
            storage = window[type];
            var x = '__storage_test__';
            storage.setItem(x, x);
            storage.removeItem(x);
            return true;
        } catch (e) {
            return e instanceof DOMException && (
                    // everything except Firefox
                e.code === 22 ||
                // Firefox
                e.code === 1014 ||
                // test name field too, because code might not be present
                // everything except Firefox
                e.name === 'QuotaExceededError' ||
                // Firefox
                e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                // acknowledge QuotaExceededError only if there's something already stored
                (storage && storage.length !== 0);
        }
    },

    getStorageObject: function () {
        var storage = false;
        try {
            if (this.isStorageAvailable('localStorage')) {
                storage = window.localStorage;
            }
        } catch (e) {
            storage = false;
        }

        return storage;
    }
});
