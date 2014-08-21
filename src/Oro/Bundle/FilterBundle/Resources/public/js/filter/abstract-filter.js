/*jslint nomen:true*/
/*global define*/
define([
    'jquery',
    'underscore',
    'backbone',
    'oroui/js/tools'
], function ($, _, Backbone, tools) {
    'use strict';

    var AbstractFilter;

    /**
     * Basic grid filter
     *
     * @export  oro/filter/abstract-filter
     * @class   oro.filter.AbstractFilter
     * @extends Backbone.View
     */
    AbstractFilter = Backbone.View.extend({
        /**
         * Template for filter criteria
         *
         * @property
         */
        template: '',

        /**
         * Template selector for filter criteria
         * (should be defined for descendant filter)
         *
         * @property
         */
        templateSelector: '',

        /**
         * Filter decoration theme, empty string means default theme.
         * Gets appended to base templateSelector property
         *
         * @property
         */
        templateTheme: '',


        /**
         * Is filter can be disabled
         *
         * @property {Boolean}
         */
        canDisable: true,

        /**
         * Is filter enabled
         *
         * @property {Boolean}
         */
        enabled: false,

        /**
         * Is filter enabled by default
         *
         * @property {Boolean}
         */
        defaultEnabled: false,

        /**
         * Name of filter field
         *
         * @property {String}
         */
        name: 'input_name',

        /**
         * Placeholder for default value
         *
         * @property
         */
        placeholder: 'All',

        /**
         * Label of filter
         *
         * @property {String}
         */
        label: 'Input Label',

        /**
         * Is filter label visible
         *
         * @property {Boolean}
         */
        showLabel: true,

        /**
         * Parent element active class
         *
         * @property {String}
         */
        buttonActiveClass: 'open-filter',

        /**
         * Null link value
         *
         * @property {String}
         */
        nullLink: 'javascript:void(0);',

        /**
         * Initialize.
         *
         * @param {Object} options
         * @param {Boolean} [options.enabled]
         */
        initialize: function (options) {
            var opts = _.pick(options || {}, 'enabled', 'canDisable', 'placeholder', 'showLabel', 'label',
                'templateSelector', 'templateTheme');
            _.extend(this, opts);

            this._defineTemplate();

            this.defaultEnabled = this.enabled;

            // init empty value object if it was not initialized so far
            if (_.isUndefined(this.emptyValue)) {
                this.emptyValue = {};
            }
            // init raw value of filter
            this.value = _.clone(this.emptyValue);

            AbstractFilter.__super__.initialize.apply(this, arguments);
        },

        /**
         * @inheritDoc
         */
        dispose: function () {
            if (this.disposed) {
                return;
            }
            delete this.value;
            delete this.emptyValue;
            AbstractFilter.__super__.dispose.call(this);
        },

        /**
         * Enable filter
         *
         * @return {*}
         */
        enable: function () {
            if (!this.enabled) {
                this.enabled = true;
                this.show();
                this.trigger('enable', this);
            }
            return this;
        },

        /**
         * Disable filter
         *
         * @return {*}
         */
        disable: function () {
            if (this.enabled) {
                this.enabled = false;
                this.hide();
                this.trigger('disable', this);
                this.reset();
            }
            return this;
        },

        /**
         * Show filter
         *
         * @return {*}
         */
        show: function () {
            this.$el.css('display', 'inline-block');
            return this;
        },

        /**
         * Hide filter
         *
         * @return {*}
         */
        hide: function () {
            this.$el.hide();
            return this;
        },

        /**
         * Reset filter elements
         *
         * @return {*}
         */
        reset: function () {
            this.setValue(this.emptyValue);
            return this;
        },

        /**
         * Get clone of current raw value
         *
         * @return {Object}
         */
        getValue: function () {
            return tools.deepClone(this.value);
        },

        /**
         * Set raw value to filter
         *
         * @param value
         * @return {*}
         */
        setValue: function (value) {
            if (!tools.isEqualsLoosely(this.value, value)) {
                var oldValue = this.value;
                this.value = tools.deepClone(value);
                this._updateDOMValue();
                this._onValueUpdated(this.value, oldValue);
            }
            return this;
        },

        /**
         * Converts a display value to raw format, e.g. decimal value can be displayed as "5,000,000.00"
         * but raw value is 5000000.0
         *
         * @param {*} value
         * @return {*}
         * @protected
         */
        _formatRawValue: function (value) {
            return value;
        },

        /**
         * Converts a raw value to display format, opposite to _formatRawValue
         *
         * @param {*} value
         * @return {*}
         * @protected
         */
        _formatDisplayValue: function (value) {
            return value;
        },

        /**
         * Triggers when filter value is updated
         *
         * @param {*} newValue
         * @param {*} oldValue
         * @protected
         */
        _onValueUpdated: function (newValue, oldValue) {
            this._triggerUpdate(newValue, oldValue);
        },

        /**
         * Handles click on filter-item reset button
         *
         * @param {jQuery.Event} e
         * @private
         */
        _onClickResetFilter: function (e) {
            e.stopPropagation();
            this.reset();
        },

        /**
         * Triggers update event
         *
         * @param {*} newValue
         * @param {*} oldValue
         * @protected
         */
        _triggerUpdate: function (newValue, oldValue) {
            this.trigger('update');
        },

        /**
         * Compares current value with empty value
         *
         * @return {Boolean}
         */
        isEmpty: function () {
            return tools.isEqualsLoosely(this.getValue(), this.emptyValue);
        },

        /**
         * Determines whether a filter value is empty or not
         * Unlike isEmpty method this method should take in account only data values.
         * For example if a filter has a string value and comparison type, the comparison type
         * should be ignored in this method.
         *
         * @return {Boolean}
         */
        isEmptyValue: function () {
            if (_.has(this.emptyValue, 'value') && _.has(this.value, 'value')) {
                return tools.isEqualsLoosely(this.value.value, this.emptyValue.value);
            }
            return true;
        },

        /**
         * Gets input value. Radio inputs are supported.
         *
         * @param {String|Object} input
         * @return {*}
         * @protected
         */
        _getInputValue: function (input) {
            var result = undefined;
            var $input = this.$(input);
            switch ($input.attr('type')) {
                case 'radio':
                    $input.each(function () {
                        if ($(this).is(':checked')) {
                            result = $(this).val();
                        }
                    });
                    break;
                default:
                    result = $input.val();

            }
            return result;
        },

        /**
         * Sets input value. Radio inputs are supported.
         *
         * @param {String|Object} input
         * @param {String} value
         * @protected
         * @return {*}
         */
        _setInputValue: function (input, value) {
            var $input = this.$(input);
            switch ($input.attr('type')) {
                case 'radio':
                    $input.each(function () {
                        var $input = $(this);
                        if ($input.attr('value') == value) {
                            $input.attr('checked', true);
                            $input.click();
                        } else {
                            $(this).removeAttr('checked');
                        }
                    });
                    break;
                default:
                    $input.val(value);

            }
            return this;
        },

        /**
         * Updated DOM value with current display value
         *
         * @return {*}
         * @protected
         */
        _updateDOMValue: function () {
            return this._writeDOMValue(this._getDisplayValue());
        },

        /**
         * Get criteria hint value
         *
         * @return {String}
         */
        _getCriteriaHint: function () {
            return '';
        },

        /**
         * Get current value formatted to display format
         *
         * @return {*}
         * @protected
         */
        _getDisplayValue: function () {
            var value = (arguments.length > 0) ? arguments[0] : this.getValue();
            return this._formatDisplayValue(value);
        },

        /**
         * Writes values from object into DOM elements
         *
         * @param {Object} value
         * @abstract
         * @protected
         * @return {*}
         */
        _writeDOMValue: function (value) {
            throw new Error("Method _writeDOMValue is abstract and must be implemented");
            //this._setInputValue(inputValueSelector, value.value);
            //return this
        },

        /**
         * Reads value of DOM elements into object
         *
         * @return {Object}
         * @protected
         */
        _readDOMValue: function () {
            throw new Error("Method _readDOMValue is abstract and must be implemented");
            //return { value: this._getInputValue(this.inputValueSelector) }
        },

        /**
         * Set filter button class
         *
         * @param {Object} element
         * @param {Boolean} status
         * @protected
         */
        _setButtonPressed: function (element, status) {
            if (status) {
                element.parent().addClass(this.buttonActiveClass);
            } else {
                element.parent().removeClass(this.buttonActiveClass);
            }
        },

        /**
         * Prevent submit of parent form if any.
         *
         * @param {Event} e
         * @private
         */
        _preventEnterProcessing: function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                e.stopPropagation();
            }
        },

        /**
         * Apply changes manually
         *
         * @public
         */
        applyValue: function () {
            this.setValue(this._formatRawValue(this._readDOMValue()));
        },

        /**
         * Defines which template to use
         *
         * @private
         */
        _defineTemplate: function () {
            this.template = this._getTemplate(this.templateSelector);
        },

        _getTemplate: function (selector) {
            var theme = this.templateTheme,
                src = theme && $(selector + '-' + theme).text() || $(selector).text();

            return _.template(src);
        }
    });

    return AbstractFilter;
});
