/* global define */
define([
    'underscore', 'backbone', 'routing', 'oroui/js/messenger', 'orotranslation/js/translator',
    'oroworkflow/js/workflow-management/step/view/list',
    'oroworkflow/js/workflow-management/step/model',
    'oroworkflow/js/workflow-management/transition/model',
    'oroworkflow/js/workflow-management/step/view/edit',
    'oroworkflow/js/workflow-management/transition/view/edit',
    'oroworkflow/js/workflow-management/helper',
    'oroui/js/tools',
    'oroui/js/mediator',
    'oroui/js/delete-confirmation',
    'oroentity/js/fields-loader'
],
function(_, Backbone, routing, messenger, __,
     StepsListView,
     StepModel,
     TransitionModel,
     StepEditView,
     TransitionEditForm,
     Helper,
     tools,
     mediator,
     Confirmation
) {
    'use strict';

    var $ = Backbone.$;

    /**
     * @export  oroworkflow/js/workflow-management
     * @class   oro.WorkflowManagement
     * @extends Backbone.View
     */
    return Backbone.View.extend({
        events: {
            'click .add-step-btn': 'addNewStep',
            'click .add-transition-btn': 'addNewTransition'
        },

        options: {
            stepsEl: null,
            model: null,
            entities: []
        },

        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            this.saveAndClose = false;

            _.each(this.model.get('steps').models, this.setWorkflow, this);
            _.each(this.model.get('transitions').models, this.setWorkflow, this);
            this.listenTo(this.model.get('steps'), 'add', this.setWorkflow);
            this.listenTo(this.model.get('transitions'), 'add', this.setWorkflow);

            this.addStartingPoint();
            this.initStartStepSelector();

            this.stepListView = new StepsListView({
                el: this.$(this.options.stepsEl),
                collection: this.model.get('steps'),
                workflow: this.model
            });
            this.$entitySelectEl = this.$('[name$="[related_entity]"]');
            this.initEntityFieldsLoader();
            this.initForm();

            this.listenTo(this.model, 'requestAddTransition', this.addNewStepTransition);
            this.listenTo(this.model, 'requestEditStep', this.openManageStepForm);
            this.listenTo(this.model, 'requestCloneStep', this.cloneStep);
            this.listenTo(this.model, 'requestRemoveStep', this.removeStep);
            this.listenTo(this.model.get('steps'), 'destroy', this.onStepRemove);

            this.listenTo(this.model, 'requestRemoveTransition', this.removeTransition);
            this.listenTo(this.model, 'requestCloneTransition', this.cloneTransition);
            this.listenTo(this.model, 'requestEditTransition', this.openManageTransitionForm);

            this.listenTo(this.model, 'change:entity', this.resetWorkflow);
        },

        initForm: function() {
            this.$el.on('submit', _.bind(this.saveConfiguration, this));
            this.model.url = this.$el.attr('action');

            this.$('[type="submit"]').on('click', _.bind(function() {
                this.saveAndClose = true;
            }, this));
            this.$('[data-action="save_and_stay"]').on('click', _.bind(function() {
                this.saveAndClose = false;
            }, this));
        },

        setWorkflow: function(item) {
            item.setWorkflow(this.model);
        },

        initStartStepSelector: function() {
            var getSteps = _.bind(function(query) {
                var steps = [];
                _.each(this.model.get('steps').models, function(step) {
                    // starting point is not allowed to be a start step
                    var stepLabel = step.get('label');
                    if (!step.get('_is_start')
                        && (!query.term || query.term == stepLabel || _.indexOf(stepLabel, query.term) !== -1)
                    ) {
                        steps.push({
                            'id': step.get('name'),
                            'text': step.get('label')
                        });
                    }
                }, this);

                query.callback({results: steps});
            }, this);

            this.$startStepEl = this.$('[name="start_step"]');

            var select2Options = {
                'allowClear': true,
                'query': getSteps,
                'placeholder': __('Choose step...'),
                'initSelection' : _.bind(function (element, callback) {
                    var startStep = this.model.getStepByName(element.val());
                    callback({
                        id: startStep.get('name'),
                        text: startStep.get('label')
                    });
                }, this)
            };

            this.$startStepEl.select2(select2Options);
        },

        initEntityFieldsLoader: function() {
            var confirm = new Confirmation({
                title: __('Change Entity Confirmation'),
                okText: __('Yes, I Agree'),
                content: __('oro.workflow.change_entity_confirmation')
            });
            confirm.on('ok', _.bind(function() {
                this.model.set('entity', this.$entitySelectEl.val());
            }, this));
            confirm.on('cancel', _.bind(function() {
                this.$entitySelectEl.select2('val', this.model.get('entity'));
            }, this));

            this.$entitySelectEl.fieldsLoader({
                router: 'oro_workflow_api_rest_entity_get',
                routingParams: {},
                confirm: confirm,
                requireConfirm: _.bind(function () {
                     return this.model.get('steps').length > 1 &&
                         (this.model.get('transitions').length
                            + this.model.get('transition_definitions').length
                            + this.model.get('attributes').length) > 0;
                }, this)
            });

            this.$entitySelectEl.on('change', _.bind(function() {
                if (!this.model.get('entity')) {
                    this.model.set('entity', this.$entitySelectEl.val());
                }
            }, this));

            this.$entitySelectEl.on('fieldsloadercomplete', _.bind(function(e) {
                this.initEntityFieldsData($(e.target).data('fields'));
            }, this));

            this._preloadEntityFieldsData();
        },

        _preloadEntityFieldsData: function() {
            if (this.$entitySelectEl.val()) {
                var fieldsData = this.$entitySelectEl.fieldsLoader('getFieldsData');
                if (!fieldsData.length) {
                    this.$entitySelectEl.fieldsLoader('loadFields');
                } else {
                    this.initEntityFieldsData(fieldsData);
                }
            }
        },

        addStartingPoint: function() {
            this.model.get('steps').add(this._createStartingPoint());
        },

        resetWorkflow: function() {
            //Need to manually destroy collection elements to trigger all appropriate events
            var resetCollection = function(collection) {
                if (collection.length) {
                    for (var i = collection.length -1; i > -1; i--) {
                        collection.at(i).destroy();
                    }
                }
            };

            this.model.set('start_step', null);
            resetCollection(this.model.get('attributes'));
            resetCollection(this.model.get('steps'));
            resetCollection(this.model.get('transition_definitions'));
            resetCollection(this.model.get('transitions'));

            this.addStartingPoint();
        },

        initEntityFieldsData: function(fields) {
            this.model.setEntityFieldsData(fields);
        },

        saveConfiguration: function(e) {
            e.preventDefault();
            if (!this.$el.valid()) {
                return;
            }

            var formData = Helper.getFormData(this.$el);
            formData.steps_display_ordered = formData.hasOwnProperty('steps_display_ordered');

            if (!this.model.get('name')) {
                this.model.set('name', Helper.getNameByString(formData.label, 'workflow_'));
            }
            this.model.set('label', formData.label);
            this.model.set('steps_display_ordered', formData.steps_display_ordered);
            this.model.set('entity', formData.related_entity);
            this.model.set('start_step', formData.start_step);

            if (!this.validateConfiguration()) {
                return;
            }

            mediator.execute('showLoading');
            this.model.save(null, {
                'success': _.bind(function() {
                    mediator.execute('hideLoading');

                    var redirectUrl = '',
                        modelName = this.model.get('name');
                    if (this.saveAndClose) {
                        redirectUrl = routing.generate('oro_workflow_definition_view', { name: modelName });
                    } else {
                        redirectUrl = routing.generate('oro_workflow_definition_update', { name: modelName });
                    }

                    mediator.once('page:afterChange', function() {
                        messenger.notificationFlashMessage('success', __('Workflow saved.'));
                    });
                    mediator.execute('redirectTo', {url: redirectUrl});
                }, this),
                'error': function(model, response) {
                    mediator.execute('hideLoading');
                    var jsonResponse = response.responseJSON || {};

                    if (tools.debug && !_.isUndefined(console) && !_.isUndefined(jsonResponse.error)) {
                        console.error(jsonResponse.error);
                    }
                    messenger.notificationFlashMessage('error', __('Could not save workflow.'));
                }
            });
        },

        validateConfiguration: function() {
            // workflow label should be defined
            if (!this.model.get('label')) {
                messenger.notificationFlashMessage('error', __('Could not save workflow. Please set workflow name.'));
                return false;
            }

            // related entity should be defined
            if (!this.model.get('entity')) {
                messenger.notificationFlashMessage('error', __('Could not save workflow. Please set related entity.'));
                return false;
            }

            // at least one step and one transition must exist
            if (this.model.get('steps').length <= 1 || this.model.get('transitions').length == 0) {
                messenger.notificationFlashMessage(
                    'error',
                    __('Could not save workflow. Please add at least one step and one transition.')
                );
                return false;
            }

            // should be defined either start step or at least one start transition
            if (!this.model.get('start_step') && _.isEmpty(this._getStartingPoint().get('allowed_transitions'))) {
                messenger.notificationFlashMessage(
                    'error',
                    __('Could not save workflow. Please either set default step or add transitions to starting point.')
                );
                return false;
            }

            return true;
        },

        _createStartingPoint: function() {
            var startStepModel = new StepModel({
                'name': 'step:starting_point',
                'label': __('(Start)'),
                'order': -1,
                '_is_start': true
            });

            startStepModel
                .getAllowedTransitions(this.model)
                .reset(this.model.getStartTransitions());

            return startStepModel;
        },

        _getStartingPoint: function() {
            return this.model.getStepByName('step:starting_point');
        },

        renderSteps: function() {
            this.stepListView.render();
        },

        addNewStep: function() {
            var step = new StepModel();
            this.openManageStepForm(step);
        },

        addNewTransition: function() {
            this.addNewStepTransition(null);
        },

        cloneTransition: function(transition, step) {
            var clonedTransition = this.model.cloneTransition(transition, true);
            this.openManageTransitionForm(clonedTransition, step);
        },

        removeTransition: function(model) {
            this._removeHandler(model, __('Are you sure you want to delete this transition?'));
        },

        _removeHandler: function(model, message) {
            var confirm = new Confirmation({
                content: message
            });
            confirm.on('ok', function () {
                model.destroy();
            });
            confirm.open();
        },

        addNewStepTransition: function(step) {
            var transition = new TransitionModel();
            this.openManageTransitionForm(transition, step);
        },

        _showModalMessage: function(message, title, okText) {
            var confirm = new Confirmation({
                title: title || '',
                content: message,
                okText: okText || __('Ok'),
                allowCancel: false
            });
            confirm.open();
        },

        openManageTransitionForm: function(transition, step_from) {
            if (this.model.get('steps').length == 1) {
                this._showModalMessage(__('At least one step should be added to add transition.'), __('Warning'));
                return;
            }
            if (!this.$entitySelectEl.val()) {
                this._showModalMessage(__('Related entity must be selected to add transition.'), __('Warning'));
                return;
            }

            var transitionEditView = new TransitionEditForm({
                'model': transition,
                'workflow': this.model,
                'step_from': step_from,
                'entity_select_el': this.$entitySelectEl,
                'entities': this.options.entities,
                'workflowContainer': this.$el
            });
            transitionEditView.on('transitionAdd', this.addTransition, this);
            transitionEditView.render();
        },

        addTransition: function(transition, stepFrom) {
            if (!this.model.get('transitions').get(transition.cid)) {
                if (_.isString(stepFrom)) {
                    stepFrom = this.model.getStepByName(stepFrom);
                }
                transition.set('is_start', stepFrom.get('_is_start'));

                this.model
                    .get('transitions')
                    .add(transition);

                stepFrom
                    .getAllowedTransitions(this.model)
                    .add(transition);
            }
        },

        openManageStepForm: function(step) {
            if (!this.$entitySelectEl.val()) {
                this._showModalMessage(__('Related entity must be selected to add step.'), __('Warning'));
                return;
            }

            var stepEditView = new StepEditView({
                'model': step,
                'workflow': this.model,
                'workflowContainer': this.$el
            });
            stepEditView.on('stepAdd', this.addStep, this);
            stepEditView.render();
        },

        cloneStep: function(step) {
            var clonedStep = this.model.cloneStep(step, true);
            this.openManageStepForm(clonedStep);
        },

        addStep: function(step) {
            if (!this.model.get('steps').get(step.cid)) {
                this.model.get('steps').add(step);
            }
        },

        removeStep: function(model) {
            this._removeHandler(model, __('Are you sure you want to delete this step?'));
        },

        onStepRemove: function(step) {
            //Deselect start_step if it was removed
            if (this.$startStepEl.val() == step.get('name')) {
                this.$startStepEl.select2('val', '');
            }
        },

        render: function() {
            this.renderSteps();
            return this;
        }
    });
});
