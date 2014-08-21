/*jslint nomen:true*/
/*global define*/
define([
    'underscore',
    'backbone',
    'orotranslation/js/translator',
    './pagination-input',
    './page-size',
    './actions-panel'
], function (_, Backbone, __, PaginationInput, PageSize, ActionsPanel) {
    'use strict';

    var $, Toolbar;
    $ = Backbone.$;

    /**
     * Datagrid toolbar widget
     *
     * @export  orodatagrid/js/datagrid/toolbar
     * @class   orodatagrid.datagrid.Toolbar
     * @extends Backbone.View
     */
    Toolbar = Backbone.View.extend({
        /** @property */
        template: '#template-datagrid-toolbar',

        /** @property */
        pagination: PaginationInput,

        /** @property */
        pageSize: PageSize,

        /** @property */
        actionsPanel: ActionsPanel,

        /** @property */
        extraActionsPanel: ActionsPanel,

        /**
         * Initializer.
         *
         * @param {Object} options
         * @param {Backbone.Collection} options.collection
         * @param {Array} options.actions List of actions
         * @throws {TypeError} If "collection" is undefined
         */
        initialize: function (options) {
            options = options || {};

            if (!options.collection) {
                throw new TypeError("'collection' is required");
            }

            this.collection = options.collection;

            this.subviews = {
                pagination: new this.pagination(_.defaults({collection: this.collection}, options.pagination)),
                pageSize: new this.pageSize(_.defaults({collection: this.collection}, options.pageSize)),
                actionsPanel: new this.actionsPanel(_.extend({}, options.actionsPanel)),
                extraActionsPanel: new this.extraActionsPanel()
            };

            if (options.actions) {
                this.subviews.actionsPanel.setActions(options.actions);
            }
            if (options.extraActions) {
                this.subviews.extraActionsPanel.setActions(options.extraActions);
            }

            if (_.has(options, 'enable') && !options.enable) {
                this.disable();
            }
            if (options.hide || this.collection.state.hideToolbar) {
                this.hide();
            }

            this.template = _.template($(options.template || this.template).html());

            Toolbar.__super__.initialize.call(this, options);
        },

        /**
         * Enable toolbar
         *
         * @return {*}
         */
        enable: function () {
            this.subviews.pagination.enable();
            this.subviews.pageSize.enable();
            this.subviews.actionsPanel.enable();
            this.subviews.extraActionsPanel.enable();
            return this;
        },

        /**
         * Disable toolbar
         *
         * @return {*}
         */
        disable: function () {
            this.subviews.pagination.disable();
            this.subviews.pageSize.disable();
            this.subviews.actionsPanel.disable();
            this.subviews.extraActionsPanel.disable();
            return this;
        },

        /**
         * Hide toolbar
         *
         * @return {*}
         */
        hide: function () {
            this.$el.hide();
            return this;
        },

        /**
         * Render toolbar with pager and other views
         */
        render: function () {
            var $pagination;
            this.$el.empty();
            this.$el.append(this.template());

            $pagination = this.subviews.pagination.render().$el;
            $pagination.attr('class', this.$('.pagination').attr('class'));

            this.$('.pagination').replaceWith($pagination);
            this.$('.page-size').append(this.subviews.pageSize.render().$el);
            this.$('.actions-panel').append(this.subviews.actionsPanel.render().$el);
            if (this.subviews.extraActionsPanel.haveActions()) {
                this.$('.extra-actions-panel').append(this.subviews.extraActionsPanel.render().$el);
            } else {
                this.$('.extra-actions-panel').hide();
            }

            return this;
        }
    });

    return Toolbar;
});
