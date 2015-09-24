define([
    'oro/datagrid/action/ajaxdelete-action',
    'oroui/js/erase-confirmation',
    'oroui/js/mediator'
], function(AjaxdeleteAction, EraseConfirmation, mediator) {
    'use strict';

    var AjaxerasefieldAction;

    /**
     * Ajax erase field action, shows confirmation dialogue, triggers REST AJAX request
     * and on success - refresh current page
     *
     * @export  oro/datagrid/action/ajaxerasefield-action
     * @class   oro.datagrid.action.AjaxerasefieldAction
     * @extends oro.datagrid.action.AjaxdeleteAction
     */
    AjaxerasefieldAction = AjaxdeleteAction.extend({
        confirmation: true,

        /** @property {Function} */
        confirmModalConstructor: EraseConfirmation,

        defaultMessages: {
            confirm_title: 'Erase Confirmation',
            confirm_content: 'oro.entity_extend.erase_field.confirm_content',
            confirm_ok: 'Yes',
            confirm_cancel: 'Cancel',
            success: 'Erased.',
            error: 'Not erased.',
            empty_selection: 'Please, select item to erase.'
        },
        _onAjaxSuccess: function() {
            mediator.execute('refreshPage');
        }
    });

    return AjaxerasefieldAction;
});

