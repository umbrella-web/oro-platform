/*global define, require*/
/*jslint nomen: true*/
define(['jquery', 'underscore', 'orotranslation/js/translator', 'orofilter/js/map-filter-module-name',
    'oroentity/js/field-choice', 'jquery-ui'
    ], function ($, _, __, mapFilterModuleName) {
    'use strict';

    /**
     * Compare field widget
     */
    $.widget('oroquerydesigner.fieldCondition', {
        options: {
            fieldChoice: {},
            fieldChoiceClass: 'select',
            filters: [],
            filterContainerClass: 'active-filter',
            hierarchy: []
        },

        _create: function () {
            var data = this.element.data('value');

            // @TODO this 'none' filter probably in not in use any more, to delete
            this.options.filters.push({
                type: 'none',
                applicable: {},
                popupHint: __('Choose a column first')
            });

            this.$fieldChoice = $('<input>').addClass(this.options.fieldChoiceClass);
            this.$filterContainer = $('<span>').addClass(this.options.filterContainerClass);
            this.element.append(this.$fieldChoice, this.$filterContainer);

            this.$fieldChoice.fieldChoice(this.options.fieldChoice);

            if (data && data.columnName) {
                this.selectField(data.columnName);
                this._renderFilter(data.columnName);
            }

            this._on(this.$fieldChoice, {
                changed: function (e, fieldId) {
                    $(':focus').blur();
                    // reset current value on field change
                    this.element.data('value', {});
                    this._renderFilter(fieldId);
                    e.stopPropagation();
                }
            });

            this._on(this.$filterContainer, {
                change: function () {
                    if (this.filter) {
                        this.filter.applyValue();
                    }
                }
            });
        },

        _destroy: function () {
            if (this.filter) {
                this.filter.dispose();
                delete this.filter;
            }
        },

        _getCreateOptions: function () {
            return $.extend(true, {}, this.options);
        },

        _renderFilter: function (fieldId) {
            var conditions = this.$fieldChoice.fieldChoice('getApplicableConditions', fieldId),
                filterId = this._getApplicableFilterId(conditions);
            this._createFilter(this.options.filters[filterId]);
        },

        _getApplicableFilterId: function (conditions) {
            var filterId = null,
                matchedBy = null,
                self = this;

            if (!_.isUndefined(conditions.filter)) {
                // the criteria parameter represents a filter
                return conditions.filter;
            }

            _.each(this.options.filters, function (filter, id) {
                var matched;

                if (!_.isEmpty(filter.applicable)) {
                    // check if a filter conforms the given criteria
                    matched = self._matchApplicable(filter.applicable, conditions);
                    if (matched && (
                            _.isNull(matchedBy) ||
                            // new rule is more exact
                            _.size(matchedBy) < _.size(matched) ||
                            // 'type' rule is most low level one, so any other rule can override it
                            (_.size(matchedBy) === 1 && _.has(matchedBy, 'type'))
                        )) {
                        matchedBy = matched;
                        filterId = id;
                    }
                } else if (_.isNull(filterId)) {
                    // if a filter was not found so far, use a default filter
                    filterId = id;
                }
            });

            return filterId;
        },

        _matchApplicable: function (applicable, criteria) {
            var hierarchy = this.options.hierarchy[criteria.entity];
            return _.find(applicable, function (item) {
                return _.every(item, function (value, key) {
                    if (key == 'entity' && hierarchy.length) {
                        return _.indexOf(hierarchy, criteria[key]);
                    }
                    return criteria[key] === value;
                });
            });
        },

        _createFilter: function (options) {
            var moduleName = mapFilterModuleName(options.type);

            require([moduleName], _.bind(function (Filter) {
                var filter = new (Filter.extend(options))();
                this._appendFilter(filter);
            }, this));
        },

        _appendFilter: function (filter) {
            var value = this.element.data('value');
            this.filter = filter;

            if (value && value.criterion) {
                this.filter.value = value.criterion.data;
            }

            this.filter.render();
            this.$filterContainer.empty().append(this.filter.$el);

            this.filter.on('update', _.bind(this._onUpdate, this));
            this._onUpdate();
        },

        _onUpdate: function () {
            var value;

            if (!this.filter.isEmptyValue()) {
                value = {
                    columnName: this.element.find('input.select').select2('val'),
                    criterion: {
                        filter: this.filter.type,
                        data: this.filter.getValue()
                    }
                };
            } else {
                value = {};
            }

            this.element.data('value', value);
            this.element.trigger('changed');
        },

        selectField: function (name) {
            this.$fieldChoice.fieldChoice('setValue', name);
        }
    });

    return $;
});
