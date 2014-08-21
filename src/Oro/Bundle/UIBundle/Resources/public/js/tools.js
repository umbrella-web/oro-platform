/*jslint nomen:true*/
/*global define, require*/
define(['jquery', 'underscore'], function ($, _) {
    'use strict';

    /**
     * @export oroui/js/tools
     * @name   oroui.tools
     */
    return {
        /** @type {boolean} */
        debug: false,
        /**
         * Pack object to string
         *
         * Object {foo: 'x', 'bar': 'y'} will be converted to string "foo=x&bar=y".
         *
         * @param {Object} object
         * @return {String}
         */
        packToQueryString: function(object) {
            return $.param(object);
        },

        /**
         * Unpack string to object. Reverse from packToQueryString.
         *
         * @param {String} query
         * @return {Object}
         */
        unpackFromQueryString: function(query) {
            var setValue = function (root, path, value) {
                if (path.length > 1) {
                    var dir = path.shift();
                    if (typeof root[dir] === 'undefined') {
                        root[dir] = path[0] == '' ? [] : {};
                    }
                    setValue(root[dir], path, value);
                } else {
                    if (root instanceof Array) {
                        root.push(value);
                    } else {
                        root[path] = value;
                    }
                }
            };
            var nvp = query.split('&');
            var data = {};
            for (var i = 0 ; i < nvp.length ; i++) {
                var pair  = nvp[i].split('=');
                var name  = this.decodeUriComponent(pair[0]);
                var value = this.decodeUriComponent(pair[1]);

                var path = name.match(/(^[^\[]+)(\[.*\]$)?/);
                var first = path[1];
                if (path[2]) {
                    //case of 'array[level1]' || 'array[level1][level2]'
                    path = path[2].match(/(?=\[(.*)\]$)/)[1].split('][');
                } else {
                    //case of 'name'
                    path = [];
                }
                path.unshift(first);

                setValue(data, path, value);
            }
            return data;
        },

        /**
         * Decode URL encoded component
         *
         * @param {String} string
         * @return {String}
         * @protected
         */
        decodeUriComponent: function(string) {
            var result = string.replace('+', '%20');
            result = decodeURIComponent(result);
            return result;
        },

        /**
         * Invert object keys.
         *
         * Example of usage:
         *
         * oro.app.invertKeys({foo: 'x', bar: 'y'}, {foo: 'f', bar: 'b'})
         * will return {f: 'x', b: 'y'}
         *
         * @param {Object} object
         * @param {Object} keys
         * @return {Object}
         */
        invertKeys: function(object, keys) {
            var result = _.extend({}, object);
            for (var key in keys) {
                var mirrorKey, baseKey;
                baseKey = key;
                mirrorKey = keys[key];

                if (baseKey in result) {
                    result[mirrorKey] = result[baseKey];
                    delete result[baseKey];
                }
            }
            return result;
        },

        /**
         * Loosely compare two values
         *
         * @param {*} value1
         * @param {*} value2
         * @return {Boolean} TRUE if values are equal, otherwise - FALSE
         */
        isEqualsLoosely: function (value1, value2) {
            if (!_.isObject(value1)) {
                if (_.isNumber(value1) || _.isNumber(value2)) {
                    var toNumber = function (v) {
                        if (_.isString(v) && v == '') {
                            return NaN;
                        }
                        return Number(v);
                    };
                    return (toNumber(value1) == toNumber(value2));
                }
                return ((value1 || '') == (value2 || ''));
            } else if (_.isObject(value1)) {
                var valueKeys = _.keys(value1);

                if (_.isObject(value2)) {
                    valueKeys = _.unique(valueKeys.concat(_.keys(value2)));
                    for (var index in valueKeys) {
                        var key = valueKeys[index];
                        if (!_.has(value2, key) || !this.isEqualsLoosely(value1[key], value2[key])) {
                            return false;
                        }
                    }
                    return true;
                }
                return false;
            } else {
                return value1 == value2;
            }
        },

        /**
         * Deep clone a value
         *
         * @param {*} value
         * @return {*}
         */
        deepClone: function(value) {
            return $.extend(true, {}, value);
        },

        /**
         * Are we currently on mobile
         */
        isMobile: function () {
            return $('body').hasClass('mobile-version');
        },

        /**
         * Loads dynamic list of modules and execute callback function with passed modules
         *
         * @param {Object.<string, string>} modules where keys are formal module names and values are actual
         * @param {function(Object)} callback
         * @param {Object=} context
         */
        loadModules: function (modules, callback, context) {
            var requirements = _.values(modules);
            // load all dependencies and build grid
            require(requirements, function () {
                _.each(modules, _.bind(function (value, key) {
                    modules[key] = this[value];
                }, _.object(requirements, _.toArray(arguments))));
                callback.call(context || null, modules);
            });
        }
    };
});
