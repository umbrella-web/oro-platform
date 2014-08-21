/*jslint nomen:true*/
/*global define*/
define([
    'jquery',
    'backbone',
    'backgrid'
], function ($, Backbone, Backgrid) {
    "use strict";

    var SelectRowCell;

    /**
     * Renders a checkbox for row selection.
     *
     * @export  oro/datagrid/cell/select-row-cell
     * @class   oro.datagrid.cell.SelectRowCell
     * @extends Backbone.View
     */
    SelectRowCell = Backbone.View.extend({

        /** @property */
        className: "select-row-cell",

        /** @property */
        tagName: "td",

        /** @property */
        events: {
            "change :checkbox": "onChange",
            "click": "enterEditMode"
        },

        /**
         * Initializer. If the underlying model triggers a `select` event, this cell
         * will change its checked value according to the event's `selected` value.
         *
         * @param {Object} options
         * @param {Backgrid.Column} options.column
         * @param {Backbone.Model} options.model
         */
        initialize: function (options) {
            Backgrid.requireOptions(options, ["model", "column"]);

            this.column = options.column;
            if (!(this.column instanceof Backgrid.Column)) {
                this.column = new Backgrid.Column(this.column);
            }

            this.listenTo(this.model, "backgrid:select", function (model, checked) {
                this.$checkbox.prop("checked", checked).change();
            });
        },

        /**
         * @inheritDoc
         */
        dispose: function () {
            if (this.disposed) {
                return;
            }
            delete this.column;
            delete this.$checkbox;
            SelectRowCell.__super__.dispose.apply(this, arguments);
        },

        /**
         * Focuses the checkbox.
         */
        enterEditMode: function (e) {
            if (this.$checkbox[0] !== e.target) {
                this.$checkbox.prop("checked", !this.$checkbox.prop("checked")).change();
            }
            e.stopPropagation();
        },

        /**
         * When the checkbox's value changes, this method will trigger a Backbone
         * `backgrid:selected` event with a reference of the model and the
         * checkbox's `checked` value.
         */
        onChange: function (e) {
            this.model.trigger("backgrid:selected", this.model, $(e.target).prop("checked"));
        },

        /**
         * Renders a checkbox in a table cell.
         */
        render: function () {
            // work around with trigger event to get current state of model (selected or not)
            var state = {selected: false};
            this.$el.empty().append('<input tabindex="-1" type="checkbox" />');
            this.$checkbox = this.$(':checkbox');
            this.model.trigger('backgrid:isSelected', this.model, state);
            if (state.selected) {
                this.$checkbox.prop('checked', 'checked');
            }
            return this;
        }
    });

    return SelectRowCell;
});
