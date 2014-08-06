
require(['jquery', 'underscore', 'orotranslation/js/translator', 'oroui/js/modal', 'oroui/js/mediator'
    ], function ($, _, __, Modal, mediator) {
    'use strict';
    $(function () {
        $(document).on('click', '.custom-button-delete-field', function (e) {
            var el = $(this),
                message = el.data('message'),
                title = __('Field delete confirmation'),
                content = '<p>' + __('Are you sure you want to delete this field?.') + '</p>' +
                    '</p>' + __('Your config changes applied after update schema.') + '</p>',
                /** @type oro.Modal */
                confirmUpdate = new Modal({
                    allowCancel: true,
                    cancelText: __('Cancel'),
                    okText: __('Yes, Delete'),
                    title: title,
                    content: content
                });

            confirmUpdate.on('ok', deleteAction);
            confirmUpdate.open();

            return false;
        });

        $(document).on('click', '.custom-button-restore-field', function (e) {
            var el = $(this),
                message = el.data('message'),
                title = __('Field restore confirmation'),
                content = '<p>' + __('Are you sure you want to restore this field?.') + '</p>' +
                    '</p>' + __('Your config changes applied after update schema.') + '</p>',
                /** @type oro.Modal */
                confirmUpdate = new Modal({
                    allowCancel: true,
                    cancelText: __('Cancel'),
                    okText: __('Yes, Restore'),
                    title: title,
                    content: content
                });

            confirmUpdate.on('ok', restoreAction);
            confirmUpdate.open();

            return false;
        });
        function restoreAction() {
            execute('restore');
        }

        function deleteAction() {
            execute('delete');
        }

        function execute(action) {

             var url, delimiter, modal, progress;

            progress = $('#progressbar').clone();
            progress.removeAttr('id').find('h3').remove();

            modal = new Modal({
                allowCancel: false,
                title: __('title'),
                content: __('content')
            });
            modal.open();
            modal.$el.find('.modal-footer').html(progress);
            progress.show();
            
            switch (action) {
                case 'delete':
                    /* if the function will be called with a parameter to remove, replace the attributes of button to  the attributes of restore button */
                    var url = $('.custom-button-delete-field').attr('id');                                
                    $.post(url, function(deleteField) {
                        if (deleteField) { 
                            url = url.replace("/remove/","/unremove/"); 
                            $('.custom-button-delete-field').addClass('btn-primary custom-button-restore-field');
                            $('.custom-button-restore-field').removeClass('btn-danger custom-button-delete-field');
                            $('.custom-button-restore-field').attr({'title':__('Restore field'), 'id': url});
                            $('.custom-button-restore-field').text(__('Restore'));
                            modal.close();
                            return
                        }              
                    });

                    break
                case 'restore':
                    /* if the function will be called with a parameter to restore, replace the attributes of button to  the attributes of delete button */
                    var url = $('.custom-button-restore-field').attr('id');                        
                    $.post(url, function(deleteField) {
                        if (deleteField) {  
                            url = url.replace("/unremove/","/remove/"); 
                            $('.custom-button-restore-field').addClass('btn-danger custom-button-delete-field');
                            $('.custom-button-delete-field').removeClass('btn-primary custom-button-restore-field');
                            $('.custom-button-delete-field').attr({'title':__('Delete field'), 'id': url});
                            $('.custom-button-delete-field').text(__('Delete'));
                            modal.close();
                            return
                        }              
                    });
                    break
            }
        }
    });
});
