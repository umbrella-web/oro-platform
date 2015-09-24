define(['underscore', 'orotranslation/js/translator', 'oroui/js/modal'
    ], function(_, __, Modal) {
    'use strict';

    /**
     * Erase confirmation dialog
     *
     * @export  oroui/js/erase-confirmation
     * @class   oroui.EraseConfirmation
     * @extends oroui.Modal
     */
    return Modal.extend({
        /** @property {String} */
        className: 'modal oro-modal-danger',

        /** @property {String} */
        okButtonClass: 'btn-danger',

        /**
         * @param {Object} options
         */
        initialize: function(options) {
            options = _.extend({
                title: __('Erase Confirmation'),
                okText: __('Yes, Erase'),
                cancelText: __('Cancel')
            }, options);

            arguments[0] = options;
            Modal.prototype.initialize.apply(this, arguments);
        }
    });
});
