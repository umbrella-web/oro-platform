/*jslint nomen:true*/
/*global define*/
define([
    'jquery',
    'underscore',
    'backgrid'
], function ($, _, Backgrid) {
    'use strict';

    var ActionCell;

    /**
     * Cell for grid, contains actions
     *
     * @export  oro/datagrid/cell/action-cell
     * @class   oro.datagrid.cell.ActionCell
     * @extends Backgrid.Cell
     */
    ActionCell = Backgrid.Cell.extend({

        /** @property */
        className: "action-cell",

        /** @property {Array} */
        actions: undefined,

        /** @property {Array} */
        launchers: undefined,

        /** @property */
        template: _.template(
            '<div class="more-bar-holder">' +
                '<div class="dropdown">' +
                    '<a data-toggle="dropdown" class="dropdown-toggle" href="javascript:void(0);">...</a>' +
                    '<ul class="dropdown-menu pull-right launchers-dropdown-menu"></ul>' +
                '</div>' +
            '</div>'
        ),

        /** @property */
        launchersListTemplate: _.template(
            '<% if (withIcons) { %>' +
                '<li><ul class="nav nav-pills icons-holder launchers-list"></ul></li>' +
            '<% } else { %>' +
                '<li class="well-small"><ul class="unstyled launchers-list"></ul></li>' +
            '<% } %>'
        ),

        /** @property */
        launcherItemTemplate:_.template(
            '<li class="launcher-item"></li>'
        ),

        /** @property */
        events: {
            'click': '_toggleDropdown',
            'mouseover .dropdown-toggle': '_toggleDropdown',
            'mouseleave .dropdown-menu': '_toggleDropdown'
        },

        /**
         * Initialize cell actions and launchers
         */
        initialize: function () {
            this.subviews = [];

            ActionCell.__super__.initialize.apply(this, arguments);
            this.actions = this.createActions();
            _.each(this.actions, function (action) {
                this.listenTo(action, 'run', this.onActionRun);
            }, this);

            this.launchers = this.createLaunchers();
            this.subviews.push.apply(this.subviews, this.actions);
        },

        /**
         * @inheritDoc
         */
        dispose: function () {
            if (this.disposed) {
                return;
            }
            delete this.launchers;
            delete this.actions;
            delete this.column;
            this.$('.dropdown-toggle').dropdown('destroy');
            ActionCell.__super__.dispose.apply(this, arguments);
        },

        /**
         * Handle action run
         *
         * @param {oro.datagrid.action.AbstractAction} action
         */
        onActionRun: function (action) {
            this.$('.dropdown.open').removeClass('open');
        },

        /**
         * Creates actions
         *
         * @return {Array}
         */
        createActions: function () {
            var result, actions, config;
            result = [];
            actions = this.column.get('actions');
            config = this.model.get('action_configuration');

            _.each(actions, function (action, name) {
                // filter available actions for current row
                if (!config || config[name] !== false) {
                    result.push(this.createAction(action));
                }
            }, this);

            return result;
        },

        /**
         * Creates action
         *
         * @param {Function} Action
         * @protected
         */
        createAction: function (Action) {
            return new Action({
                model: this.model,
                datagrid: this.column.get('datagrid')
            });
        },

        /**
         * Creates actions launchers
         *
         * @protected
         */
        createLaunchers: function () {
            var result = [];

            _.each(this.actions, function (action) {
                var options, launcher;
                options = {};
                launcher = action.createLauncher(options);
                result.push(launcher);
            }, this);

            return result;
        },

        /**
         * Render cell with actions
         */
        render: function () {
            var launchers, $listsContainer;
            // don't render anything if list of launchers is empty
            if (_.isEmpty(this.launchers)) {
                this.$el.empty();

                return this;
            }
            this.$el.empty().append(this.template());

            launchers = this.getLaunchersByIcons();
            $listsContainer = this.$('.launchers-dropdown-menu');

            if (launchers.withIcons.length) {
                this.renderLaunchersList(launchers.withIcons, {withIcons: true})
                    .appendTo($listsContainer);
            }

            if (launchers.withIcons.length && launchers.withoutIcons.length) {
                $listsContainer.append('<li class="divider"></li>');
            }

            if (launchers.withoutIcons.length) {
                this.renderLaunchersList(launchers.withoutIcons, {withIcons: false})
                    .appendTo($listsContainer);
            }

            return this;
        },

        /**
         * Render launchers list
         *
         * @param {Array} launchers
         * @param {Object=} params
         * @return {jQuery} Rendered element wrapped with jQuery
         */
        renderLaunchersList: function (launchers, params) {
            var result, $launchersList;
            params = params || {};
            result = $(this.launchersListTemplate(params));
            $launchersList = result.filter('.launchers-list').length ? result : $('.launchers-list', result);
            _.each(launchers, function (launcher) {
                $launchersList.append(this.renderLauncherItem(launcher));
            }, this);

            return result;
        },

        /**
         * Render launcher
         *
         * @param {orodatagrid.datagrid.ActionLauncher} launcher
         * @param {Object=} params
         * @return {jQuery} Rendered element wrapped with jQuery
         */
        renderLauncherItem: function (launcher, params) {
            var result, $launcherItem;
            params = params || {};
            result = $(this.launcherItemTemplate(params));
            $launcherItem = result.filter('.launcher-item').length ? result : $('.launcher-item', result);
            $launcherItem.append(launcher.render().$el);
            return result;
        },

        /**
         * Get separate object of launchers arrays: with icons (key `withIcons`) and without icons (key `withoutIcons`).
         *
         * @return {Object}
         * @protected
         */
        getLaunchersByIcons: function () {
            var launchers = {
                withIcons: [],
                withoutIcons: []
            };

            _.each(this.launchers, function (launcher) {
                if (launcher.icon) {
                    launchers.withIcons.push(launcher);
                } else {
                    launchers.withoutIcons.push(launcher);
                }
            }, this);

            return launchers;
        },

        /**
         * Open/close dropdown
         *
         * @param {Event} e
         * @protected
         */
        _toggleDropdown: function (e) {
            this.$('.dropdown-toggle').dropdown('toggle');
            e.stopPropagation();
        }
    });

    return ActionCell;
});
