define([
    'oro/datagrid/action/model-action',
    'oroui/js/restore-confirmation',
    'oroui/js/mediator'
], function(ModelAction, RestoreConfirmation, mediator) {
    'use strict';

    var AjaxrestorefieldAction;

    /**
     * Ajax restore field action, shows confirmation dialogue, triggers REST AJAX request
     * and on success - refresh current page
     *
     * @export  oro/datagrid/action/ajaxrestorefield-action
     * @class   oro.datagrid.action.AjaxrestorefieldAction
     * @extends oro.datagrid.action.ModelAction
     */
    AjaxrestorefieldAction = ModelAction.extend({
        confirmation: true,

        /** @property {Function} */
        confirmModalConstructor: RestoreConfirmation,

        defaultMessages: {
            confirm_title: 'Restore Confirmation',
            confirm_content: 'oro.entity_extend.restore_field.confirm_content',
            confirm_ok: 'Yes',
            confirm_cancel: 'Cancel',
            success: 'Restored.',
            error: 'Not restored.',
            empty_selection: 'Please, select item to restore.'
        },
        _onAjaxSuccess: function() {
            mediator.execute('refreshPage');
        }
    });

    return AjaxrestorefieldAction;
});

