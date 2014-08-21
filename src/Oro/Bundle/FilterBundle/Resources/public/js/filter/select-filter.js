/*jslint nomen:true*/
/*global define*/
define([
    'jquery',
    'underscore',
    'orotranslation/js/translator',
    './abstract-filter',
    'orofilter/js/multiselect-decorator'
], function ($, _, __, AbstractFilter, MultiselectDecorator) {
    'use strict';

    var SelectFilter;

    /**
     * Select filter: filter value as select option
     *
     * @export  oro/filter/select-filter
     * @class   oro.filter.SelectFilter
     * @extends oro.filter.AbstractFilter
     */
    SelectFilter = AbstractFilter.extend({
        /**
         * Filter selector template
         *
         * @property
         */
        templateSelector: '#select-filter-template',

        /**
         * Should default value be added to options list
         *
         * @property
         */
        populateDefault: true,

        /**
         * Selector for filter area
         *
         * @property
         */
        containerSelector: '.filter-select',

        /**
         * Selector for close button
         *
         * @property
         */
        disableSelector: '.disable-filter',

        /**
         * Selector for widget button
         *
         * @property
         */
        buttonSelector: '.filter-criteria-selector',

        /**
         * Selector for select input element
         *
         * @property
         */
        inputSelector: 'select',

        /**
         * Select widget object
         *
         * @property
         */
        selectWidget: null,

        /**
         * Minimum widget menu width, calculated depends on filter options
         *
         * @property
         */
        minimumWidth: null,

        /**
         * Select widget options
         *
         * @property
         */
        widgetOptions: {
            multiple: false,
            classes: 'select-filter-widget'
        },

        /**
         * Select widget menu opened flag
         *
         * @property
         */
        selectDropdownOpened: false,

        /**
         * @property {Boolean}
         */
        contextSearch: true,

        /**
         * Filter events
         *
         * @property
         */
        events: {
            'keydown select': '_preventEnterProcessing',
            'click .filter-select': '_onClickFilterArea',
            'click .disable-filter': '_onClickDisableFilter',
            'change select': '_onSelectChange',
            'click .reset-filter': '_onClickResetFilter'
        },

        /**
         * Initialize.
         *
         * @param {Object} options
         */
        initialize: function (options) {
            var opts = _.pick(options || {}, 'choices');
            _.extend(this, opts);

            // init filter content options if it was not initialized so far
            if (_.isUndefined(this.choices)) {
                this.choices = [];
            }
            // temp code to keep backward compatible
            this.choices = _.map(this.choices, function (option, i) {
                return _.isString(option) ? {value: i, label: option} : option;
            });

            // init empty value object if it was not initialized so far
            if (_.isUndefined(this.emptyValue)) {
                this.emptyValue = {
                    value: ''
                };
            }

            SelectFilter.__super__.initialize.apply(this, arguments);
        },

        /**
         * @inheritDoc
         */
        dispose: function () {
            if (this.disposed) {
                return;
            }
            delete this.choices;
            this.selectWidget.dispose();
            delete this.selectWidget;
            SelectFilter.__super__.dispose.call(this);
        },

        /**
         * Render filter template
         *
         * @return {*}
         */
        render: function () {
            var options = this.choices.slice(0);
            if (this.populateDefault) {
                options.unshift({value: '', label: this.placeholder});
            }

            this.setElement((
                this.template({
                    label: this.label,
                    showLabel: this.showLabel,
                    options: options,
                    placeholder: this.placeholder,
                    nullLink: this.nullLink,
                    canDisable: this.canDisable,
                    selected: _.extend({}, this.emptyValue, this.value),
                    isEmpty: this.isEmpty()
                })
            ));

            this._initializeSelectWidget();

            return this;
        },

        /**
         * Initialize multiselect widget
         *
         * @protected
         */
        _initializeSelectWidget: function () {
            this.selectWidget = new MultiselectDecorator({
                element: this.$(this.inputSelector),
                parameters: _.extend({
                    noneSelectedText: this.placeholder,
                    selectedText: _.bind(function (numChecked, numTotal, checkedItems) {
                        return this._getSelectedText(checkedItems);
                    }, this),
                    position: {
                        my: 'left top+7',
                        at: 'left bottom',
                        of: this.$(this.containerSelector)
                    },
                    open: _.bind(function () {
                        this.selectWidget.onOpenDropdown();
                        this._setDropdownWidth();
                        this._setButtonPressed(this.$(this.containerSelector), true);
                        this._clearChoicesStyle();
                        this.selectDropdownOpened = true;
                    }, this),
                    close: _.bind(function () {
                        this._setButtonPressed(this.$(this.containerSelector), false);
                        setTimeout(_.bind(function () {
                            this.selectDropdownOpened = false;
                        }, this), 100);
                    }, this)
                }, this.widgetOptions),
                contextSearch: this.contextSearch
            });

            this.selectWidget.setViewDesign(this);
            this.$(this.buttonSelector).append('<span class="caret"></span>');
            this.selectWidget.getWidget().on('keyup', _.bind(function (e) {
                if (e.keyCode === 27) {
                    this._onClickFilterArea(e);
                }
            }, this));
        },

        /**
         * Remove styles from choices list
         *
         * @protected
         */
        _clearChoicesStyle: function () {
            var labels = this.selectWidget.getWidget().find('label');
            labels.removeClass('ui-state-hover');
            if (_.isEmpty(this.value.value)) {
                labels.removeClass('ui-state-active');
            }
        },

        /**
         * Get text for filter hint
         *
         * @param {Array} checkedItems
         * @protected
         */
        _getSelectedText: function (checkedItems) {
            if (_.isEmpty(checkedItems)) {
                return this.placeholder;
            }

            var elements = [];
            _.each(checkedItems, function (element) {
                var title = element.getAttribute('title');
                if (title) {
                    elements.push(title);
                }
            });
            return elements.join(', ');
        },

        /**
         * Get criteria hint value
         *
         * @return {String}
         */
        _getCriteriaHint: function () {
            var value = (arguments.length > 0) ? this._getDisplayValue(arguments[0]) : this._getDisplayValue();
            var choice = _.find(this.choices, function (c) {
                return (c.value == value.value);
            });
            return !_.isUndefined(choice) ? choice.label : this.placeholder;
        },

        /**
         * Set design for select dropdown
         *
         * @protected
         */
        _setDropdownWidth: function () {
            if (!this.minimumWidth) {
                this.minimumWidth = this.selectWidget.getMinimumDropdownWidth() + 22;
            }
            var widget = this.selectWidget.getWidget(),
                filterWidth = this.$(this.containerSelector).width(),
                requiredWidth = Math.max(filterWidth + 24, this.minimumWidth);
            widget.width(requiredWidth).css('min-width', requiredWidth + 'px');
            widget.find('input[type="search"]').width(requiredWidth - 30);
        },

        /**
         * Open/close select dropdown
         *
         * @param {Event} e
         * @protected
         */
        _onClickFilterArea: function (e) {
            if (!this.selectDropdownOpened) {
                setTimeout(_.bind(function () {
                    this.selectWidget.multiselect('open');
                }, this), 50);
            } else {
                setTimeout(_.bind(function () {
                    this.selectWidget.multiselect('close');
                }, this), 50);
            }

            e.stopPropagation();
        },

        /**
         * Triggers change data event
         *
         * @protected
         */
        _onSelectChange: function () {
            // set value
            this.applyValue();
            // update dropdown
            this.selectWidget.updateDropdownPosition();
        },

        /**
         * Handle click on filter disabler
         *
         * @param {Event} e
         */
        _onClickDisableFilter: function (e) {
            e.preventDefault();
            this.disable();
        },

        /**
         * @inheritDoc
         */
        _onValueUpdated: function (newValue, oldValue) {
            SelectFilter.__super__._onValueUpdated.apply(this, arguments);
            this.selectWidget.multiselect('refresh');
            this.$(this.buttonSelector)
                .toggleClass('filter-default-value', this.isEmpty());
        },

        /**
         * @inheritDoc
         */
        _writeDOMValue: function (value) {
            this._setInputValue(this.inputSelector, value.value);
            return this;
        },

        /**
         * @inheritDoc
         */
        _readDOMValue: function () {
            return {
                value: this._getInputValue(this.inputSelector)
            };
        }
    });

    return SelectFilter;
});
