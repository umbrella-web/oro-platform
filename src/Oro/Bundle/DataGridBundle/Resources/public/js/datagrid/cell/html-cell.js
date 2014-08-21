/*global define*/
define([
    './string-cell'
], function (StringCell) {
    'use strict';

    var HtmlCell;

    /**
     * Html column cell. Added missing behaviour.
     *
     * @export  oro/datagrid/cell/html-cell
     * @class   oro.datagrid.cell.HtmlCell
     * @extends oro.datagrid.cell.StringCell
     */
    HtmlCell = StringCell.extend({
        /**
         * Render a text string in a table cell. The text is converted from the
         * model's raw value for this cell's column.
         */
        render: function () {
            this.$el.empty().html(this.formatter.fromRaw(this.model.get(this.column.get("name"))));
            return this;
        }
    });

    return HtmlCell;
});
