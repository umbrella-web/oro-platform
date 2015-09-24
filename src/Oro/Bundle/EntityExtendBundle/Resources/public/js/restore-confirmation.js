define(['underscore', 'orotranslation/js/translator', 'oroui/js/modal'
    ], function(_, __, Modal) {
    'use strict';

    /**
     * Restore confirmation dialog
     *
     * @export  oroui/js/restore-confirmation
     * @class   oroui.RestoreConfirmation
     * @extends oroui.Modal
     */
    return Modal.extend({
        /**
         * @param {Object} options
         */
        initialize: function(options) {
            options = _.extend({
                title: __('Restore Confirmation'),
                okText: __('Yes, Restore'),
                cancelText: __('Cancel')
            }, options);

            arguments[0] = options;
            Modal.prototype.initialize.apply(this, arguments);
        }
    });
});
