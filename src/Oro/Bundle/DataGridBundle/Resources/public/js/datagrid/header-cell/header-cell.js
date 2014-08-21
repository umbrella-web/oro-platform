/*jslint nomen:true*/
/*global define*/
define([
    'underscore',
    'backgrid',
    '../../pageable-collection'
], function (_, Backgrid, PageableCollection) {
    "use strict";

    var HeaderCell;

    /**
     * Datagrid header cell
     *
     * @export  orodatagrid/js/datagrid/header-cell/header-cell
     * @class   orodatagrid.datagrid.headerCell.HeaderCell
     * @extends Backgrid.HeaderCell
     */
    HeaderCell = Backgrid.HeaderCell.extend({

        /** @property */
        template: _.template(
            '<% if (sortable) { %>' +
                '<a href="#">' +
                    '<%= label %> ' +
                    '<span class="caret"></span>' +
                '</a>' +
            '<% } else { %>' +
                '<span><%= label %></span>' + // wrap label into span otherwise underscore will not render it
            '<% } %>'
        ),

        /** @property {Boolean} */
        allowNoSorting: true,

        /**
         * Initialize.
         *
         * Add listening "reset" event of collection to able catch situation when header cell should update it's sort state.
         */
        initialize: function () {
            this.allowNoSorting = this.collection.multipleSorting;
            HeaderCell.__super__.initialize.apply(this, arguments);
            this._initCellDirection(this.collection);
            this.listenTo(this.collection, 'reset', this._initCellDirection);
        },

        /**
         * @inheritDoc
         */
        dispose: function () {
            if (this.disposed) {
                return;
            }
            delete this.column;
            HeaderCell.__super__.dispose.apply(this, arguments);
        },

        /**
         * There is no need to reset cell direction because of multiple sorting
         *
         * @private
         */
        _resetCellDirection: function () {},

        /**
         * Inits cell direction when collections loads first time.
         *
         * @param collection
         * @private
         */
        _initCellDirection: function (collection) {
            var state, direction, columnName;
            if (collection === this.collection) {
                state = collection.state;
                direction = null;
                columnName = this.column.get('name');
                if (this.column.get('sortable') && _.has(state.sorters, columnName)) {
                    if (1 === parseInt(state.sorters[columnName], 10)) {
                        direction = 'descending';
                    } else if (-1 === parseInt(state.sorters[columnName], 10)) {
                        direction = 'ascending';
                    }
                }
                if (direction !== this.direction()) {
                    this.direction(direction);
                }
            }
        },

        /**
         * Renders a header cell with a sorter and a label.
         *
         * @return {*}
         */
        render: function () {
            this.$el.empty();

            this.$el.append(this.template({
                label: this.column.get("label"),
                sortable: this.column.get("sortable")
            }));

            if (this.column.has('width')) {
                this.$el.width(this.column.get('width'));
            }

            if (!_.isUndefined(this.column.attributes.cell.prototype.className)) {
                this.$el.addClass(this.column.attributes.cell.prototype.className);
            }

            if (this.column.has('align')) {
                this.$el.css('text-align', this.column.get('align'));
            }

            return this;
        },

        /**
         * Click on column name to perform sorting
         *
         * @param {Event} e
         */
        onClick: function (e) {
            e.preventDefault();

            var columnName = this.column.get("name");

            if (this.column.get("sortable")) {
                if (this.direction() === "ascending") {
                    this.sort(columnName, "descending", function (left, right) {
                        var leftVal, rightVal, res;
                        leftVal = left.get(columnName);
                        rightVal = right.get(columnName);
                        res = 1;
                        if (leftVal === rightVal) {
                            res = 0;
                        } else if (leftVal > rightVal) {
                            res = -1;
                        }
                        return res;
                    });
                } else if (this.allowNoSorting && this.direction() === "descending") {
                    this.sort(columnName, null);
                } else {
                    this.sort(columnName, "ascending", function (left, right) {
                        var leftVal, rightVal, res;
                        leftVal = left.get(columnName);
                        rightVal = right.get(columnName);
                        res = 1;
                        if (leftVal === rightVal) {
                            res = 0;
                        } else if (leftVal < rightVal) {
                            res = -1;
                        }
                        return res;
                    });
                }
            }
        },

        /**
         * @param {string} columnName
         * @param {null|"ascending"|"descending"} direction
         * @param {function(*, *): number} [comparator]
         */
        sort: function (columnName, direction, comparator) {
            comparator = comparator || this._cidComparator;

            var order, collection = this.collection;

            if (collection instanceof PageableCollection) {
                if (direction === "ascending") {
                    order = -1;
                } else if (direction === "descending") {
                    order = 1;
                } else {
                    order = null;
                }

                collection.setSorting(columnName, order);

                if (collection.mode === "client") {
                    if (!collection.fullCollection.comparator) {
                        collection.fullCollection.comparator = comparator;
                    }
                    collection.fullCollection.sort();
                } else {
                    collection.fetch({reset: true});
                }
            } else {
                collection.comparator = comparator;
                collection.sort();
            }

            this.collection.trigger("backgrid:sort", columnName, direction, comparator, this.collection);
        }
    });

    return HeaderCell;
});
