/*global define*/
/*jslint nomen: true*/
define([
    'jquery',
    'routing',
    'orotranslation/js/translator',
    'oroui/js/messenger',
    'oroui/js/tools',
    'jquery-ui'
], function ($, routing, __, messenger, tools) {
    'use strict';

    /**
     * Widget responsible for loading fields of selected entity
     */
    $.widget('oroentity.fieldsLoader', {
        options: {
            router: null,
            routingParams: {},
            afterRevertCallback: null,
            // supports 'oroui/js/modal' confirmation dialog
            confirm: null,
            requireConfirm: function () { return true; }
        },

        _create: function () {
            this.setFieldsData(this.element.data('fields') || []);

            this._on({
                change: this._onChange
            });
        },

        _onChange: function (e) {
            var oldVal, confirm = this.options.confirm;
            if (confirm && this.options.requireConfirm()) {
                // @todo support also other kind of inputs than select2
                oldVal = (e.removed && e.removed.id) || null;
                this._confirm(confirm, e.val, oldVal);
            } else {
                this.loadFields();
            }
        },

        loadFields: function () {
            $.ajax({
                url: routing.generate(this.options.router, this.options.routingParams),
                success: $.proxy(this._onLoaded, this),
                error: this._onError,
                beforeSend: $.proxy(this._trigger, this, 'start'),
                complete: $.proxy(this._trigger, this, 'complete')
            });
        },

        getEntityName: function () {
            return this.element.val();
        },

        setFieldsData: function (data) {
            var fields = this._convertData(data);
            this.element.data('fields', fields);
            this._trigger('update', null, [fields]);
        },

        getFieldsData: function () {
            return this.element.data('fields');
        },

        _confirm: function (confirm, newVal, oldVal) {
            if (!oldVal) {
                return;
            }
            var $el = this.element,
                load = $.proxy(this.loadFields, this),
                revert = function () {
                    $el.val(oldVal).change();
                    if ($.isFunction(this.options.afterRevertCallback)) {
                        this.options.afterRevertCallback.call(this, $el);
                    }
                }.bind(this);
            confirm.on('ok', load);
            confirm.on('cancel', revert);
            confirm.once('hidden', function () {
                confirm.off('ok', load);
                confirm.off('cancel', revert);
            });
            confirm.open();
        },

        _onLoaded: function (data) {
            this.setFieldsData(data);
        },

        _onError: function (jqXHR) {
            var err = jqXHR.responseJSON,
                msg = __('Sorry, unexpected error was occurred');
            if (tools.debug) {
                if (err.message) {
                    msg += ': ' + err.message;
                } else if (err.errors && $.isArray(err.errors)) {
                    msg += ': ' + err.errors.join();
                } else if ($.type(err) === 'string') {
                    msg += ': ' + err;
                }
            }
            messenger.notificationFlashMessage('error', msg);
        },

        /**
         * Converts data in proper array of fields hierarchy
         *
         * @param {Array} data
         * @returns {Array}
         * @private
         */
        _convertData: function (data) {
            $.each(data, function () {
                var entity = this;
                entity.fieldsIndex = {};
                $.each(entity.fields, function () {
                    var field = this;
                    if (field.related_entity_name) {
                        field.related_entity = data[field.related_entity_name];
                        delete field.related_entity_name;
                    }
                    field.entity = entity;
                    entity.fieldsIndex[field.name] = field;
                });
            });
            return data;
        }
    });
});
