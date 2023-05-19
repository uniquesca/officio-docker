function openMemberTab(tabPanel, data) {
    if (!empty(data.caseId)) {
        switch (data.applicantType) {
            case 'individual':
                tabPanel.openApplicantTab({
                    applicantId:      data.applicantId,
                    applicantName:    data.applicantName,
                    memberType:       data.applicantType,
                    caseId:           data.caseId,
                    caseName:         data.caseName,
                    caseType:         data.caseType
                }, 'profile');
                break;

            case 'employer':
            default:
                tabPanel.openApplicantTab({
                    applicantId:      data.applicantId,
                    applicantName:    data.applicantName,
                    memberType:       data.applicantType,
                    caseId:           data.caseId,
                    caseName:         data.caseName,
                    caseType:         data.caseType,
                    caseEmployerId:   data.applicantId,
                    caseEmployerName: data.applicantName
                }, 'profile');
                break;
        }

    } else {
        tabPanel.openApplicantTab({
            applicantId:      data.applicantId,
            applicantName:    data.applicantName,
            memberType:       data.applicantType
        }, 'profile');
    }
}

var ApplicantsProfileForm = function(config, owner) {
    var thisForm = this;

    Ext.apply(this, config);
    this.owner = owner;

    this.booRendered = false;
    this.booIsDirty = false;
    this.defaultFieldLabelStyle = 'color: #000; font-size: 14px; font-weight: 500;';

    // If we already created a new case, but case type wasn't set yet -
    // Don't show the Change link and after data will be saved - refresh the page (the same as when we create a case)
    this.booShowChangeCaseTypeLink = true;
    if (!empty(thisForm.caseId) && empty(thisForm.caseType)) {
        this.booShowChangeCaseTypeLink = false;
    }

    this.booOpenNewCaseTab = false;
    this.arrThisApplicantCaseTemplates = [];
    var checkMemberType = !empty(config.caseEmployerId) && !empty(config.applicantId) && config.caseEmployerId == config.applicantId ? 'employer' : 'individual';
    var booFilterByLink = !empty(thisForm.caseIdLinkedTo) || (!empty(config.applicantId) && !empty(config.caseEmployerId) && config.applicantId != config.caseEmployerId);
    Ext.each(arrApplicantsSettings.case_templates, function(caseTemplate) {
        if (caseTemplate.case_template_type_names.has(checkMemberType)) {
            // Filter by "categories" if we want to link the current case to a specific one
            // OR case is linked to both IA and Employer
            var booShow = true;
            if (booFilterByLink && !caseTemplate.case_template_can_be_linked_to_employer) {
                booShow = false;
            }

            if (booShow) {
                thisForm.arrThisApplicantCaseTemplates.push({
                    option_id:   caseTemplate.case_template_id,
                    option_name: caseTemplate.case_template_name
                });
            }
        }
    });

    var defaultCaseType = null;
    if (!empty(thisForm.caseType)) {
        defaultCaseType = thisForm.caseType;
    } else if (empty(thisForm.applicantId) && thisForm.arrThisApplicantCaseTemplates.length === 1) {
        defaultCaseType = thisForm.arrThisApplicantCaseTemplates[0]['option_id'];
    }
    this.mainForm = new Ext.form.FormPanel({
        fileUpload: true,
        items: [
            {
                xtype: 'hidden',
                name: 'applicantId',
                value: config.applicantId
            }, {
                xtype: 'hidden',
                name: 'memberType',
                value: config.memberType
            }, {
                xtype: 'hidden',
                name: 'applicantType',
                value: config.applicantType || 0
            }, {
                xtype: 'hidden',
                name: 'caseIdLinkedTo',
                value: config.caseIdLinkedTo || 0
            }, {
                xtype: 'hidden',
                name: 'applicantUpdatedOn',
                value: 0
            }, {
                xtype: 'hidden',
                name: 'forceOverwrite',
                value: 0
            }, {
                xtype: 'hidden',
                name: 'caseId',
                value: config.caseId
            }, {
                xtype: 'hidden',
                name: 'caseName',
                value: config.caseName
            }, {
                xtype: 'hidden',
                name: 'caseType',
                value: defaultCaseType
            }, {
                xtype: 'hidden',
                name: 'caseEmployerId',
                value: config.caseEmployerId
            }, {
                height: 50,
                html: '&nbsp;' // spacer, because of the floating toolbar
            }, {
                xtype: 'panel',
                hidden: empty(arrApplicantsSettings['client_warning']) || thisForm.memberType === 'contact',
                html: '<div style="margin: 10px; padding: 10px; color: red; background-color: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px; font-size: 12px; font-weight: bold;">' +
                          arrApplicantsSettings['client_warning'] +
                        '</div>'
            }, {
                xtype: 'panel',
                layout: 'table',
                hidden: thisForm.memberType == 'client' || thisForm.memberType == 'contact' || thisForm.memberType == 'case',
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '97%',
                            'margin-bottom': '20px'
                        }
                    },
                    columns: 2
                },
                items:  [
                    {
                        xtype: 'panel',
                        hidden: true,
                        uniqueFieldId: 'case_section_read_only_warning',
                        cellCls: 'td-width-70',
                        html: '<div style="margin: 10px 0; padding: 10px; color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px;">' +
                            'The client has already been submitted. Profile cannot be changed.' +
                            '</div>'
                    }, {
                        xtype: 'panel',
                        cellCls: 'td-width-30',
                        hidden: thisForm.memberType == 'client' || thisForm.memberType == 'contact' || thisForm.memberType == 'case',
                        html: '<div style="float: right;">' +
                            '<span class="error">*</span> <span class="garytxt14">indicates mandatory fields</span>' +
                            '</div>'
                    }
                ]
            }, {
                xtype: 'container',
                hidden: thisForm.memberType !== 'case' || empty(thisForm.caseId) || empty(thisForm.caseType),
                uniqueFieldId: 'case_section_case_type_container',
                style: 'margin: 0 5px 0;',
                cls: 'new-client-container',
                items: [{
                    xtype: 'displayfield',
                    style: 'float: right;',
                    value: '<span class="error">*</span> <span class="garytxt14">' + _('indicates mandatory fields') + '</span>'
                }]
            }, {
                xtype: 'container',
                layout: 'column',
                items: [
                    {
                        xtype: 'container',
                        uniqueFieldId: 'case_section_go_to_employer_container',
                        items: []
                    }, {
                        xtype: 'container',
                        uniqueFieldId: 'case_section_related_cases_container',
                        items: []
                    }
                ]
            }
        ]
    });

    this.detailsPanel = new Ext.form.DisplayField({
        value: ''
    });

    this.profileToolbar = new ApplicantsProfileToolbar({
        applicantId: this.applicantId,
        applicantName: this.applicantName,
        caseId: this.caseId,
        caseName: this.caseName,
        caseType: this.caseType,
        memberType: this.memberType
    }, this);

    ApplicantsProfileForm.superclass.constructor.call(this, {
        autoHeight: true,
        defaults: {
            autoScroll: true,
            autoHeight: true
        },

        layout: 'table',
        cls: 'x-table-layout-cell-top-align',
        layoutConfig: {
            tableAttrs: {
                style: {
                    // We cannot use 100% here because of the extjs rendering issue
                    // 275 - tabWidth
                    // 60 - width of paddings
                    width: ($(window).width() - 275 - 60) + 'px'
                }
            },
            columns: 2
        },
        items: [
            {
                xtype:  'container',
                colspan: 2,
                style:  'position: relative; float: left; z-index: 10; width: 100%;',
                autoEl: {
                    tag: 'div'
                },
                items:  {
                    xtype:  'container',
                    style:  'position: absolute; left: 0; width: 100%;',
                    autoEl: {
                        tag: 'div'
                    },
                    items: {
                        xtype:  'container',
                        style:  'background-color: #FFF; width: 100%;',
                        items: this.profileToolbar
                    }
                }
            }, {
                xtype: 'panel',
                autoWidth: true,
                autoHeight: true,
                items: [
                    this.mainForm,
                    this.detailsPanel
                ]
            }
        ]
    });

    this.on('beforerender', this.createAllGroupsAndFields.createDelegate(this));
    this.on('render', this.loadApplicantDetails.createDelegate(this, [true]));
};

Ext.extend(ApplicantsProfileForm, Ext.Panel, {
    generateRowId: function() {
        var rowIdLength = 32;
        var text = "";
        var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

        for( var i=0; i < rowIdLength; i++ )
            text += possible.charAt(Math.floor(Math.random() * possible.length));

        return text;
    },

    openNewCaseTab: function () {
        var thisPanel = this;

        if (thisPanel.memberType == 'case') {
            thisPanel.owner.owner.openApplicantTab({
                applicantId: thisPanel.applicantId,
                applicantName: thisPanel.applicantName,
                memberType: empty(thisPanel.caseEmployerId) ? 'individual' : 'employer',
                caseId: 0,
                caseName: _('Case 1'),
                caseType: '',
                caseEmployerId: thisPanel.caseEmployerId,
                caseEmployerName: thisPanel.caseEmployerName,
                caseIdLinkedTo: thisPanel.caseIdLinkedTo
            }, 'case_details');
        } else if (thisPanel.memberType == 'individual') {
            thisPanel.owner.owner.openApplicantTab({
                applicantId: thisPanel.applicantId,
                applicantName: thisPanel.applicantName,
                memberType: thisPanel.memberType,
                caseId: 0,
                caseName: _('Case 1'),
                caseType: '',
                caseEmployerId: null,
                caseEmployerName: null,
                caseIdLinkedTo: thisPanel.caseIdLinkedTo
            }, 'case_details');
        } else {
            thisPanel.owner.owner.openApplicantTab({
                applicantId: thisPanel.applicantId,
                applicantName: thisPanel.applicantName,
                memberType: thisPanel.memberType,
                caseId: 0,
                caseName: _('Case 1'),
                caseType: '',
                caseEmployerId: thisPanel.applicantId,
                caseEmployerName: thisPanel.applicantName,
                caseIdLinkedTo: thisPanel.caseIdLinkedTo
            }, 'case_details');
        }
    },


    createAllGroupsAndFields: function() {
        this.booRendered = true;

        switch (this.memberType) {
            case 'client':
                if (this.booHideNewClientType) {
                    this.createNewIAAndCaseGroupsAndFields();
                } else {
                    this.createNewClientGroupsAndFields();
                }
                break;

            case 'case':
                if (empty(this.caseId) || empty(this.caseType)) {
                    this.createEmployerNewClientGroupsAndFields();
                } else {
                    this.createCaseFieldsSection();
                }
                this.initImageField();
                this.initFileField();
                break;

            case 'individual':
                this.createApplicantGroupsAndFields();
                this.initImageField();
                this.initFileField();
                break;

            case 'employer':
                this.createApplicantGroupsAndFields();
                this.initImageField();
                this.initFileField();
                break;

            case 'contact':
                if (empty(this.applicantId)) {
                    this.createContactGroupsAndFields();
                }
                this.initImageField();
                this.initFileField();
                break;

            default:
                break;
        }
    },

    initFileField: function() {
        // show field to upload image
        $(document).on('click', '.form-file a[data-rel=change]', function(){
            var parent = $(this).closest('.form-file');

            parent.find('.form-file-view').hide();
            parent.find('.form-file-edit').show();
            return false;
        });

        // cancel button
        $(document).on('click', '.form-file a[data-rel=cancel]', function(){
            var parent = $(this).closest('.form-file');

            parent.find('.form-file-input').val('');
            parent.find('.form-file-edit').hide();
            parent.find('.form-file-view').show();
            return false;
        });

        // remove button
        $(document).on('click', '.form-file a[data-rel=remove]', function(){
            var parent = $(this).closest('.form-file');
            var action = $(this).attr('href');

            if(action.length === 0) {
                return false;
            }

            Ext.Msg.show({
                title: 'Please confirm',
                msg: 'Are you sure you want to remove this file?',
                buttons: {yes: 'Yes', no: 'Cancel'},
                minWidth: 300,
                modal: true,
                icon: Ext.MessageBox.WARNING,
                fn: function(btn) {
                    if (btn == 'yes') {

                        // send request to remove picture in "silent" mode
                        Ext.Ajax.request({
                            url: action,
                            failure: function(){

                                Ext.simpleConfirmation.error('Can\'t remove file: internal error');

                                parent.find('.form-file-view').show();
                                parent.find('.form-file-edit').hide();
                                parent.find('.form-file-edit a[data-rel=cancel]').show();
                            }
                        });

                        // remove image
                        parent.find('.form-file-view').hide();
                        parent.find('.form-file-edit').show();
                        parent.find('.form-file-edit a[data-rel=cancel]').hide();
                    }
                }
            });

        });
    },

    initImageField: function() {
        // show field to upload image
        $(document).on('click', '.form-image a[data-rel=change]', function(){

            var parent = $(this).closest('.form-image');

            parent.find('.form-image-view').hide();
            parent.find('.form-image-edit').show();
            return false;
        });

        // cancel button
        $(document).on('click', '.form-image a[data-rel=cancel]', function(){

            var parent = $(this).closest('.form-image');

            parent.find('.form-image-input').val('');
            parent.find('.form-image-edit').hide();
            parent.find('.form-image-view').show();
            return false;
        });

        // remove button
        $(document).on('click', '.form-image a[data-rel=remove]', function(){

            var parent = $(this).closest('.form-image');
            var action = $(this).attr('href');

            if(action.length === 0) {
                return false;
            }

            Ext.Msg.show({
               title: 'Please confirm',
               msg: 'Are you sure you want to remove this image?',
               buttons: {yes: 'Yes', no: 'Cancel'},
               minWidth: 300,
               modal: true,
               icon: Ext.MessageBox.WARNING,
               fn: function(btn) {
                    if (btn == 'yes') {
                        // remove image
                        parent.find('.form-image-view').hide();
                        parent.find('.form-image-edit').show();
                        parent.find('.form-image-edit a[data-rel=cancel]').hide();

                        // send request to remove picture in "silent" mode
                        Ext.Ajax.request({
                            url: action,
                            success: function (f) {
                                var resultData = Ext.decode(f.responseText);
                                if (!resultData.success) {
                                    Ext.simpleConfirmation.error(resultData.error);

                                    parent.find('.form-image-view').show();
                                    parent.find('.form-image-edit').hide();
                                    parent.find('.form-image-edit a[data-rel=cancel]').show();
                                }
                            },
                            failure: function(){

                                Ext.simpleConfirmation.error('Can\'t remove picture: internal error');

                                parent.find('.form-image-view').show();
                                parent.find('.form-image-edit').hide();
                                parent.find('.form-image-edit a[data-rel=cancel]').show();
                            }
                        });
                    }
               }
            });

        });
    },

    updateDetailsSection: function(details) {
        this.detailsPanel.setValue(details);
    },

    getCategoryByCaseTypeAndCategoryId: function (caseTypeId, caseCategoryId) {
        var category;
        if (!empty(caseCategoryId)) {
            var arrCategories = [];
            if (!empty(arrApplicantsSettings.options.general.categories_grouped[caseTypeId])) {
                arrCategories = arrApplicantsSettings.options.general.categories_grouped[caseTypeId];
            }

            Ext.each(arrCategories, function (oCategory) {
                if (oCategory.option_id == caseCategoryId) {
                    category = oCategory;

                    // don't search anymore
                    return false;
                }
            });
        }

        return category;
    },

    getCategoriesByCaseType: function (caseTypeId, caseCategoryId) {
        var thisPanel = this;
        var arrCategories = [];
        if (!empty(arrApplicantsSettings.options.general.categories_grouped[caseTypeId])) {
            arrCategories = arrApplicantsSettings.options.general.categories_grouped[caseTypeId];

            // If we want to link this case to the specific case -
            // make sure that category allows "linking"
            var booFilterByLink = !empty(thisPanel.caseIdLinkedTo) || (!empty(thisPanel.applicantId) && !empty(thisPanel.caseEmployerId) && thisPanel.applicantId != thisPanel.caseEmployerId);
            if (booFilterByLink) {
                var arrFilteredCategories = [];
                Ext.each(arrCategories, function (oCategory){
                    if (oCategory.link_to_employer === 'Y') {
                        arrFilteredCategories.push(oCategory);
                    }
                });

                arrCategories = arrFilteredCategories;
            }
        }

        if (!empty(caseCategoryId)) {
            var booFound = false;
            Ext.each(arrCategories, function (oCategory){
                if (oCategory.option_id == caseCategoryId) {
                    booFound = true;

                    // don't search anymore
                    return false;
                }
            });

            if (!booFound) {
                Ext.each(arrApplicantsSettings.options.general.categories, function (oCategory){
                    if (oCategory.option_id == caseCategoryId) {
                        arrCategories.push(oCategory);

                        // don't search anymore
                        return false;
                    }
                });
            }
        }

        return arrCategories;
    },

    getCaseStatusesByCaseSettings: function (caseTypeId, caseCategoryId, caseStatusesIds) {
        var arrCaseStatuses = [];

        // Try to load the list of statuses by the provided category
        if (!empty(caseCategoryId) && !empty(arrApplicantsSettings.options.general.case_statuses['categories'][caseCategoryId])) {
            arrCaseStatuses = arrApplicantsSettings.options.general.case_statuses['categories'][caseCategoryId];
        }

        // If there is no category selected and this case type has only 1 category -> use the list of statuses from it
        if (empty(arrCaseStatuses.length) && empty(caseCategoryId) && !empty(arrApplicantsSettings.options.general.categories_grouped[caseTypeId]) && arrApplicantsSettings.options.general.categories_grouped[caseTypeId].length === 1) {
            caseCategoryId = arrApplicantsSettings.options.general.categories_grouped[caseTypeId][0]['option_id'];
            if (!empty(caseCategoryId) && !empty(arrApplicantsSettings.options.general.case_statuses['categories'][caseCategoryId])) {
                arrCaseStatuses = arrApplicantsSettings.options.general.case_statuses['categories'][caseCategoryId];
            }
        }

        if (empty(arrCaseStatuses.length) && !empty(caseTypeId) && !empty(arrApplicantsSettings.options.general.case_statuses['case_types'][caseTypeId])) {
            // Try to load the list of statuses by the provided case type (default list)
            arrCaseStatuses = arrApplicantsSettings.options.general.case_statuses['case_types'][caseTypeId];
        }

        // Check if the provided case status is in the list, if no - load from the main list
        if (!empty(caseStatusesIds)) {
            var arrCaseStatusesIds = caseStatusesIds.split(',');

            Ext.each(arrCaseStatusesIds, function (caseStatusId) {
                var booFound = false;
                Ext.each(arrCaseStatuses, function (oStatus) {
                    if (oStatus.option_id == caseStatusId) {
                        booFound = true;

                        // don't search anymore
                        return false;
                    }
                });

                if (!booFound) {
                    Ext.each(arrApplicantsSettings.options.general.case_statuses['all'], function (oStatus) {
                        if (oStatus.option_id == caseStatusId) {
                            arrCaseStatuses.push(oStatus);

                            // don't search anymore
                            return false;
                        }
                    });
                }
            });
        }

        return arrCaseStatuses;
    },

    createCaseFieldsSection: function() {
        var thisPanel = this;

        var caseTypeField = thisPanel.find('name', 'caseType')[0];
        var caseTypeValue = caseTypeField.getValue();
        thisPanel.booChangedCaseType = false;

        // We can run this method several times,
        // So we need clear previously created fields/groups
        var mainContainer = thisPanel.getUniqueField('case_section_main_container');
        if (mainContainer) {
            thisPanel.mainForm.remove(mainContainer);
        }

        var arrGroups = [];
        arrGroups.push({
            xtype: 'container',
            uniqueFieldId: 'case_section_container',
            items: []
        });

        thisPanel.mainForm.add({
            uniqueFieldId: 'case_section_main_container',
            xtype: 'container',
            items: arrGroups
        });

        thisPanel.createCaseGroupsAndFields(parseInt(caseTypeValue, 10));
    },

    createCaseGoToEmployerButton: function(employerId, employerName, applicantId, applicantName, caseName, caseType) {
        var thisPanel = this;

        var arrGroups = [];
        var casePanel = thisPanel.getUniqueField('case_section_go_to_employer_container');
        if (casePanel) {
            casePanel.removeAll();
            for (var i = 0; i < arrGroups.length; i++) {
                casePanel.add(arrGroups[i]);
            }
            casePanel.doLayout();
        }
        thisPanel.owner.owner.fixParentPanelHeight();
    },

    initClickOnCase: function(c, rec) {
        var thisPanel = this;
        c.getEl().on('click', function() {
            switch (rec.applicant_type) {
                case 'individual':
                    thisPanel.owner.owner.openApplicantTab({
                        applicantId:    rec.applicant_id,
                        applicantName:  rec.applicant_name,
                        memberType:     rec.applicant_type,
                        caseId:         rec.case_id,
                        caseName:       rec.case_name,
                        caseType:       rec.case_type
                    }, 'profile');
                    break;

                case 'employer':
                default:
                    thisPanel.owner.owner.openApplicantTab({
                        applicantId:      rec.applicant_id,
                        applicantName:    rec.applicant_name,
                        memberType:       rec.applicant_type,
                        caseId:           rec.case_id,
                        caseName:         rec.case_name,
                        caseType:         rec.case_type,
                        caseEmployerId:   rec.applicant_id,
                        caseEmployerName: rec.applicant_name
                    }, 'profile');
            }
        }, this, {stopEvent: true});
    },

    createRelatedCaseSection: function(arrCasesWithParents) {
        var thisPanel = this;

        var arrRelatedCases = [];
        for (var j = 0; j < arrCasesWithParents.length; j++) {
            var rec = arrCasesWithParents[j];
            arrRelatedCases.push({
                xtype:  'box',
                autoEl: {
                    tag:     'a',
                    href:    '#',
                    style:   'padding-top: 2px',
                    'class': 'blulinkun',
                    html:    rec.case_and_applicant_name,
                    title:   'Go to "' + rec.case_and_applicant_name + '"'
                },
                listeners: {
                    scope:  this,
                    render: thisPanel.initClickOnCase.createDelegate(this, [rec], true)
                }
            });

            if (j < arrCasesWithParents.length - 1) {
                arrRelatedCases.push({
                    xtype:  'box',
                    autoEl: {
                        tag:     'div',
                        style:   'color: #000; padding-top: 2px; margin-right: 10px;',
                        'class': 'x-form-field-value',
                        html:    ';'
                    }
                });
            }
        }

        var arrGroups = [];
        if (!empty(arrRelatedCases.length)) {
            arrGroups.push({
                xtype: 'container',
                style: 'padding: 0 0 20px 10px',
                layout: 'table',
                cls: 'x-table-layout-cell-top-align',
                layoutConfig: {
                    columns: 2
                },
                items: [
                    {
                        xtype: 'panel',
                        style: 'color: #000; padding: 2px 5px 0 0;',
                        'class': 'x-form-field-value',
                        html: arrCasesWithParents.length == 1 ? _('Related Case:') : _('Related Cases:')
                    }, {
                        xtype: 'container',
                        layout: 'column',
                        width: 500,
                        items: arrRelatedCases
                    }
                ]
            });
        }


        var casePanel = thisPanel.getUniqueField('case_section_related_cases_container');
        if (casePanel) {
            casePanel.removeAll();
            for (var i = 0; i < arrGroups.length; i++) {
                casePanel.add(arrGroups[i]);
            }
            casePanel.doLayout();
        }
        thisPanel.owner.owner.fixParentPanelHeight();
    },

    createCaseGroupsAndFields: function(caseTypeId) {
        var thisPanel = this;

        var arrGroups = [];
        if (arrApplicantsSettings.case_group_templates[caseTypeId]) {
            arrGroups = thisPanel.createGroupsAndFields(
                arrApplicantsSettings.case_group_templates[caseTypeId],
                'case'
            );
        }

        if (!arrGroups.length) {
            arrGroups.push({
                xtype: 'label',
                html: "<div class='padal garytxt'>" + _("There are no fields/groups or you don't have access to fields/groups.") + "</div>"
            });
        }

        var casePanel = thisPanel.getUniqueField('case_section_container');
        if (casePanel) {
            casePanel.removeAll();
            for (var i = 0; i < arrGroups.length; i++) {
                casePanel.add(arrGroups[i]);

            }
            casePanel.doLayout();
        }
        thisPanel.owner.owner.fixParentPanelHeight();
    },

    /**
     * This method is used as alternative to Ext.getCmp method
     * In such case we don't need to use ids -> issue when several similar tabs are opened
     *
     * @param uniqueFieldId
     * @returns {*}
     */
    getUniqueField: function(uniqueFieldId) {
        var arrFields = this.mainForm.find('uniqueFieldId', uniqueFieldId);
        return arrFields.length ? arrFields[0] : null;
    },

    getCurrentTabId: function() {
        var tabId = this.panelType + '-tab-' + this.applicantId;
        if (typeof this.caseId != 'undefined') {
            tabId += '-' + this.caseId;
        }
        return tabId;
    },

    toggleClientGroups: function(container, radioValue) {
        this.memberType = radioValue;
        var memberTypeField = this.find('name', 'memberType');
        if (memberTypeField.length) {
            memberTypeField[0].setValue(radioValue);
        }

        this.toggleNewClientFields(container, radioValue);
    },

    createEmployerNewClientGroupsAndFields: function() {
        var thisPanel = this;

        var newClientType;
        if (!empty(thisPanel.showOnlyCaseTypes)) {
            newClientType = thisPanel.showOnlyCaseTypes;
        } else {
            newClientType = empty(thisPanel.caseEmployerId) || (!empty(thisPanel.applicantId) && !empty(thisPanel.caseEmployerId) && thisPanel.applicantId != thisPanel.caseEmployerId) ? 'individual' : 'employer';
        }

        var arrThisApplicantCaseTemplates = [];
        var booFilterByLink = !empty(thisPanel.caseIdLinkedTo) || (!empty(thisPanel.applicantId) && !empty(thisPanel.caseEmployerId) && thisPanel.applicantId != thisPanel.caseEmployerId);
        Ext.each(arrApplicantsSettings.visible_case_templates, function(caseTemplate) {
            // Filter by "categories" if we want to link the current case to a specific one
            var booShow = true;
            if (booFilterByLink && !caseTemplate.case_template_can_be_linked_to_employer) {
                booShow = false;
            }

            if (booShow && caseTemplate.case_template_type_names.has(newClientType)) {
                arrThisApplicantCaseTemplates.push({
                    option_id:              caseTemplate.case_template_id,
                    option_name:            caseTemplate.case_template_name,
                    case_template_needs_ia: caseTemplate.case_template_needs_ia
                });
            }
        });

        var cookieSettingsName = 'applicant_case_default_' + newClientType;
        var thisClientMemberType = 'case';
        var newClientCaseTypeCombo = new Ext.form.ComboBox({
            width: 450,

            store: {
                xtype: 'store',
                reader: new Ext.data.JsonReader({
                    id: 'option_id'
                }, [
                    {name: 'option_id'},
                    {name: 'option_name'},
                    {name: 'case_template_needs_ia'}
                ]),
                data: arrThisApplicantCaseTemplates
            },
            emptyText: 'Please select the ' + arrApplicantsSettings.case_type_label_singular + '...',
            mode:           'local',
            displayField:   'option_name',
            valueField:     'option_id',
            triggerAction:  'all',
            forceSelection: true,
            selectOnFocus:  true,
            editable:       false,
            allowBlank:     false,
            disabled:       true,
            listeners: {
                'beforeselect': function(combo, rec) {
                    if (rec.data.option_id == combo.getValue()) {
                        return;
                    }

                    // Save this value for future
                    Ext.state.Manager.set(cookieSettingsName, rec.data.option_id);

                    var arrGroups = [];
                    IAFieldsContainer.removeAll();
                    newClientCaseSection.removeAll();
                    if (newClientType == 'employer' && rec.data.case_template_needs_ia == 'Y') {
                        newClientIAContainer.setVisible(true);

                        // Try to load the list of IAs + cases if not loaded yet
                        if (individualsStore.getCount() === 0) {
                            individualsStore.load();
                        }
                    } else {
                        newClientIAContainer.setVisible(false);

                        // Remember the type of the current client
                        this.memberType = thisClientMemberType;
                         var memberTypeField = thisPanel.find('name', 'memberType');
                         if (memberTypeField.length) {
                             memberTypeField[0].setValue(thisClientMemberType);
                         }

                        // Save selected Immigration Program
                        thisPanel.caseType = rec.data.option_id;
                        var caseTypeField = thisPanel.find('name', 'caseType');
                        if (caseTypeField.length) {
                            caseTypeField[0].setValue(rec.data.option_id);
                        }

                        // Show "Case fields/groups"
                        if (arrApplicantsSettings.case_group_templates[rec.data.option_id]) {
                            var arrCaseGroups = thisPanel.createGroupsAndFields(
                                arrApplicantsSettings.case_group_templates[rec.data.option_id],
                                'case'
                            );
                            arrGroups = arrGroups.concat(arrCaseGroups);
                        }

                        if (!arrGroups.length) {
                            arrGroups.push({
                                xtype: 'label',
                                html: "<div class='padal garytxt'>" + _("There are no fields/groups, or you don't have access to fields/groups.") + "</div>"
                            });
                        }

                        for (var i = 0; i < arrGroups.length; i++) {
                            newClientCaseSection.add(arrGroups[i]);
                        }
                        newClientCaseSection.doLayout();
                        thisPanel.owner.owner.fixParentPanelHeight();

                        thisPanel.loadApplicantDetails(false);
                    }
                }
            }
        });

        var individualsStore = new Ext.data.Store({
            xtype: 'store',
            autoLoad: false,
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/applicants/index/get-applicants-list'
            }),
            baseParams: {
                memberType: 'individual'
            },
            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'user_id',
                fields: [
                    'user_id',
                    'user_type',
                    'user_name',
                    'applicant_id',
                    'applicant_name'
                ]
            })
        });

        var newCaseIACombo = new Ext.form.ComboBox({
            width:          400,
            store:          individualsStore,
            mode:           'local',
            displayField:   'user_name',
            valueField:     'user_id',
            emptyText:      _('Please select an Existing Applicant...'),
            triggerAction:  'all',
            allowBlank:     false,
            forceSelection: true,
            selectOnFocus:  true,
            editable:       true,
            disabled:       true,
            hidden:         true
        });

        var help = String.format(
            "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
            _('Use this option, if the contact information of the individual you are about to open a case for already exists.')
        );

        var newClientIAContainer = new Ext.Container({
            hidden: true,
            cls: 'x-table-layout-cell-top-align',

            items: [
                {
                    xtype: 'box',
                    style: 'padding: 30px 0 5px 0;',
                    autoEl: {
                        tag: 'div',
                        html: _('This Immigration Program needs to be associated to an individual applicant in addition to the employer.')
                    }
                }, {
                    xtype: 'box',
                    style: 'font-weight: 500; padding: 10px 0 5px 0;',
                    autoEl: {
                        tag: 'div',
                        html: _('Add the case to:')
                    }
                }, {
                    xtype: 'radio',
                    boxLabel: _('A <b>New</b> Individual Applicant'),
                    checked: true,
                    colspan: 3,
                    name: 'adding-case-to',
                    inputValue: 'new-client',
                    style: 'margin: 0 0 2px 15px',
                    listeners: {
                        'check': function(radio, booChecked) {
                            if (booChecked) {
                                newCaseIACombo.clearInvalid();
                                newCaseIACombo.setDisabled(true);
                                newCaseIACombo.setVisible(false);
                            }
                        }
                    }
                }, {
                    xtype: 'container',
                    layout: 'column',
                    height: 38,
                    items: [
                        {
                            xtype: 'container',
                            style: 'margin-top: 7px',
                            items:  {
                                xtype: 'radio',
                                boxLabel: _('An <b>Existing</b> Applicant') + help,
                                name: 'adding-case-to',
                                inputValue: 'existing-client',
                                width: 210,
                                listeners: {
                                    'check': function (radio, booChecked) {
                                        if (booChecked) {
                                            newCaseIACombo.clearInvalid();
                                            newCaseIACombo.setDisabled(false);
                                            newCaseIACombo.setVisible(true);
                                        }
                                    }
                                }
                            }
                        },

                        newCaseIACombo
                    ]
                }, {
                    xtype: 'button',
                    cls: 'orange-btn',
                    style:   'margin-top: 10px',
                    text: _('Next') + ' ' + '<i class="las la-arrow-right"></i>',
                    handler: function () {
                        var arrRadios = thisPanel.find('name', 'adding-case-to');
                        if (!arrRadios.length) {
                            return;
                        }

                        var booCloseCurrentTab = false;
                        var radio = arrRadios[0];
                        if (radio.getValue() && radio.getRawValue() == 'new-client') {
                            thisPanel.owner.owner.openApplicantTab({
                                applicantId: 0,
                                applicantName: '',
                                memberType: 'client',
                                newClientForceTo: 'individual',
                                caseType: newClientCaseTypeCombo.getValue(),
                                caseEmployerId: thisPanel.caseEmployerId,
                                caseEmployerName: thisPanel.caseEmployerName,
                                caseIdLinkedTo: thisPanel.caseIdLinkedTo,
                                booHideNewClientType: true
                            });
                            booCloseCurrentTab = true;
                        } else {
                            if (newCaseIACombo.isValid()) {
                                var rec = newCaseIACombo.getStore().getById(newCaseIACombo.getValue());
                                if (rec) {
                                    thisPanel.owner.owner.openApplicantTab({
                                        applicantId: rec.data.user_id,
                                        applicantName: rec.data.user_name,
                                        memberType: rec.data.user_type,
                                        caseId: 0,
                                        caseName: 'Case 1',
                                        caseType: newClientCaseTypeCombo.getValue(),
                                        caseEmployerId: thisPanel.caseEmployerId,
                                        caseEmployerName: thisPanel.applicantName,
                                        caseIdLinkedTo: thisPanel.caseIdLinkedTo,
                                        showOnlyCaseTypes: 'individual'
                                    }, 'case_details');
                                    booCloseCurrentTab = true;
                                }
                            }
                        }

                        // Close current tab only we can do this
                        if (booCloseCurrentTab) {
                            setTimeout(function() {
                                var currentTab = Ext.getCmp(thisPanel.getCurrentTabId());
                                var tabPanel = currentTab.ownerCt;
                                tabPanel.remove(thisPanel.getCurrentTabId());
                            }, 50);
                        }
                    }
                }
            ]
        });

        var newClientCaseTypeContainer = new Ext.Container({
            style:  'margin: 0 5px 0;',
            hidden: !empty(thisPanel.caseType),
            cls:    'new-client-container',
            items: [
                {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        {
                            xtype: 'displayfield',
                            style: 'font-weight: 500; padding: 5px 45px 0 0;',
                            value: 'Select ' + arrApplicantsSettings.case_type_label_singular + ':'
                        }, newClientCaseTypeCombo, {
                            xtype: 'displayfield',
                            style: 'float: right; margin-top: 5px;',
                            value:  '<span class="error">*</span> <span class="garytxt14">indicates mandatory fields</span>'
                        }
                    ]
                },
                newClientIAContainer
            ]
        });

        var arrGroups = [];
        arrGroups.push(newClientCaseTypeContainer);

        var IAFieldsContainer = new Ext.Container({
            style: 'margin-top: 10px',
            items: []
        });
        arrGroups.push(IAFieldsContainer);

        var newClientCaseSection = new Ext.Container({});
        arrGroups.push(newClientCaseSection);

        thisPanel.mainForm.add(arrGroups);
        thisPanel.mainForm.doLayout();

        setTimeout(function() {
            // We need this to prevent mark combo as invalid
            newClientCaseTypeCombo.setDisabled(false);

            if (!empty(thisPanel.caseType)) {
                var index = newClientCaseTypeCombo.store.findBy(function (record){
                    return parseInt(record['data'][newClientCaseTypeCombo.valueField], 10) === parseInt(thisPanel.caseType, 10);
                });

                if (index !== -1) {
                    thisPanel.setComboValueAndFireBeforeSelectEvent(newClientCaseTypeCombo, thisPanel.caseType);
                }
            }
        }, 100);

        thisPanel.owner.owner.fixParentPanelHeight();
    },

    createNewIAAndCaseGroupsAndFields: function() {
        var arrGroups = [],
            thisPanel = this,
            newClientApplicantSectionContainer = new Ext.Container(),
            newCaseSectionContainer = new Ext.Container();
        arrGroups.push(newClientApplicantSectionContainer);
        arrGroups.push(newCaseSectionContainer);

        // We want after clicking on the 'Save' and 'Save & Add a New Case' buttons -
        // automatically DO NOT show New Case tab
        thisPanel.booOpenNewCaseTab = false;

        thisPanel.mainForm.add({
            xtype: 'container',
            items: arrGroups
        });

        newClientApplicantSectionContainer.on('render', function(container) {
            thisPanel.toggleClientGroups(container, 'individual');
        });

        newCaseSectionContainer.on('render', function() {
            // Show "Case fields/groups"
            var arrCaseGroups = [];
            if (arrApplicantsSettings.case_group_templates[thisPanel.caseType]) {
                arrCaseGroups = thisPanel.createGroupsAndFields(
                    arrApplicantsSettings.case_group_templates[thisPanel.caseType],
                    'case'
                );
            }

            if (!arrCaseGroups.length) {
                arrCaseGroups.push({
                    xtype: 'label',
                    html: "<div class='padal garytxt'>There are no fields/groups, or you don't have access to fields/groups.</div>"
                });
            }

            for (var i = 0; i < arrCaseGroups.length; i++) {
                newCaseSectionContainer.add(arrCaseGroups[i]);
            }
            newCaseSectionContainer.doLayout();
            thisPanel.owner.owner.fixParentPanelHeight();

            // Save selected Immigration Program
            var caseTypeField = thisPanel.find('name', 'caseType');
            if (caseTypeField.length) {
                caseTypeField[0].setValue(thisPanel.caseType);
            }

            thisPanel.booLoadCaseInfoOnly = true;
            thisPanel.loadApplicantDetails(false, true);
        });
    },

    createNewClientGroupsAndFields: function() {
        var thisPanel = this;

        // Use default radio value (previously used)
        var cookieSettingsName = 'applicant_new_client_default';
        var clientTypeSaved = Ext.state.Manager.get(cookieSettingsName) || 'individual';

        // Use passed param
        if (!empty(thisPanel.newClientForceTo)) {
            clientTypeSaved = thisPanel.newClientForceTo;
        }

        var arrGroups = [{
            xtype: 'container',
            html: '&nbsp;'
        }];

        var arrSupportedTypes = [];
        if (arrApplicantsSettings.search_for.length) {
            Ext.each(arrApplicantsSettings.search_for, function(oClientType) {
                arrSupportedTypes.push(oClientType.search_for_id);
            });
        }

        var individualRadioLabel = String.format(
            "<span ext:qtip='{0}' ext:qwidth='450' style='cursor: help;'>{1}</span>",
            _('This client type is for individual applicants who require your services. Selecting this option will customize the form accordingly.'),
            _('Individual')
        );

        var employerRadioLabel = String.format(
            "<span ext:qtip='{0}' ext:qwidth='450' style='cursor: help;'>{1}</span>",
            _('This client type is for a company who is an employer of the applicant who requires your services. Selecting this option will customize the form accordingly.'),
            _('Employer')
        );

        var newClientTypeContainer = new Ext.Container({
            xtype: 'container',
            style: 'margin: 0 5px 10px 0;',
            cls: 'new-client-container',
            layout: 'column',
            items: [
                {
                    xtype: 'displayfield',
                    style: 'font-weight: 500; padding-top: 2px; padding-right: 10px; margin-top: 0',
                    value: _('Client type:')
                }, {
                    xtype: 'container',
                    style: 'margin-right: 17px;',

                    items: {
                        xtype: 'radio',
                        boxLabel: individualRadioLabel,
                        name: 'new-client-type',
                        inputValue: 'individual',
                        hidden: !arrSupportedTypes.has('individual'),
                        listeners: {
                            'render': function (radio) {
                                if (clientTypeSaved == radio.inputValue) {
                                    radio.setValue(true);
                                }
                            },

                            'check': function (radio, booChecked) {
                                if (booChecked) {
                                    Ext.state.Manager.set(cookieSettingsName, radio.inputValue);
                                    thisPanel.toggleClientGroups(newClientApplicantSectionContainer, radio.inputValue);
                                }
                            }
                        }
                    }


                }, {
                    xtype: 'radio',
                    boxLabel: employerRadioLabel,
                    name: 'new-client-type',
                    inputValue: 'employer',
                    hidden: !arrSupportedTypes.has('employer'),
                    listeners: {
                        'render': function(radio) {
                            if (clientTypeSaved == radio.inputValue) {
                                radio.setValue(true);
                            }
                        },

                        'check': function(radio, booChecked) {
                            if (booChecked) {
                                Ext.state.Manager.set(cookieSettingsName, radio.inputValue);
                                thisPanel.toggleClientGroups(newClientApplicantSectionContainer, radio.inputValue);
                            }
                        }
                    }
                }
            ]
        });
        newClientTypeContainer.on('render', function(container) {
            if (thisPanel.booHideNewClientType || (!arrSupportedTypes.has('individual') || !arrSupportedTypes.has('employer'))) {
                setTimeout(function(){
                    container.setVisible(false);
                    thisPanel.owner.owner.fixParentPanelHeight();
                }, 50);
            }
        });


        arrGroups.push(newClientTypeContainer);

        var newClientApplicantSectionContainer = new Ext.Container();
        arrGroups.push(newClientApplicantSectionContainer);

        // We want after clicking on the 'Save' and 'Save & Add a New Case' buttons -
        // automatically show New Case tab
        thisPanel.booOpenNewCaseTab = true;

        thisPanel.mainForm.add({
            xtype: 'container',
            layout: 'table',
            cls: 'x-table-layout-cell-top-align',
            layoutConfig: {
                tableAttrs: {
                    style: {
                        width: '100%'
                    }
                },
                columns: 2
            },
            items: [
                {
                    xtype: 'container',
                    items: arrGroups
                }
            ]
        });
    },

    toggleNewClientFields: function(newClientApplicantSectionContainer, newClientType) {
        var thisPanel = this;
        newClientApplicantSectionContainer.removeAll();

        var arrGroups = [];

        // Generate 'Employer' link
        if (!empty(thisPanel.caseEmployerId) && thisPanel.caseEmployerId != thisPanel.applicantId) {
            arrGroups.push({
                xtype: 'panel',
                html: '<div style="float: right;">' +
                    '<span class="error">*</span> <span class="garytxt14">indicates mandatory fields</span>' +
                    '</div>'
            });
        }

        // Generate fields
        arrGroups.push(thisPanel.createGroupsAndFields(arrApplicantsSettings.groups_and_fields[newClientType][0]['fields'], newClientType));

        newClientApplicantSectionContainer.add(arrGroups);
        newClientApplicantSectionContainer.doLayout();

        thisPanel.owner.owner.fixParentPanelHeight();

        // Automatically preselect/fill special fields
        thisPanel.fillDefaultValues(newClientType, 0);
    },

    fillDefaultValues: function(newClientType, applicantTypeId) {
        var thisPanel = this;
        var arrDefaultValues = [
            {
                field_unique_id: 'disable_login',
                field_text_value: 'Enabled'
            }, {
                field_unique_id: 'status',
                field_text_value: 'Active'
            }, {
                field_unique_id: 'status_simple',
                field_text_value: 'Active'
            }
        ];

        if (arrApplicantsSettings.booRememberDefaultFieldsSetting) {
            arrDefaultValues.push({
                field_unique_id: 'office',
                field_text_value: Ext.state.Manager.get('agents_office')
            });
        }

        var allFields = [];
        if (empty(applicantTypeId)) {
            allFields = arrApplicantsSettings.groups_and_fields[newClientType][0]['fields'];
        } else {
            Ext.each(arrApplicantsSettings.groups_and_fields[newClientType], function(oData){
                if (oData['type_id'] == applicantTypeId) {
                    allFields = oData['fields'];
                }
            });
        }


        Ext.each(arrDefaultValues, function(oDefaultField){
            Ext.each(allFields, function(oGroup){
                Ext.each(oGroup['fields'], function(oFieldInfo){
                    if (oFieldInfo['field_unique_id'] == oDefaultField['field_unique_id']) {
                        var fieldName = 'field_' + newClientType + '_' + oGroup.group_id + '_' + oFieldInfo.field_id,
                            fieldValue = oDefaultField.field_text_value;
                        var arrFormFields = thisPanel.find('name', fieldName + '[]');
                        if (arrFormFields.length) {
                            if(arrApplicantsSettings.options[newClientType][oFieldInfo.field_id]) {
                                var arrData = arrApplicantsSettings.options[newClientType][oFieldInfo.field_id];
                                Ext.each(arrData, function (oOption) {
                                    if (oOption.option_name == fieldValue) {
                                        fieldValue = oOption.option_id;
                                    }
                                });
                            }
                            thisPanel.fillFieldData(arrFormFields[0], fieldValue, oFieldInfo.field_id, 0);
                        }
                    }
                });
            });
        });
    },

    createApplicantGroupsAndFields: function() {
        var thisPanel = this;
        var arrGroups = thisPanel.createGroupsAndFields(
            arrApplicantsSettings.groups_and_fields[thisPanel.memberType][0]['fields'],
            thisPanel.memberType
        );
        thisPanel.mainForm.add(arrGroups);
    },

    createContactGroupsAndFieldsByType: function(contactTypeId) {
        var thisPanel = this;

        var arrGroups = [];
        var arrFields = [];
        Ext.each(arrApplicantsSettings.groups_and_fields[thisPanel.memberType], function(oData){
            if (oData['type_id'] == contactTypeId) {
                arrFields = oData['fields'];
            }
        });

        if (arrFields.length) {
            arrGroups = thisPanel.createGroupsAndFields(
                arrFields,
                thisPanel.memberType
            );
        }

        if (!arrGroups.length) {
            arrGroups.push({
                xtype: 'label',
                html: "<div class='padal garytxt'>There are no fields/groups, or you don't have access to fields/groups.</div>"
            });
        }

        var casePanel = thisPanel.getUniqueField('contact_fields_container');
        if (casePanel) {
            casePanel.removeAll();
            for (var i = 0; i < arrGroups.length; i++) {
                casePanel.add(arrGroups[i]);

            }
            casePanel.doLayout();
        }
        thisPanel.owner.owner.fixParentPanelHeight();

        if (empty(thisPanel.applicantId)) {
            // Automatically preselect/fill special fields
            thisPanel.fillDefaultValues('contact', contactTypeId);
        }
    },

    createContactGroupsAndFields: function() {
        var thisPanel = this;
        var arrGroups = [];

        var applicantTypeField = thisPanel.find('name', 'applicantType')[0];
        var applicantTypeValue = applicantTypeField.getValue();

        // Check if this section was already generated - destroy it
        var mainContainer = thisPanel.getUniqueField('contact_section_main_container');
        if (mainContainer) {
            thisPanel.mainForm.remove(mainContainer);
        }

        // Collect data we need to use in the combo
        var arrTypes = [];
        var currentApplicantType = '-';
        Ext.each(arrApplicantsSettings.groups_and_fields[thisPanel.memberType], function(oData){
            if (oData['type_id'] == applicantTypeValue) {
                currentApplicantType =  oData['type_name'];
            }

            arrTypes.push({
                'option_id':   oData['type_id'],
                'option_name': oData['type_name']
            });
        });

        // Header - show combobox for NEW Contact OR just a label for already created Contact
        var applicantTypeCombo = new Ext.form.ComboBox({
            width:  450,
            hidden: !empty(applicantTypeValue),
            disabled: true,
            store: {
                xtype: 'store',
                reader: new Ext.data.JsonReader({
                    id: 'option_id'
                }, [
                    {name: 'option_id'},
                    {name: 'option_name'}
                ]),
                data: arrTypes
            },
            mode:           'local',
            displayField:   'option_name',
            valueField:     'option_id',
            triggerAction:  'all',
            emptyText:      'Please select a type...',
            allowBlank:     false,
            forceSelection: true,
            selectOnFocus:  true,
            editable:       true,
            listeners: {
                'beforeselect': function(combo, rec) {
                    applicantTypeValue = rec.data.option_id;
                    applicantTypeField.setValue(applicantTypeValue);

                    thisPanel.createContactGroupsAndFieldsByType(applicantTypeValue);
                }
            }
        });

        arrGroups.push({
            xtype:  'container',
            style:  'margin: 0 5px 10px; font-size: 12px;',
            cls:    empty(applicantTypeValue) ? 'new-client-container' : '',
            layout: 'column',
            items: [
                {
                    xtype: 'displayfield',
                    style: 'color: #000; padding: 5px 15px 0 8px;',
                    cls: 'x-form-field-value',
                    value: empty(applicantTypeValue) ? 'Select Contact Type:' : 'Contact Type:'
                }, {
                    xtype:  'displayfield',
                    style:  'padding: 5px 15px 0 0;',
                    hidden: empty(applicantTypeValue),
                    value:  currentApplicantType
                }, applicantTypeCombo
            ]
        });

        // Here will be placed fields and groups
        arrGroups.push({
            xtype: 'container',
            uniqueFieldId: 'contact_fields_container',
            items: []
        });

        thisPanel.mainForm.add({
            xtype: 'container',
            uniqueFieldId: 'contact_section_main_container',
            items: arrGroups
        });
        thisPanel.mainForm.doLayout();


        if (!empty(applicantTypeValue)) {
            thisPanel.createContactGroupsAndFieldsByType(parseInt(applicantTypeValue, 10));
        } else {
            // We need this to prevent mark combo as invalid
            setTimeout(function() {applicantTypeCombo.setDisabled(false);}, 100);
        }
    },

    addDependentRow: function(group, arrDependentData) {
        var thisPanel = this;
        var container = thisPanel.getUniqueField('dependents_container');
        var groupAccess = group.group_access;

        if (container) {
            var arrFields = [];
            //Without dependent_id
            var totalFieldsCount = arrApplicantsSettings.fields['dependants'].length - 1;
            var dependentIdField;

            var columnsCount = empty(group.group_cols_count) ? 4 : parseInt(group.group_cols_count, 10);

            if (totalFieldsCount) {
                var totalCellsUsed = 0;

                for (var j = 0; j < totalFieldsCount + 1; j++) {
                    var field = arrApplicantsSettings.fields['dependants'][j];
                    var newField = Ext.apply({}, field);
                    newField.field_disabled = field.field_disabled == 'Y' || groupAccess != 'F';
                    newField.field_container_width = 'auto';
                    newField.field_container_style = 'padding-right: 15px;';
                    newField.hide_field_label = false;
                    newField.field_access = groupAccess;

                    newField.field_width = '100%';
                    newField.field_container_width = 'auto';

                    if (newField.field_use_full_row) {
                        newField.field_width = '100%';
                        newField.field_container_width = 'auto';
                        newField.field_container_colspan = columnsCount;
                        totalCellsUsed += columnsCount;
                    } else {
                        totalCellsUsed++;
                    }

                    if (arrApplicantsSettings.fields['dependants'][j]['field_id'] === 'dependent_id') {
                        dependentIdField = thisPanel.generateField('dependants', newField, 'case', true, container.items.getCount(), [], groupAccess);
                        continue;
                    }

                    arrFields.push(thisPanel.generateField('dependants', newField, 'case', true, container.items.getCount(), arrDependentData, groupAccess));
                }
            }

            if (arrFields.length) {
                // Remove row button
                arrFields.push(dependentIdField);

                var newGroup = new Ext.form.FieldSet({
                    cls: 'applicants-profile-fieldset-cloned applicants-profile-fieldset-cloned-border',
                    title: 'Delete',
                    titleCollapse: false,
                    collapsible: group.group_access == 'F',
                    autoHeight: true,
                    items: [
                        {
                            xtype: 'container',
                            style: 'display: grid; grid-template-columns: ' + (' 1fr').repeat(columnsCount),
                            items: arrFields
                        }
                    ],
                    listeners: {
                        'beforecollapse': function() {
                            if (empty(arrDependentData)) {
                                newGroup.ownerCt.remove(newGroup);

                                thisPanel.owner.owner.fixParentPanelHeight();
                                thisPanel.toggleAddDependantButton();
                            } else {
                                var msg = 'Are you sure you want to delete this dependant and all the related information e.g. forms?';
                                Ext.Msg.confirm('Please confirm', msg, function (btn) {
                                    if (btn == 'yes') {
                                        newGroup.ownerCt.remove(newGroup);

                                        thisPanel.owner.owner.fixParentPanelHeight();
                                        thisPanel.toggleAddDependantButton();
                                        thisPanel.booIsDirty = true;
                                    }
                                });
                            }

                            return false;
                        }
                    }
                });

                container.add(newGroup);
                container.doLayout();
                thisPanel.owner.owner.fixParentPanelHeight();

                thisPanel.toggleAddDependantButton();
            }
        }
    },

    toggleAddDependantButton: function () {
        var container = this.getUniqueField('dependents_container');
        var button = this.getUniqueField('dependents_container_add_button');

        if (container && button) {
            button.setDisabled(container.items.getCount() >= arrApplicantsSettings.max_dependants_count || button.group_access != 'F');
        }
    },

    showThirdPartyVisaDialog: function (booHasEditAccess, booDependents, dependentId, oFieldGroup) {
        if (empty(this.caseId)) {
            Ext.simpleConfirmation.warning('Please save the profile and try again.');
            return;
        }

        if (booDependents && empty(dependentId)) {
            oFieldGroup.resetValueIfNoRecordsCreated(0);
            Ext.simpleConfirmation.warning('Please save changes and try again.');
            return;
        }


        var wnd = new ApplicantsVisaSurveyDialog({
            booHasEditAccess: booHasEditAccess,
            caseId: this.caseId,
            dependentId: dependentId,
            oFieldGroup: oFieldGroup
        });

        wnd.show();
        wnd.center();
    },

    toggleConditionalField: function (formMemberType, oField, booNowFieldVisible, booDoNotShowIfHidden) {
        var thisPanel = this;
        var booWasFieldVisible = oField.ownerCt.isVisible();

        if (booDoNotShowIfHidden && booNowFieldVisible && !booWasFieldVisible) {
            return;
        }

        oField.ownerCt.setVisible(booNowFieldVisible);

        if (!booNowFieldVisible && booWasFieldVisible && !empty(arrApplicantsSettings.conditional_fields[formMemberType]) && arrApplicantsSettings.conditional_fields[formMemberType][thisPanel.caseType]) {
            var arrConditionalFields = arrApplicantsSettings.conditional_fields[formMemberType][thisPanel.caseType][oField.realFieldId];
            if (!empty(arrConditionalFields)) {
                for (var k in arrConditionalFields) {
                    // Toggle fields
                    if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                        for (var j in arrConditionalFields[k]['hide_fields']) {
                            if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j)) {
                                var arrFieldsToHide = thisPanel.find('realFieldId', parseInt(arrConditionalFields[k]['hide_fields'][j], 10));
                                if (!empty(arrFieldsToHide) && arrFieldsToHide.length) {
                                    for (var i = 0; i < arrFieldsToHide.length; i++) {
                                        thisPanel.toggleConditionalField(formMemberType, arrFieldsToHide[i], booNowFieldVisible);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        var value = booNowFieldVisible ? oField.getValue() : '';
        var itemXType = oField.getXType();
        // Run with a small delay - because maybe this field will toggle other fields too (that were toggled by this field's parent field)
        setTimeout(function () {
            switch (itemXType) {
                case 'checkbox':
                    oField.fireEvent('check', oField, value);
                    break;

                case 'radio':
                case 'radiogroup':
                    oField.fireEvent('change', oField, value);
                    break;

                case 'multiple_combo':
                case 'lovcombo':
                case 'combo':
                    if (booNowFieldVisible) {
                        if (!empty(value)) {
                            thisPanel.setComboValueAndFireBeforeSelectEvent(oField, value);
                        } else {
                            oField.fireEvent('select', oField);

                            if (itemXType != 'lovcombo') {
                                // In some cases blur is used too
                                oField.fireEvent('blur', oField);
                            }
                        }
                    } else {
                        oField.fireEvent('change', oField, value);
                    }
                    break;

                default:
                    break;
            }
        }, 50);
    },

    toggleConditionalFieldsAndGroups: function(formMemberType, arrConditionalFields, arrCheckedOptions) {
        var thisPanel     = this;
        var arrHideFields = [];
        var arrHideGroups = [];
        for (var i = 0; i < arrCheckedOptions.length; i++) {
            for (var k in arrConditionalFields) {
                if (k != arrCheckedOptions[i]) {
                    continue;
                }

                // Collect fields that should be hidden
                if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                    for (var j in arrConditionalFields[k]['hide_fields']) {
                        if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j) && !arrHideFields.has(arrConditionalFields[k]['hide_fields'][j])) {
                            arrHideFields.push(arrConditionalFields[k]['hide_fields'][j]);
                        }
                    }
                }

                // Collect groups that should be hidden
                if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_groups')) {
                    for (j in arrConditionalFields[k]['hide_groups']) {
                        if (arrConditionalFields[k]['hide_groups'].hasOwnProperty(j) && !arrHideGroups.has(arrConditionalFields[k]['hide_groups'][j])) {
                            arrHideGroups.push(arrConditionalFields[k]['hide_groups'][j]);
                        }
                    }
                }
            }
        }

        // Hide fields and groups
        if (arrHideFields.length) {
            for (var n = 0; n < arrHideFields.length; n++) {
                var arrFieldsToHide = thisPanel.find('realFieldId', parseInt(arrHideFields[n], 10));
                if (!empty(arrFieldsToHide) && arrFieldsToHide.length) {
                    for (i = 0; i < arrFieldsToHide.length; i++) {
                        thisPanel.toggleConditionalField(formMemberType, arrFieldsToHide[i], false);
                    }
                }
            }
        }

        if (arrHideGroups.length) {
            for (n = 0; n < arrHideGroups.length; n++) {
                var arrGroupsToHide = thisPanel.find('realGroupId', parseInt(arrHideGroups[n], 10));
                if (!empty(arrGroupsToHide) && arrGroupsToHide.length) {
                    for (i = 0; i < arrGroupsToHide.length; i++) {
                        arrGroupsToHide[i].ownerCt.ownerCt.setVisible(false);
                    }
                }
            }
        }

        // Show fields and groups that were hidden previously and are not hidden right now
        for (k in arrConditionalFields) {
            if (arrConditionalFields.hasOwnProperty(k)) {
                if (arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                    for (j in arrConditionalFields[k]['hide_fields']) {
                        if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j) && !arrHideFields.has(arrConditionalFields[k]['hide_fields'][j])) {
                            var arrFieldsToShow = thisPanel.find('realFieldId', parseInt(arrConditionalFields[k]['hide_fields'][j], 10));
                            if (!empty(arrFieldsToShow) && arrFieldsToShow.length) {
                                for (i = 0; i < arrFieldsToShow.length; i++) {
                                    thisPanel.toggleConditionalField(formMemberType, arrFieldsToShow[i], true);
                                }
                            }
                        }
                    }
                }

                if (arrConditionalFields[k].hasOwnProperty('hide_groups')) {
                    for (j in arrConditionalFields[k]['hide_groups']) {
                        if (arrConditionalFields[k]['hide_groups'].hasOwnProperty(j) && !arrHideGroups.has(arrConditionalFields[k]['hide_groups'][j])) {
                            var arrGroupsToShow = thisPanel.find('realGroupId', parseInt(arrConditionalFields[k]['hide_groups'][j], 10));
                            if (!empty(arrGroupsToShow) && arrGroupsToShow.length) {
                                for (i = 0; i < arrGroupsToShow.length; i++) {
                                    arrGroupsToShow[i].ownerCt.ownerCt.setVisible(true);
                                }
                            }
                        }
                    }
                }
            }
        }
    },

    generateField: function(groupId, field, formMemberType, booSetDefaultValue, line, arrDependentData, groupAccess) {
        var thisPanel           = this;
        var fieldWidth          = field.field_width ? field.field_width : 200;
        var fieldHeight         = parseInt(field.field_custom_height, 10) ? parseInt(field.field_custom_height, 10) : 0;
        var booRequired         = field.field_required == 'Y';
        var label               = field.field_name + (booRequired ? '<span class="error" style="padding-left: 5px">*</span>' : '');
        var booTinyWidth        = $(window).width() <= 1024;
        var booHasEditAccess    = field.field_access == 'F';
        var maxLength           = field.field_maxlength ? field.field_maxlength : 0;
        var booIsDependentGroup = groupId === 'dependants';

        // General details

        // Apply specific classes
        var cls = '';
        switch (field.field_unique_id) {
            case 'username':
                cls = 'username-identifier';
                break;

            case 'password':
                cls = 'password-identifier';
                break;

            case 'third_country_visa':
                if (field.field_type == 'combo') {
                    label += String.format(
                        ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value" style="display: none;">{0}</a>',
                        booHasEditAccess ? _('(add/view visa records)') : _('(view visa records)')
                    );
                }
                break;

            case 'case_type':
                var booVisibleChangeCaseTypeLink = false;
                if (booHasEditAccess && thisPanel.booShowChangeCaseTypeLink && !thisPanel.booChangedCaseType && !empty(thisPanel.caseId) && !empty(thisPanel.caseType)) {
                    booVisibleChangeCaseTypeLink = true;
                }

                label += String.format(
                    ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value" style="{0}">{1}</a>',
                    booVisibleChangeCaseTypeLink ? '' : 'display: none',
                    _('Change')
                );
                break;

            default:
        }

        var oFieldDetails = {
            cls: cls,
            xtype: booHasEditAccess ? 'textfield' : 'displayfield',
            name: 'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]',
            hiddenName: 'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]',
            fieldUniqueName: field.field_unique_id,
            labelStyle: thisPanel.defaultFieldLabelStyle,
            disabled: field.field_disabled || !booHasEditAccess,
            fieldLabel: label,
            realFieldLabel: field.field_name,
            realFieldId: parseInt(field.field_id, 10),
            hideLabel: field.hide_field_label,
            realFieldType: field.field_type,
            labelSeparator: '',
            anchor: '100%',
            allowBlank: !booRequired
        };

        // Genereate the id only for this specific field
        // Should not be generated if that field will be clonned (e.g. Authorized Contacts or Dependents)
        if (field.field_unique_id == 'nomination_ceiling' && !empty(thisPanel.caseId)) {
            oFieldDetails.id = Ext.id();
        }

        if (field.field_unique_id == 'username' || field.field_unique_id == 'password') {
            oFieldDetails.defaultAutoCreate = {
                tag:          'input',
                type:         'text',
                size:         '20',
                autocomplete: 'new-passwords',
                'readonly':   'readonly'
            };

            oFieldDetails.listeners = {
                afterrender: {
                    buffer: 500,
                    fn: function() {
                        $('#' + this.id).attr('readonly', null);
                    }
                }
            };
        }

        // Don't show by default
        if (booIsDependentGroup && field.field_unique_id === 'spouse_name') {
            oFieldDetails.hidden = true;
        }

        if (fieldWidth == 'auto') {
            oFieldDetails.autoWidth = true;
        } else {
            oFieldDetails.width = fieldWidth;
        }

        if (!empty(maxLength)) {
            oFieldDetails.maxLength = maxLength;
        }

        if (booSetDefaultValue && field.hasOwnProperty('field_default_value')) {
            if (field.field_type == 'checkbox') {
                oFieldDetails.checked = field.field_default_value;
            } else {
                oFieldDetails.value = field.field_default_value;
            }
        }

        var booIsCaseStatusField = false;
        if (field.field_type === 'case_status') {
            booIsCaseStatusField = true;
            field.field_type = arrApplicantsSettings.case_status_field_multiselect ? 'multiple_combo' : 'combo';
        }

        var booIsVACField = false;
        if (field.field_type === 'visa_office' || field.field_type === 'immigration_office') {
            booIsVACField = true;
            field.field_type = 'combo';
        }

        var booCanEditOptions = false;
        if (field.field_can_edit_in_gui) {
            // Check role access rights if we can show 'Edit Options' link
            switch (formMemberType) {
                case 'case':
                    booCanEditOptions = arrApplicantsSettings.can_edit_case_fields;
                    break;

                case 'individual':
                    booCanEditOptions = arrApplicantsSettings.can_edit_individuals_fields;
                    break;

                case 'employer':
                    booCanEditOptions = arrApplicantsSettings.can_edit_employer_fields;
                    break;

                case 'contact':
                    booCanEditOptions = arrApplicantsSettings.can_edit_contact_fields;
                    break;

                default:
                    break;
            }
        }

        // Here we'll collect all events that will be applied for the current field
        // Can be several the same events for 1 field
        var arrFieldListeners = [];

        // Specific details
        var oFieldNewDetails = {};
        switch (field.field_type) {
            case 'email':
                if (allowedPages.has('email')) {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        vtype: 'email',
                        fieldLabel: '<a href="#" onclick="return false;" class="blulinkunm x-form-field-value email-field"><i class="lar la-envelope"></i>' + label + '</a>'
                    };

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function () {
                            var thisField = this;
                            var label = this.getEl().up('div.x-form-item').child('label', true);
                            Ext.get(label).on('mousedown', function () {
                                var options = {
                                    member_id: empty(thisPanel.caseId) ? 0 : thisPanel.caseId,
                                    parentMemberId: thisPanel.applicantId,
                                    booHideSendAndSaveProspect: true,
                                    booNewEmail: true,
                                    emailTo: thisField.getValue(),
                                    booProspect: false,
                                    booCreateFromProfile: true
                                };

                                show_email_dialog(options);
                            });
                        }
                    });
                } else {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        vtype: 'email'
                    };
                }
                break;

            case 'office_multi':
                var officeLabel = String.format(
                    "<span ext:qtip='{0}' ext:qwidth='450' style='cursor: help;'>{1}</span>",
                    _('If you have more than one office, it’s important to select the office to which the client belongs This will allow you to manage the access rights to that particular client’s file. For example, if you work with an overseas agent, you can allow the agent to only see the client files that are registered to that particular overseas office.'),
                    label
                );

                oFieldNewDetails = {
                    xtype: 'lovcombo',
                    fieldLabel: officeLabel,

                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'}
                        ]),
                        data: arrApplicantsSettings.options['general']['office']
                    },
                    triggerAction: 'all',
                    valueField: 'option_id',
                    displayField: 'option_name',
                    mode: 'local',
                    useSelectAll: false,
                    allowBlank: false
                };

                if (field.field_unique_id == 'office') {
                    arrFieldListeners.push({
                        eventName: 'select',
                        eventMethod: function (combo) {
                            Ext.state.Manager.set('agents_office', combo.getValue());
                        }
                    });
                }
                break;

            case 'multiple_combo':
                var arrMultipleComboData = [];

                if (booIsCaseStatusField) {
                    arrMultipleComboData = thisPanel.getCaseStatusesByCaseSettings(thisPanel.caseType, thisPanel.caseCategory);

                    label += String.format(
                        ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value view-file-status-history" {0}>' + _('Change History') + '</a>',
                        empty(thisPanel.caseId) ? 'style="display: none"' : ''
                    );
                } else if (field.hasOwnProperty('field_options')) {
                    arrMultipleComboData = field.field_options;
                } else if(arrApplicantsSettings.options[formMemberType][field.field_id]) {
                    arrMultipleComboData = arrApplicantsSettings.options[formMemberType][field.field_id];
                }

                if (booCanEditOptions) {
                    label += ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value view-edit-options">' + _('Edit Options') + '</a>';
                }

                var arrMultipleComboDataWithoutDeleted = [];
                Ext.each(arrMultipleComboData, function (r) {
                    if (typeof r.option_deleted === 'undefined' || !r.option_deleted) {
                        arrMultipleComboDataWithoutDeleted.push(r);
                    }
                });

                oFieldNewDetails = {
                    xtype: 'lovcombo',
                    fieldLabel: label,
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'},
                            {name: 'option_deleted'}
                        ]),
                        data: arrMultipleComboDataWithoutDeleted,
                        all_data: arrMultipleComboData,
                        // Allow filter 'any match values'
                        filter: function(filters, value) {
                            var escapeRegexRe = /([-.*+?\^${}()|\[\]\/\\])/g;
                            Ext.data.Store.prototype.filter.apply(this, [
                                filters,
                                value ? new RegExp(value.replace(escapeRegexRe, "\\$1"), 'i') : value
                            ]);
                        }

                    },
                    triggerAction: 'all',
                    valueField: 'option_id',
                    displayField: 'option_name',
                    mode: 'local',
                    useSelectAll: false,
                    anchor: booTinyWidth ? '95%' : '100%',

                    // A custom behaviour if one option is selected, and we select/click the same or another option
                    customBehaviourForOneOption: booIsCaseStatusField,

                    // A custom template, so we can mark "deleted" options
                    tpl: '<tpl for=".">'
                        + '<div class="x-combo-list-item">'
                        + '<img src="' + Ext.BLANK_IMAGE_URL + '" '
                        + 'class="ux-lovcombo-icon ux-lovcombo-icon-'
                        + '{[values.checked?"checked":"unchecked"' + ']}">'
                        + '<tpl if="option_deleted == true">'
                        + '<div class="ux-lovcombo-item-text" style="text-decoration: line-through red;">{' + ('option_name') + ':htmlEncode}</div>'
                        + '</tpl>'
                        + '<tpl if="option_deleted != true">'
                        + '<div class="ux-lovcombo-item-text">{' + ('option_name') + ':htmlEncode}</div>'
                        + '</tpl>'
                        + '</div>'
                        + '</tpl>'
                };

                if (booIsCaseStatusField) {
                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).hasClass('view-file-status-history')) {
                                    var wnd = new ApplicantsProfileFileStatusHistoryDialog({
                                        caseId: thisPanel.caseId
                                    }, thisPanel);
                                    wnd.show();
                                }
                            });
                        }
                    });
                }

                if (booCanEditOptions) {
                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).hasClass('view-edit-options')) {
                                    var order = 0;
                                    var arrItems = [];
                                    combo.store.each(function (rec) {
                                        arrItems.push({
                                            option_id: rec.data['option_id'],
                                            option_name: rec.data['option_name'],
                                            option_order: order++,
                                            option_deleted: rec.data['option_deleted']
                                        });
                                    });

                                    var wnd = new ApplicantsProfileEditOptionsDialog({
                                        booCaseField: thisPanel.memberType == 'case',
                                        fieldId: combo.realFieldId,
                                        options: arrItems
                                    });
                                    wnd.show();
                                }
                            });
                        }
                    });
                }

                arrFieldListeners.push({
                    eventName: 'afterrender',
                    eventMethod: function (combo) {
                        new Ext.ToolTip({
                            target: combo.getEl(),
                            autoWidth: true,
                            cls: 'not-bold-header',
                            header: true,
                            trackMouse: true,
                            listeners: {
                                beforeshow: function (tooltip) {
                                    var val = combo.getRawValue();
                                    if (!empty(val)) {
                                        tooltip.setTitle(val);
                                    } else {
                                        // Don't show tooltip if value is empty
                                        setTimeout(function () {
                                            tooltip.hide();
                                        }, 1);
                                    }
                                }
                            }
                        });
                    }
                });
                break;

            case 'country':
            case 'combo':
            case 'office':
            case 'agents':
            case 'assigned_to':
            case 'staff_responsible_rma':
            case 'list_of_occupations':
            case 'active_users':
            case 'employee':
            case 'employer_contacts':
            case 'employer_engagements':
            case 'employer_legal_entities':
            case 'employer_locations':
            case 'employer_third_party_representatives':
            case 'categories':
            case 'case_type':
                var arrData = [];
                switch (field.field_type) {
                    case 'office':
                    case 'agents':
                    case 'list_of_occupations':
                    case 'employee':
                    case 'country':
                        arrData = arrApplicantsSettings.options['general'][field.field_type];
                        break;

                    case 'categories':
                        arrData = thisPanel.getCategoriesByCaseType(thisPanel.caseType);
                        break;

                    case 'case_type':
                        arrData = thisPanel.getCaseTemplatesForCurrentApplicant();
                        break;

                    case 'assigned_to':
                    case 'staff_responsible_rma':
                    case 'active_users':
                        Ext.each(arrApplicantsSettings.options['general'][field.field_type], function(oData){
                            if (parseInt(oData['status']) == 1) {
                                arrData.push({
                                    option_id: oData['option_id'],
                                    option_name: oData['option_name']
                                });
                            }
                        });
                        break;

                    default:
                        if (booIsCaseStatusField) {
                            arrData = thisPanel.getCaseStatusesByCaseSettings(thisPanel.caseType, thisPanel.caseCategory);

                            label += String.format(
                                ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value view-file-status-history" {0}>' + _('Change History') + '</a>',
                                empty(thisPanel.caseId) ? 'style="display: none"' : ''
                            );
                        } else if (booIsVACField) {
                            arrData = arrApplicantsSettings.options['general']['visa_office'];

                            label += ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value view-vac-website">' + _('Website') + '</a>';
                        } else if (field.hasOwnProperty('field_options')) {
                            arrData = field.field_options;
                        } else if (arrApplicantsSettings.options[formMemberType][field.field_id]) {
                            arrData = arrApplicantsSettings.options[formMemberType][field.field_id];
                        }
                        break;
                }

                if (booCanEditOptions) {
                    label += ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value view-edit-options">' + _('Edit Options') + '</a>';
                }

                var arrComboDataWithoutDeleted = [];
                Ext.each(arrData, function (r) {
                    if (typeof r.option_deleted === 'undefined' || !r.option_deleted) {
                        arrComboDataWithoutDeleted.push(r);
                    }
                });

                var arrOptionFields = [];
                if (booIsDependentGroup) {
                    arrOptionFields = [
                        {name: 'option_id'},
                        {name: 'option_name'},
                        {name: 'option_deleted'},
                        {name: 'option_max_count'},
                        {name: 'option_max_count_error'}
                    ];
                } else {
                    arrOptionFields = [
                        {name: 'option_id'},
                        {name: 'option_name'},
                        {name: 'option_link'},
                        {name: 'option_deleted'}
                    ];
                }

                oFieldNewDetails = {
                    xtype: 'combo',
                    comboType: field.field_type,
                    fieldLabel: label,
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, arrOptionFields),
                        data: arrComboDataWithoutDeleted,
                        all_data: arrData,

                        // Allow filter 'any match values'
                        filter: function(filters, value) {
                            var escapeRegexRe = /([-.*+?\^${}()|\[\]\/\\])/g;
                            Ext.data.Store.prototype.filter.apply(this, [
                                filters,
                                value ? new RegExp(value.replace(escapeRegexRe, "\\$1"), 'i') : value
                            ]);
                        }
                    },

                    // A custom template, so we can mark "deleted" options
                    tpl: new Ext.XTemplate(
                        '<tpl for=".">',
                        '<tpl if="option_deleted == true">',
                        '<h1 class="x-combo-list-item" style="text-decoration: line-through red;">{option_name}</h1>',
                        '</tpl>',

                        '<tpl if="option_deleted != true">',
                        '<div class="x-combo-list-item">{option_name}</div>',
                        '</tpl>',
                        '</tpl>'
                    ),

                    mode: 'local',
                    displayField: 'option_name',
                    valueField: 'option_id',
                    triggerAction: 'all',
                    forceSelection: true,
                    selectOnFocus: true,
                    editable: true,
                    anchor: booTinyWidth ? '95%' : '100%'
                };

                if (booIsCaseStatusField) {
                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).hasClass('view-file-status-history')) {
                                    var wnd = new ApplicantsProfileFileStatusHistoryDialog({
                                        caseId: thisPanel.caseId
                                    }, thisPanel);
                                    wnd.show();
                                }
                            });
                        }
                    });
                }

                if (booIsVACField) {
                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).hasClass('view-vac-website')) {
                                    var index = combo.store.find(combo.valueField, combo.getValue());
                                    var record = combo.store.getAt(index);
                                    if (record) {
                                        if (!empty(record.data.option_link)) {
                                            Ext.ux.Popup.show(record.data.option_link, true, '', _('Redirecting...'));
                                        } else {
                                            Ext.simpleConfirmation.msg('Info', _('No Website for this VAC/Office is entered.'));
                                        }
                                    } else {
                                        Ext.simpleConfirmation.msg('Info', _('Please select an office first.'));
                                    }
                                }
                            });
                        }
                    });
                }

                if (booCanEditOptions) {
                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).hasClass('view-edit-options')) {
                                    var order = 0;
                                    var arrItems = [];
                                    combo.store.each(function (rec) {
                                        arrItems.push({
                                            option_id: rec.data['option_id'],
                                            option_name: rec.data['option_name'],
                                            option_order: order++,
                                            option_deleted: rec.data['option_deleted']
                                        });
                                    });

                                    var wnd = new ApplicantsProfileEditOptionsDialog({
                                        booCaseField: thisPanel.memberType == 'case',
                                        fieldId: combo.realFieldId,
                                        options: arrItems
                                    });
                                    wnd.show();
                                }
                            });
                        }
                    });
                }

                arrFieldListeners.push({
                    eventName: 'afterrender',
                    eventMethod: function (combo) {
                        new Ext.ToolTip({
                            target: combo.getEl(),
                            autoWidth: true,
                            cls: 'not-bold-header',
                            header: true,
                            trackMouse: true,
                            listeners: {
                                beforeshow: function (tooltip) {
                                    var val = combo.getRawValue();
                                    if (!empty(val)) {
                                        tooltip.setTitle(val);
                                    } else {
                                        // Don't show tooltip if value is empty
                                        setTimeout(function () {
                                            tooltip.hide();
                                        }, 1);
                                    }
                                }
                            }
                        });
                    }
                });

                if (booSetDefaultValue) {
                    // If in the combo there is only one option - automatically select it
                    switch (field.field_type) {
                        case 'assigned_to':
                            // Search for users only
                            var arrUsers = [];
                            var userRegexp = /^user:\d+$/;
                            for (var i = 0; i < arrData.length; i++) {
                                if (userRegexp.test(arrData[i]['option_id'])) {
                                    arrUsers.push(arrData[i]['option_id']);
                                }
                            }

                            if (arrUsers.length == 1) {
                                oFieldNewDetails.value = arrUsers[0];
                            }
                            break;

                        case 'staff_responsible_rma':
                        case 'active_users':
                            if (arrData.length == 1) {
                                oFieldNewDetails.value = arrData[0]['option_id'];
                            }
                            break;
                        default:
                    }
                }

                if (field.field_type == 'combo' && !booIsDependentGroup) {
                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, record) {
                            var otherField = combo.ownerCt.items.itemAt(1);
                            var booShowOtherField = typeof (record.data) == 'undefined' ? false : record.data.option_name == 'Other';
                            otherField.setVisible(booShowOtherField);
                        }
                    });
                }

                if (field.field_unique_id == 'case_type') {
                    if (!thisPanel.booChangedCaseType || empty(thisPanel.caseId) || empty(thisPanel.caseType)) {
                        oFieldNewDetails.disabled = true;
                    }

                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, rec, index, booDoNotRegenerate) {
                            var caseTypeValue = rec.data.option_id;
                            thisPanel.caseType = caseTypeValue;
                            if (!booDoNotRegenerate) {
                                thisPanel.loadApplicantDetails(false);
                            }
                        }
                    });

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (combo) {
                            var label = this.getEl().up('div.x-form-item').child('a', true);
                            if (label) {
                                Ext.get(label).on('mousedown', function (p1, el) {
                                    Ext.Msg.confirm(_('Please confirm'), _('The data in the fields that do not exist in your new ' + arrApplicantsSettings.case_type_label_singular + ' will be lost. Are you sure you want to proceed?'), function (btn) {
                                        if (btn === 'yes') {
                                            thisPanel.booChangedCaseType = true;
                                            combo.setDisabled(false);
                                            Ext.get(label).hide();
                                        }
                                    });
                                });
                            }
                        }
                    });
                }

                if (field.field_unique_id == 'categories') {
                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, rec, index) {
                            var caseCategoryValue = typeof (rec.data) == 'undefined' ? 0 : rec.data.option_id;
                            thisPanel.caseCategory = caseCategoryValue;

                            // Set disabled/enabled "Link to LMIA" button in the toolbar if it is visible and selected category allows this
                            thisPanel.toggleDisabledLinkToEmployerButton();

                            var caseStatusCombo = thisPanel.find('fieldUniqueName', 'file_status');
                            if (caseStatusCombo.length) {
                                var store = caseStatusCombo[0].getStore();
                                store.loadData(thisPanel.getCaseStatusesByCaseSettings(thisPanel.caseType, caseCategoryValue));
                            }
                        }
                    });
                }

                if (field.field_unique_id == 'third_country_visa') {
                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, record) {
                            var booShowLink = record.data.option_name === 'Yes';
                            var label = this.getEl().up('div.x-form-item').child('a', true);
                            Ext.get(label).setVisibilityMode(Ext.Element.DISPLAY).setVisible(booShowLink);
                        }
                    });

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function () {
                            var dependentId = typeof arrDependentData !== 'undefined' && typeof arrDependentData[line] !== 'undefined' ? arrDependentData[line] : 0;

                            // Automatically fire event to force label link show/hide
                            if (booIsDependentGroup && empty(dependentId) && !empty(this.getValue())) {
                                thisPanel.setComboValueAndFireBeforeSelectEvent(this, this.getValue());
                            }

                            var label = this.getEl().up('div.x-form-item').child('a', true);
                            Ext.get(label).on('mousedown', function (p1, el) {
                                // Maybe data was already saved,
                                // So dependent id was generated - let's use it!
                                if (booIsDependentGroup && empty(dependentId)) {
                                    var dependentFieldId = $(el).closest('table').find("[name*='field_case_dependants_dependent_id']");
                                    if (dependentFieldId.length) {
                                        dependentId = dependentFieldId.val();
                                    }
                                }

                                thisPanel.showThirdPartyVisaDialog(booHasEditAccess, booIsDependentGroup, dependentId);
                            });
                        }
                    });
                }

                // When relationship or marital_status changed - toggle the spouse_name field
                if (booIsDependentGroup && ['relationship', 'marital_status'].has(field.field_unique_id)) {
                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, record) {
                            var relationship = field.field_unique_id === 'relationship' ? record.data.option_id : null;
                            var marital_status = field.field_unique_id === 'relationship' ? null : record.data.option_id;

                            thisPanel.toggleDependentSpouseNameField(combo.ownerCt.ownerCt, relationship, marital_status);
                        }
                    });
                }

                if (field.field_type == 'staff_responsible_rma' || field.field_type == 'assigned_to') {
                    arrFieldListeners.push({
                        eventName: 'beforeselect',
                        eventMethod: function (combo, record) {
                            switch (field.field_unique_id) {
                                case 'registered_migrant_agent':
                                case 'sales_and_marketing':
                                case 'accounting':
                                case 'processing':
                                    // These cookies will be used on server
                                    Cookies.set('ys-' + field.field_unique_id, record.data.option_id, {expires: 365});
                                    break;
                                default:
                            }
                        }
                    });
                }

                if (field.field_type == 'office' && field.field_unique_id == 'office') {
                    arrFieldListeners.push({
                        eventName: 'select',
                        eventMethod: function (combo) {
                            Ext.state.Manager.set('agents_office', combo.getValue());
                        }
                    });
                }
                break;

            case 'date':
            case 'date_repeatable':
                oFieldNewDetails = {
                    xtype: booHasEditAccess ? 'datefield' : 'displayfield',
                    format: dateFormatFull,
                    altFormats: dateFormatFull + '|' + dateFormatShort
                };
                break;

            case 'memo':
                oFieldNewDetails = {
                    xtype: 'textarea',
                    grow: true, // allow automatically change the height on text enter,
                    growMin: !empty(fieldHeight) ? fieldHeight : 120
                };

                arrFieldListeners.push({
                    eventName: 'autosize',
                    eventMethod: function () {
                        // Fix issue when copy/paste text in this field - there is no scroller,
                        // and it is not possible to see all entered text
                        thisPanel.owner.owner.fixParentPanelHeight();
                    }
                });
                break;

            case 'html_editor':
                oFieldNewDetails = {
                    xtype: 'froalaeditor',
                    'class': 'clients-html-editor',
                    height: !empty(fieldHeight) ? fieldHeight : 150,
                    resizeEnabled: true,
                    value: '',
                    booAllowImagesUploading: true
                };

                arrFieldListeners.push({
                    eventName: 'instanceReady',
                    eventMethod: function () {
                        setTimeout(function () {
                            thisPanel.owner.owner.fixParentPanelHeight();
                        }, 500);
                    }
                });
                break;

            case 'checkbox':
                oFieldNewDetails = {
                    xtype: 'checkbox'
                };

                if (booIsDependentGroup && (field.field_unique_id == 'main_applicant_address_is_the_same' || field.field_unique_id == 'include_in_minute_checkbox')) {
                    oFieldNewDetails.inputValue = 'Y';

                    arrFieldListeners.push({
                        eventName: 'check',
                        eventMethod: function (checkbox, booChecked) {
                            if (field.field_unique_id == 'main_applicant_address_is_the_same') {
                                var mainContainer = checkbox.ownerCt.ownerCt;

                                var arrAddressFieldsToggle = ['address', 'city', 'country', 'region', 'postal_code'];
                                Ext.each(arrAddressFieldsToggle, function (addressFieldId) {
                                    var arrFields = mainContainer.find('fieldUniqueName', addressFieldId);
                                    if (arrFields.length) {
                                        arrFields[0].submittable_and_hidden = true;
                                        arrFields[0].setDisabled(booChecked);
                                        arrFields[0].ownerCt.setVisible(!booChecked);
                                    }
                                });
                            }

                            // Hack to submit value even if checkbox is unchecked
                            if (booChecked) {
                                if (this.noValEl != null) {
                                    // Remove the extra hidden element
                                    Ext.select('input[id=' + this.noValEl.id + ']').remove();
                                    this.noValEl = null;
                                }
                            } else {
                                // Add our hidden element for (unchecked) value
                                this.noValEl = Ext.DomHelper.insertAfter(this.el, {
                                    tag: 'input',
                                    type: 'hidden',
                                    value: 'N',
                                    name: this.getName()
                                }, true);
                            }
                        }
                    });

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function () {
                            if (this.checked) {
                                this.fireEvent('check', this, true);
                            }
                        }
                    });
                }
                break;

            case 'radio':
                var arrRadios = [];
                var arrRadiosData = [];
                if (field.hasOwnProperty('field_options')) {
                    arrRadiosData = field.field_options;
                } else {
                    if (arrApplicantsSettings.options[formMemberType][field.field_id]) {
                        arrRadiosData = arrApplicantsSettings.options[formMemberType][field.field_id];
                    }
                }
                var dependentId = typeof arrDependentData !== 'undefined' && typeof arrDependentData[line] !== 'undefined' ? arrDependentData[line] : 0;

                var radioGroupId = Ext.id();
                for (var j = 0; j < arrRadiosData.length; j++) {
                    var optionFieldName = oFieldDetails.name.replace('[]', '');
                    if (booIsDependentGroup) {
                        optionFieldName = radioGroupId + '_' + optionFieldName;
                    }


                    arrRadios.push({
                        name: optionFieldName,
                        hiddenName: optionFieldName,
                        boxLabel: arrRadiosData[j]['option_name'],
                        inputValue: arrRadiosData[j]['option_id'],
                        checked: booIsDependentGroup && empty(dependentId) && oFieldDetails.value && oFieldDetails.value == arrRadiosData[j]['option_id']
                    });
                }

                if (booIsDependentGroup) {
                    arrRadios.push({
                        xtype: 'hidden',
                        name: oFieldDetails.name,
                        value: oFieldDetails.value
                    });
                }

                oFieldNewDetails = {
                    id: radioGroupId,
                    xtype: 'radiogroup',
                    columns: 1,
                    items: arrRadios
                };

                if (field.field_unique_id == 'third_country_visa' && arrRadiosData.length > 1) {
                    oFieldNewDetails.autoValueFill = false;

                    if (booIsDependentGroup) {
                        // There is a hidden field for each dependent's row
                        oFieldNewDetails.columns = [1 / arrRadiosData.length, 1 / arrRadiosData.length, 0, 100];
                    } else {
                        oFieldNewDetails.columns = [1 / arrRadiosData.length, 1 / arrRadiosData.length, 100];
                    }

                    var greenButton = {
                        xtype: 'button',
                        id: oFieldNewDetails.id + '_btn',
                        hidden: true,
                        text: _('View Details'),
                        cls: 'green-btn small',

                        handler: function () {
                            // Maybe data was already saved,
                            // So dependent id was generated - let's use it!
                            if (booIsDependentGroup && empty(dependentId)) {
                                var dependentFieldId = $('#' + oFieldNewDetails.id + '_btn').closest('table').find('[name*=\'field_case_dependants_dependent_id\']');
                                if (dependentFieldId.length) {
                                    dependentId = dependentFieldId.val();
                                }
                            }

                            thisPanel.showThirdPartyVisaDialog(booHasEditAccess, booIsDependentGroup, dependentId, Ext.getCmp(oFieldNewDetails.id));
                        }
                    };
                    oFieldNewDetails.items.push(greenButton);

                    oFieldNewDetails.resetValueIfNoRecordsCreated = function (recordsCount) {
                        this.items.each(function (oGroupItem) {
                            if (empty(recordsCount)) {
                                oGroupItem.setValue(oGroupItem.boxLabel !== 'Yes');
                            }
                        });
                    };

                    arrFieldListeners.push({
                        eventName: 'change',
                        eventMethod: function (oGroup, oRadio) {
                            oGroup.items.each(function (oField) {
                                if (oField.getXType() === 'hidden') {
                                    oField.setValue(oRadio.inputValue);
                                }
                            });

                            var booShowBtn = oRadio.boxLabel === 'Yes';
                            var btn = Ext.getCmp(oGroup.id + '_btn');
                            btn.setVisible(booShowBtn);

                            var booAutoFill = false;
                            if (oGroup.autoValueFill) {
                                oGroup.autoValueFill = false;
                                booAutoFill = true;
                            }

                            // Automatically show the dialog if "Yes" was manually checked
                            // But not during data loading
                            if (booShowBtn && !booAutoFill) {
                                btn.el.dom.click();
                            }
                        }
                    });
                } else if (field.field_unique_id == 'cbiu_investment_type' && !empty(arrApplicantsSettings.government_fund_option_id)) {
                    arrFieldListeners.push({
                        eventName: 'change',
                        eventMethod: function (oGroup, oRadio) {
                            var realEstateProjectFields = thisPanel.find('fieldUniqueName', 'real_estate_project');

                            if (oRadio.inputValue == arrApplicantsSettings.government_fund_option_id) {
                                realEstateProjectFields[0].setDisabled(true);
                                realEstateProjectFields[0].setValue('-');
                            } else {
                                if (realEstateProjectFields[0].getValue() == '-') {
                                    realEstateProjectFields[0].reset();
                                }
                                realEstateProjectFields[0].setDisabled(false);
                            }
                        }
                    });
                }
                break;

            case 'password':
                if (field.field_unique_id == 'password') {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        minLength: passwordMinLength,
                        maxLength: passwordMaxLength,
                        inputType: 'password'
                    };
                } else {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        inputType: 'password'
                    };
                }
                break;

            case 'multiple_text_fields':
                oFieldNewDetails = {
                    xtype: 'multipletextfields',
                    maxAllowedRowsCount: 10,
                    style: 'margin-top: -4px;',
                    addButtonCfg: {
                        width: 'auto',
                        iconCls: 'icon-applicant-add-section'
                    },
                    deleteButtonText: '',
                    deleteButtonCfg: {
                        tooltip: 'Click to delete.',
                        width: 20,
                        iconCls: 'icon-applicant-remove-section'
                    }
                };
                break;

            case 'authorized_agents':
            case 'office_change_date_time':
                oFieldNewDetails = {
                    xtype: 'displayfield',
                    value: '-'
                };
                break;

            case 'photo':
                label = '<div style="color: #000; font-weight: 500;" class ="x-form-field-value">' + label + '</div>';
                var extraLabel1 = '<div style="float: left; margin-bottom: -3px">' + label + '</div>';
                var extraLabel2 = '<div style="float: left; margin-bottom: -12px">' + label + '</div>';

                var deleteUrl = topBaseUrl + '/applicants/profile/delete-file?type=image&mid=' + thisPanel.applicantId + '&id=' + field.field_id;
                var viewUrl   = topBaseUrl + '/applicants/profile/view-image?mid=' + thisPanel.applicantId + '&id=' + field.field_id;
                var imgWidth = 'width="40px"';
                var href = '';
                var target = 'target="_blank"';
                var imageEditStyle = 'style="max-width: 120px; padding-left: 0px;"';

                if (booIsDependentGroup) {
                    href = topBaseUrl + '/applicants/profile/get-profile-image?mid=' + thisPanel.caseId;
                    viewUrl = topBaseUrl + '/applicants/profile/view-image?mid=' + thisPanel.caseId + '&type=thumbnail';
                    deleteUrl = topBaseUrl + '/applicants/profile/delete-file?type=image&mid=' + thisPanel.caseId;
                } else {
                    href = topBaseUrl + '/applicants/profile/get-profile-image?mid=' + thisPanel.applicantId;
                    deleteUrl = topBaseUrl + '/applicants/profile/delete-file?type=image&mid=' + thisPanel.applicantId + '&id=' + field.field_id;
                    viewUrl = topBaseUrl + '/applicants/profile/view-image?mid=' + thisPanel.applicantId + '&id=' + field.field_id;
                }



                if (typeof arrDependentData !== 'undefined' && typeof arrDependentData[line] !== 'undefined') {
                    viewUrl += '/did/' + arrDependentData[line];
                    deleteUrl += '/did/' + arrDependentData[line];
                    href += '/did/' + arrDependentData[line];
                }

                viewUrl += '/' + Date.now();
                deleteUrl += '/' + Date.now();
                href += '/' + Date.now();

                var changeLinks;
                var photoInput;

                if (booHasEditAccess || (booIsDependentGroup && groupAccess == 'F')) {
                    changeLinks = '<div  style="float: left;" class="form-image-links" style="margin-bottom: -1px">' +
                        '<a href="#" class="blulinkunm x-form-field-value" data-rel="change" onclick="return false" style="margin-right: 20px">' + _('change') + '</a>' +
                        '<a href="{0}" class="blulinkunm x-form-field-value" data-rel="remove" onclick="return false">' + _('remove') + '</a>' +
                        '</div>';

                    photoInput = '<input type="file" name="{2}" class="form-image-input x-form-element" accept="image/*" style="width: 100%; position: absolute; top: 16px; "/>' +
                        '<a href="#" class="blulinkunm x-form-field-value" style="display:none; position: absolute; top: 45px;" data-rel="cancel" onclick="return false">cancel</a>';
                } else {
                    changeLinks = '';
                    photoInput = '<div style="padding-top: 16px">-</div>';
                }

                var fieldHtml = String.format(
                    '<table class="form-image-view hidden" style="width: 280px; min-height: 70px; margin-bottom: -8px">' +
                        '<tr><td colspan="2" align="left ">' + extraLabel1 + '</td></tr>' +
                        '<tr>' +
                            '<td width="50%">' +
                                '<div><a href="{5}" {6}><img {3} src="{1}" data-path="{1}" vspace="2" border="0" align="bottom" alt="" /></div></a>' +
                            '</td>' +
                            '<td width="50%" valign="top">' +
                                changeLinks +
                            '</td>' +
                        '</tr>' +
                    '</table>' +

                    '<table class="form-image-edit" style="width: 280px; min-height: 70px;">' +
                        '<tr style="' + (empty(extraLabel2) ? 'display: none;' : '') + '">' +
                            '<td align="left" ' +
                                extraLabel2 +
                            '</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td valign="top">' +
                                photoInput +
                            '<td>' +
                        '</tr>' +
                    '</table>',
                    deleteUrl,
                    viewUrl,
                    'field_file_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]',
                    imageEditStyle,
                    imgWidth,
                    href,
                    target
                );

                oFieldDetails = {
                    realFieldId: parseInt(field.field_id, 10),
                    name:        'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]',
                    cls:         'form-image',
                    xtype:       'panel',
                    anchor:      booTinyWidth ? '80%' : '90%',
                    html:        fieldHtml
                };
                break;

            case 'file':
                label = '<div style="color: #000; font-weight: 500;" class ="x-form-field-value">' + label + '</div>';
                var fieldHtml = String.format(
                    '<div class="form-file-view hidden">' +
                    '<div style="float: left;">' +
                    label +
                    '<div class="form-file-links">' +
                    '<a href="#" class="blulinkunm x-form-field-value" data-rel="change" onclick="return false">change</a>' +
                    '<a href="{0}" class="blulinkunm x-form-field-value" data-rel="remove" onclick="return false">remove</a>' +
                    '</div>' +
                    '</div>' +
                    '<div class="form-file-download"><a href="{1}" class="blulinkunm x-form-field-value" data-rel="download" style="white-space: nowrap"></a></div>' +
                    '<div style="clear: both;"></div>' +
                    '</div>' +

                    '<div class="form-file-edit">' +
                    label +
                    '<input type="file" name="{2}" class="form-file-input" />' +
                    '<a href="#" class="blulinkunm x-form-field-value" style="display:none;" data-rel="cancel" onclick="return false">cancel</a>' +
                    '</div>',
                    topBaseUrl + '/applicants/profile/delete-file?type=file&mid=' + thisPanel.applicantId + '&id=' + field.field_id,
                    topBaseUrl + '/applicants/profile/download-file?mid=' + thisPanel.applicantId + '&id=' + field.field_id,
                    'field_file_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]'
                );

                oFieldDetails = {
                    realFieldId: parseInt(field.field_id, 10),
                    name:        'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '[]',
                    cls:         'form-file',
                    xtype:       'panel',
                    anchor:      booTinyWidth ? '80%' : '90%',
                    html:        fieldHtml
                };
                break;

            case 'related_case_selection':
                // The difference between 'combo' and this field type is that we listen 'click' on the label
                // and open client's tab with opened case
                var arrThisComboData = [];
                if(arrApplicantsSettings.options[formMemberType][field.field_id]) {
                    arrThisComboData = arrApplicantsSettings.options[formMemberType][field.field_id];
                }

                var emptyText;

                oFieldNewDetails = {
                    xtype: 'combo',
                    fieldLabel: label,
                    comboType: field.field_type,
                    emptyText: emptyText,
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'case_id'
                        }, [
                            {name: 'case_and_applicant_name'},
                            {name: 'case_id'},
                            {name: 'case_name'},
                            {name: 'case_type'},
                            {name: 'applicant_id'},
                            {name: 'applicant_name'},
                            {name: 'applicant_type'}
                        ]),
                        data: arrThisComboData,

                        // Allow filter 'any match values'
                        filter: function(filters, value) {
                            var escapeRegexRe = /([-.*+?\^${}()|\[\]\/\\])/g;
                            Ext.data.Store.prototype.filter.apply(this, [
                                filters,
                                value ? new RegExp(value.replace(escapeRegexRe, "\\$1"), 'i') : value
                            ]);
                        }
                    },
                    mode:            'local',
                    displayField:    'case_and_applicant_name',
                    valueField:      'case_id',
                    triggerAction:   'all',
                    forceSelection:  true,
                    selectOnFocus:   true,
                    editable:        true,
                    enableKeyEvents: true,
                    anchor:          booTinyWidth ? '95%' : '100%'
                };

                arrFieldListeners.push({
                    eventName: 'render',
                    eventMethod: function (combo) {
                        combo.ownerCt.getEl().addListener('click', function (evt, el) {
                            if (field.field_type == 'related_case_selection' && $(el).is('label')) {
                                var rec = combo.getStore().getById(combo.getValue());
                                if (rec) {
                                    switch (rec.data.applicant_type) {
                                        case 'individual':
                                            thisPanel.owner.owner.openApplicantTab({
                                                applicantId: rec.data.applicant_id,
                                                applicantName: rec.data.applicant_name,
                                                memberType: rec.data.applicant_type,
                                                caseId: rec.data.case_id,
                                                caseName: rec.data.case_name,
                                                caseType: rec.data.case_type
                                            }, 'case_details');
                                            break;

                                        case 'employer':
                                        default:
                                            thisPanel.owner.owner.openApplicantTab({
                                                applicantId: rec.data.applicant_id,
                                                applicantName: rec.data.applicant_name,
                                                memberType: rec.data.applicant_type,
                                                caseId: rec.data.case_id,
                                                caseName: rec.data.case_name,
                                                caseType: rec.data.case_type,
                                                caseEmployerId: rec.data.applicant_id,
                                                caseEmployerName: rec.data.applicant_name
                                            }, 'case_details');
                                    }
                                } else {
                                    Ext.simpleConfirmation.info(_('Please first select the related employer case.'));
                                }
                            }
                        });
                    }
                });

                arrFieldListeners.push({
                    eventName: 'afterrender',
                    eventMethod: function (combo) {
                        new Ext.ToolTip({
                            target: combo.getEl(),
                            autoWidth: true,
                            cls: 'not-bold-header',
                            header: true,
                            trackMouse: true,
                            listeners: {
                                beforeshow: function (tooltip) {
                                    var val = combo.getRawValue();
                                    if (!empty(val)) {
                                        tooltip.setTitle(val);
                                    } else {
                                        // Don't show tooltip if value is empty
                                        setTimeout(function () {
                                            tooltip.hide();
                                        }, 1);
                                    }
                                }
                            }
                        });
                    }
                });

                arrFieldListeners.push({
                    eventName: 'beforeselect',
                    eventMethod: function (combo, record) {
                        var oldVal = combo.getValue(),
                            newVal = record.data.case_id;

                        if (field.field_type != 'related_case_selection' && oldVal != newVal && !empty(newVal)) {
                            // Send request to check if we need to show a warning dialog
                            Ext.Ajax.request({
                                url: baseUrl + '/applicants/profile/check-employer-case',
                                params: {
                                    applicantId: Ext.encode(thisPanel.applicantId),
                                    caseId: Ext.encode(thisPanel.caseId),
                                    selectedCaseId: Ext.encode(newVal)
                                },

                                success: function (f) {
                                    var resultData = Ext.decode(f.responseText);
                                    if (!empty(resultData.msg)) {
                                        switch (resultData.msg_type) {
                                            case 'error':
                                                Ext.simpleConfirmation.error(resultData.msg);
                                                break;

                                            case 'confirmation':
                                                Ext.Msg.confirm(_('Please confirm'), resultData.msg, function (btn) {
                                                    if (btn == 'no') {
                                                        // Revert selection
                                                        combo.setValue(oldVal);
                                                        combo.fireEvent('change', combo, oldVal);
                                                    }
                                                });
                                                break;

                                            default:
                                        }
                                    }
                                },

                                failure: function () {
                                    Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                                }
                            });
                        }
                    }
                });

                arrFieldListeners.push({
                    eventName: 'keyup',
                    eventMethod: function (combo) {
                        combo.fireEvent('change', combo, combo.getValue());
                    }
                });

                arrFieldListeners.push({
                    eventName: 'select',
                    eventMethod: function (combo, record) {
                        combo.fireEvent('change', combo, record.data[combo.valueField]);
                    }
                });

                arrFieldListeners.push({
                    eventName: 'change',
                    eventMethod: function (combo, newVal) {
                        if (field.field_type == 'related_case_selection') {
                            // Mark the label as a link only when there is entered value
                            var fieldEl = $('#' + combo.ownerCt.getId()).find('.x-form-item');
                            if (!empty(newVal)) {
                                fieldEl.addClass('x-form-item-label-as-link');
                            } else {
                                fieldEl.removeClass('x-form-item-label-as-link');
                            }
                        }
                    }
                });
                break;

            case 'contact_sales_agent':
                // The difference between 'combo' and this field type is that we listen the 'click' event on the label
                var arrComboData = arrApplicantsSettings.options['general']['contact_sales_agent'];

                oFieldNewDetails = {
                    xtype:      'combo',
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'}
                        ]),
                        data: arrComboData
                    },
                    mode:            'local',
                    displayField:    'option_name',
                    valueField:      'option_id',
                    triggerAction:   'all',
                    forceSelection:  true,
                    selectOnFocus:   true,
                    editable:        true,
                    enableKeyEvents: true,
                    anchor:          booTinyWidth ? '95%' : '100%'
                };

                arrFieldListeners.push({
                    eventName: 'render',
                    eventMethod: function (combo) {
                        if (allowedPages.has('contacts')) {
                            combo.ownerCt.getEl().addListener('click', function (evt, el) {
                                if ($(el).is('label')) {
                                    var contactId = combo.getValue();
                                    if (!empty(contactId)) {
                                        setUrlHash('#contacts/' + contactId);
                                        setActivePage();
                                    }
                                }
                            });
                        }
                    }
                });

                arrFieldListeners.push({
                    eventName: 'keyup',
                    eventMethod: function (combo) {
                        combo.fireEvent('change', combo, combo.getValue());
                    }
                });

                arrFieldListeners.push({
                    eventName: 'select',
                    eventMethod: function (combo, record) {
                        combo.fireEvent('change', combo, record.data[combo.valueField]);
                    }
                });

                arrFieldListeners.push({
                    eventName: 'change',
                    eventMethod: function (combo, newVal) {
                        if (allowedPages.has('contacts')) {
                            // Mark the label as a link only when there is entered value
                            var fieldEl = $('#' + combo.ownerCt.getId()).find('.x-form-item');
                            if (!empty(newVal)) {
                                fieldEl.addClass('x-form-item-label-as-link');
                            } else {
                                fieldEl.removeClass('x-form-item-label-as-link');
                            }
                        }
                    }
                });
                break;

            case 'number':
                oFieldNewDetails = {
                    xtype: 'numberfield',
                    allowDecimals: false
                };
                break;

            case 'auto_calculated':
                oFieldNewDetails = {
                    xtype: 'numberfield',
                    allowDecimals: false,
                    value: '0',
                    readOnly: true,
                    minValue: parseInt(field.field_min_value),
                    maxValue: parseInt(field.field_max_value)
                };

                arrFieldListeners.push({
                    eventName: 'render',
                    eventMethod: function (thisField) {
                        if (!booHasEditAccess) {
                            return;
                        }

                        var arrThisComboData = [];
                        if (arrApplicantsSettings.options[formMemberType][field.field_id]) {
                            arrThisComboData = arrApplicantsSettings.options[formMemberType][field.field_id];
                        }

                        // For each linked field add listener on field change
                        for (var i = 0; i < arrThisComboData.length; i++) {
                            var sourceFields = thisPanel.find('fieldUniqueName', arrThisComboData[i]['option_name']);
                            for (var j = 0; j < sourceFields.length; j++) {
                                sourceFields[j].on('change', thisPanel.updateAutoCalcField.createDelegate(thisPanel, [thisField, arrThisComboData]));
                            }
                        }
                    }
                });
                break;

            case 'kskeydid':
                oFieldNewDetails = {
                    xtype: 'textfield',
                    realFieldLabel: field.field_name,
                    fieldLabel: label + ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value">(generate new)</a>',
                    readOnly: true,
                    disabled: false,
                    style: "border: 0; background-color: #ffffff; background-image: none;",
                    submittable: true
                };

                arrFieldListeners.push({
                    eventName: 'render',
                    eventMethod: function () {
                        var thisField = this;
                        var label = this.getEl().up('div.x-form-item').child('a', true);
                        Ext.get(label).on('mousedown', function () {
                            thisPanel.generateNewKsKey(thisField);
                        });
                    }
                });
                break;

            case 'hyperlink':
                oFieldNewDetails = {
                    xtype: 'textfield',
                    realFieldLabel: field.field_name,
                    fieldLabel: label + '<img style="padding-left: 3px; margin-bottom: -5px;" src="' + topBaseUrl + '/images/icons/help.png"/>',
                    enableKeyEvents: true
                };

                arrFieldListeners.push({
                    eventName: 'render',
                    eventMethod: function (field) {
                        field.ownerCt.getEl().addListener('click', function (evt, el) {
                            if ($(el).is('label')) {
                                var link = field.getValue();
                                if (empty(link)) {
                                    Ext.simpleConfirmation.warning('Please enter a link first.');
                                } else if (!Ext.form.VTypes.url(link)) {
                                    Ext.simpleConfirmation.warning('Please enter correct link.');
                                } else {
                                    window.open(link);
                                }
                            }
                        });
                    }
                });

                arrFieldListeners.push({
                    eventName: 'keyup',
                    eventMethod: function (combo) {
                        combo.fireEvent('change', combo, combo.getValue());
                    }
                });

                arrFieldListeners.push({
                    eventName: 'change',
                    eventMethod: function (combo, newVal) {
                        // Mark the label as a link only when there is entered value
                        var fieldEl = $('#' + combo.ownerCt.getId()).find('.x-form-item');
                        if (!empty(newVal)) {
                            fieldEl.addClass('x-form-item-label-as-link');
                        } else {
                            fieldEl.removeClass('x-form-item-label-as-link');
                        }
                    }
                });


                arrFieldListeners.push({
                    eventName: 'afterrender',
                    eventMethod: function (field) {
                        new Ext.ToolTip({
                            target: field.getEl().up('div.x-form-item').child('img', true),
                            autoWidth: true,
                            html: 'Please enter a full URL starting with http:// or https://',
                            title: 'How to use Hyperlink field',
                            autoHide: false,
                            closable: true,
                            anchor: 'top',
                            draggable: true
                        });
                    }
                });
                break;

            case 'bcpnp_nomination_certificate_number':
                if (booAutomaticTurnedOn) {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        realFieldLabel: field.field_name,
                        fieldLabel: label + ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value">(generate new)</a>',
                        readOnly: true,
                        style: "border: 0; background-color: #ffffff; background-image: none;",
                        disabled: false,
                        submittable: true
                    };

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function (field) {
                            var thisField = this;
                            var label = this.getEl().up('div.x-form-item').child('a', true);
                            var caseReferenceNumberFields = thisPanel.find('fieldUniqueName', 'file_number');

                            Ext.get(label).on('mousedown', function () {
                                thisPanel.getNewBcpnpNominationCertificateNumber(thisField, caseReferenceNumberFields[0]);
                            });
                        }
                    });
                }
                break;

            case 'reference':
                var realFieldId = parseInt(field.field_id, 10);
                var booMultipleValues = field.field_multiple_values;

                if (booMultipleValues) {
                    oFieldNewDetails = {
                        xtype: 'multipletextfields',
                        realFieldLabel: field.field_name,
                        fieldLabel: label + '<img style="padding-left: 3px;margin-bottom: -5px;" src="' + topBaseUrl + '/images/icons/help.png"/>',
                        disabled: false,
                        realFieldType: 'reference',
                        realFieldId: realFieldId,
                        enableKeyEvents: true,
                        maxAllowedRowsCount: 4,
                        addButtonCfg: {
                            width: 'auto',
                            iconCls: 'icon-applicant-add-section',
                            style: 'margin-top: -4px;',
                            listeners: {
                                click: function(field) {
                                    setTimeout(function () {
                                        thisPanel.initReferenceField($('#' + field.id).parents('div.x-form-item').find('input:last').attr('id'), true, true, realFieldId);
                                    }, 100);
                                }
                            }
                        },
                        deleteButtonText: '',
                        deleteButtonCfg: {
                            tooltip: 'Click to delete.',
                            width: 20,
                            iconCls: 'icon-applicant-remove-section',
                            style: 'float: right;'
                        }
                    };

                } else {
                    oFieldNewDetails = {
                        xtype: 'textfield',
                        realFieldLabel: field.field_name,
                        fieldLabel: label + '<img style="padding-left: 3px;margin-bottom: -5px;" src="' + topBaseUrl + '/images/icons/help.png"/>',
                        disabled: false,
                        realFieldType: 'reference',
                        realFieldId: realFieldId,
                        enableKeyEvents: true
                    };


                    arrFieldListeners.push({
                        eventName: 'afterrender',
                        eventMethod: function (field) {
                            $("#" + field.id).after('<span class="reference_info" style="float: left;">Press Enter after editing this field</span>');
                        }
                    });

                    arrFieldListeners.push({
                        eventName: 'keypress',
                        eventMethod: function (field) {
                            if (!$('#' + field.id).siblings('.edit_reference').length) {
                                thisPanel.initReferenceField(field.id, true, false, realFieldId);
                            }
                        }
                    });
                }

                arrFieldListeners.push({
                    eventName: 'afterrender',
                    eventMethod: function (field) {
                        new Ext.ToolTip({
                            target: field.getEl().up('div.x-form-item').child('img', true),
                            autoWidth: true,
                            html: 'This field is a reference to another case, profile or a field.\n<br/>' +
                                'Please use the following values for this field:\n<br/>' +
                                'case_<%ID%>: Link to a case. Example: case_95\n<br/>' +
                                'individual_profile_<%ID%>: Link to an individual profile. Example: individual_profile_107\n<br/>' +
                                'employer_profile_<%ID%>: Link to an employer profile. Example: employer_profile_375\n<br/>' +
                                'case_<%ID%>_field_<%FIELDNAME%>: Link to a field in a case.\n<br/>' +
                                'You can replace "case" with "individual_profile" or "employer_profile". \n<br/>' +
                                'Example: case_903_field_assessment_score, individual_profile_1023_field_DOB',
                            title: 'How to use Reference field',
                            autoHide: false,
                            closable: true,
                            anchor: 'top',
                            draggable: true
                        });
                        if (!booMultipleValues) {
                            $("#" + field.id).after('<span class="reference_info" style="float: left;">Press Enter after editing this field</span>');
                        }
                    }
                });
                break;

            case 'case_internal_id':
            case 'applicant_internal_id':
                oFieldNewDetails = {
                    xtype: 'textfield',
                    realFieldLabel: field.field_name,
                    fieldLabel: label,
                    readOnly: true,
                    style: "border: 0; background-color: #ffffff; background-image: none;",
                    disabled: false,
                    submittable: false
                };
                break;

            case 'client_referrals':
                if (!empty(thisPanel.applicantId)) {
                    oFieldNewDetails = {
                        xtype: 'ApplicantsProfileClientReferralsGrid',
                        style: 'border: 1px #E8EAEE solid',
                        hideLabel: true,
                        applicantId: thisPanel.applicantId,
                        booReadOnly: !booHasEditAccess || !thisPanel.hasAccess(thisPanel.memberType, 'edit'),
                        owner: thisPanel
                    };
                } else {
                    oFieldNewDetails = {
                        xtype: 'hidden',
                        style: 'display: none;'
                    };
                }
                break;

            case 'client_profile_id':
                if (!empty(thisPanel.applicantId)) {
                    oFieldNewDetails = {
                        xtype: 'displayfield',
                        value: '-'
                    };
                } else {
                    oFieldNewDetails = {
                        xtype: 'hidden',
                        style: 'display: none;'
                    };
                }
                break;

            case 'hidden':
                oFieldNewDetails = {
                    xtype: 'hidden',
                    style: 'display: none;'
                };
                break;
            // case 'phone':
            // case 'text':
            default:
                oFieldNewDetails = {};
                if (field.field_unique_id == 'file_number' && !empty(thisPanel.applicantId) && thisPanel.hasAccess(thisPanel.memberType, 'edit') && thisPanel.hasAccess('', 'generate_file_number') && !is_client) {
                    var booDoesNotHaveAccessToGenerate = oFieldDetails.disabled  || thisPanel.hasAccess('', 'generate_file_number_field_readonly');

                    oFieldNewDetails = {
                        xtype: 'textfield',
                        realFieldLabel: field.field_name,
                        fieldLabel: label + ' <a href="#" onclick="return false;" class="blulinkunm x-form-field-value link-generate-file-number">' + _('(generate new)') + '</a>',
                        readOnly: booDoesNotHaveAccessToGenerate,
                        disabled: false,
                        submittable: !oFieldDetails.disabled,
                        style: booDoesNotHaveAccessToGenerate ? 'border: 0; background-color: #ffffff; background-image: none;' : ''
                    };

                    arrFieldListeners.push({
                        eventName: 'render',
                        eventMethod: function () {
                            var thisField = this;
                            var label = this.getEl().up('div.x-form-item').child('a', true);
                            if (label) {
                                Ext.get(label).on('mousedown', function () {
                                    thisPanel.generateNewCaseNumber(thisField);
                                });
                            }
                        }
                    });
                }
                break;
        }

        // For specific fields we generate custom label as a link
        // by clicking on it - we'll pass entered values to the special page
        // Also current user must have access rights to open these links
        if (thisPanel.hasAccess(thisPanel.memberType, 'abn_check') && ['EOI_ID', 'EOI_password', 'australian_business_number', 'australian_company_number', 'entity_name'].has(field.field_unique_id)) {
            oFieldNewDetails.enableKeyEvents = true;

            arrFieldListeners.push({
                eventName: 'keyup',
                eventMethod: function (field) {
                    // Mark the label as a link only when there is entered value
                    var fieldEl = $('#' + field.ownerCt.getId()).find('.x-form-item');
                    if (field.getValue().trim() != '') {
                        fieldEl.addClass('x-form-item-label-as-link');
                    } else {
                        fieldEl.removeClass('x-form-item-label-as-link');
                    }
                }
            });

            arrFieldListeners.push({
                eventName: 'render',
                eventMethod: function (field) {
                    field.ownerCt.getEl().addListener('click', function (evt, el) {
                        if ($(el).is('label')) {
                            var booCorrect = false,
                                val1 = '',
                                val2 = '';
                            if (['EOI_ID', 'EOI_password'].has(field.fieldUniqueName)) {
                                var loginField = field.ownerCt.ownerCt.ownerCt.find('fieldUniqueName', 'EOI_ID');
                                if (loginField.length) {
                                    val1 = loginField[0].getValue().trim();
                                }

                                var passField = field.ownerCt.ownerCt.ownerCt.find('fieldUniqueName', 'EOI_password');
                                if (passField.length) {
                                    val2 = passField[0].getValue().trim();
                                }

                                // Both login and pass must be entered
                                booCorrect = (val1 != '' && val2 != '');
                            } else {
                                val1 = field.getValue().trim();

                                // Value must be entered to proceed
                                booCorrect = val1 != '';
                            }

                            if (booCorrect) {
                                submit_post_via_hidden_form(
                                    topBaseUrl + '/applicants/index/open-link',
                                    {
                                        id: field.fieldUniqueName,
                                        val1: val1,
                                        val2: val2
                                    }
                                );
                            }
                        }
                    });
                }
            });
        }

        // Toggle conditional fields
        if (!booIsDependentGroup && ['combo', 'multiple_combo', 'radio', 'checkbox', 'categories'].has(field.field_type)) {
            if (!empty(arrApplicantsSettings.conditional_fields[formMemberType]) && arrApplicantsSettings.conditional_fields[formMemberType][thisPanel.caseType]) {
                var arrConditionalFields = arrApplicantsSettings.conditional_fields[formMemberType][thisPanel.caseType][oFieldDetails.realFieldId];
                if (!empty(arrConditionalFields)) {
                    switch (field.field_type) {
                        case 'checkbox':
                            arrFieldListeners.push({
                                eventName: 'render',
                                eventMethod: function () {
                                    this.fireEvent('check', this, false);
                                }
                            });

                            arrFieldListeners.push({
                                eventName: 'check',
                                eventMethod: function (checkbox, booChecked, booDoNotShowIfHidden) {
                                    for (var k in arrConditionalFields) {
                                        // Toggle fields
                                        if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                                            for (j in arrConditionalFields[k]['hide_fields']) {
                                                if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j)) {
                                                    var arrFieldsToHide = thisPanel.find('realFieldId', parseInt(arrConditionalFields[k]['hide_fields'][j], 10));
                                                    if (!empty(arrFieldsToHide) && arrFieldsToHide.length) {
                                                        for (i = 0; i < arrFieldsToHide.length; i++) {
                                                            thisPanel.toggleConditionalField(formMemberType, arrFieldsToHide[i], k === 'checked' ? !booChecked : booChecked, booDoNotShowIfHidden);
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        // Toggle groups
                                        if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_groups')) {
                                            for (j in arrConditionalFields[k]['hide_groups']) {
                                                if (arrConditionalFields[k]['hide_groups'].hasOwnProperty(j)) {
                                                    var arrGroupsToHide = thisPanel.find('realGroupId', parseInt(arrConditionalFields[k]['hide_groups'][j], 10));
                                                    if (!empty(arrGroupsToHide) && arrGroupsToHide.length) {
                                                        for (i = 0; i < arrGroupsToHide.length; i++) {
                                                            arrGroupsToHide[i].ownerCt.ownerCt.setVisible(k === 'checked' ? !booChecked : booChecked);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            break;

                        case 'radio':
                            arrFieldListeners.push({
                                eventName: 'render',
                                eventMethod: function () {
                                    this.fireEvent('change', this);
                                }
                            });

                            arrFieldListeners.push({
                                eventName: 'check',
                                eventMethod: function () {
                                    this.fireEvent('change', this);
                                }
                            });

                            arrFieldListeners.push({
                                eventName: 'change',
                                eventMethod: function (radio, newVal) {
                                    newVal = newVal && newVal.inputValue ? newVal.inputValue : 0;
                                    thisPanel.toggleConditionalFieldsAndGroups(formMemberType, arrConditionalFields, [newVal]);
                                }
                            });
                            break;

                        case 'multiple_combo':
                            arrFieldListeners.push({
                                eventName: 'select',
                                eventMethod: function () {
                                    if (!this.isVisible()) {
                                        return;
                                    }

                                    var checkedOptions = this.getValue();
                                    if (empty(checkedOptions)) {
                                        this.fireEvent('change', this);
                                    } else {
                                        var arrCheckedOptions = checkedOptions.split(this.separator);
                                        thisPanel.toggleConditionalFieldsAndGroups(formMemberType, arrConditionalFields, arrCheckedOptions);
                                    }
                                }
                            });

                            arrFieldListeners.push({
                                eventName: 'change',
                                eventMethod: function (combo, newVal) {
                                    if (empty(newVal)) {
                                        for (var k in arrConditionalFields) {
                                            // Toggle fields
                                            if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                                                for (var j in arrConditionalFields[k]['hide_fields']) {
                                                    if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j)) {
                                                        var arrFieldsToHide = thisPanel.find('realFieldId', parseInt(arrConditionalFields[k]['hide_fields'][j], 10));
                                                        if (!empty(arrFieldsToHide) && arrFieldsToHide.length) {
                                                            for (var i = 0; i < arrFieldsToHide.length; i++) {
                                                                thisPanel.toggleConditionalField(formMemberType, arrFieldsToHide[i], !empty(k));
                                                            }
                                                        }
                                                    }
                                                }
                                            }

                                            // Toggle groups
                                            if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_groups')) {
                                                for (j in arrConditionalFields[k]['hide_groups']) {
                                                    if (arrConditionalFields[k]['hide_groups'].hasOwnProperty(j)) {
                                                        var arrGroupsToHide = thisPanel.find('realGroupId', parseInt(arrConditionalFields[k]['hide_groups'][j], 10));
                                                        if (!empty(arrGroupsToHide) && arrGroupsToHide.length) {
                                                            for (i = 0; i < arrGroupsToHide.length; i++) {
                                                                arrGroupsToHide[i].ownerCt.ownerCt.setVisible(!empty(k));
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            break;

                        case 'combo':
                        default:
                            arrFieldListeners.push({
                                eventName: 'blur',
                                eventMethod: function (combo) {
                                    // 'beforeselect' sometimes isn't called, so simulate it
                                    // e.g. when clear value in the combo
                                    if (empty(this.getValue())) {
                                        var record = {
                                            id: 0
                                        };
                                        combo.fireEvent('beforeselect', combo, record);
                                    }
                                }
                            });

                            arrFieldListeners.push({
                                eventName: 'beforeselect',
                                eventMethod: function (combo, record) {
                                    var arrGroupedFieldsToToggle = [];
                                    var arrGroupedGroupsToToggle = [];
                                    for (var k in arrConditionalFields) {
                                        var booNeedToHide = record['id'] == k;

                                        // Collect fields that we want to toggle
                                        if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_fields')) {
                                            for (var j in arrConditionalFields[k]['hide_fields']) {
                                                if (arrConditionalFields[k]['hide_fields'].hasOwnProperty(j)) {
                                                    var arrFieldsToHide = thisPanel.find('realFieldId', parseInt(arrConditionalFields[k]['hide_fields'][j], 10));
                                                    if (!empty(arrFieldsToHide) && arrFieldsToHide.length) {
                                                        for (var i = 0; i < arrFieldsToHide.length; i++) {
                                                            var booFound = false;
                                                            Ext.each(arrGroupedFieldsToToggle, function (oFieldConditionDetails, index) {
                                                                if (oFieldConditionDetails['field']['fieldUniqueName'] === arrFieldsToHide[i]['fieldUniqueName']) {
                                                                    booFound = true;

                                                                    if (booNeedToHide) {
                                                                        arrGroupedFieldsToToggle[index]['booShow'] = false;
                                                                    }
                                                                }
                                                            });

                                                            if (!booFound) {
                                                                arrGroupedFieldsToToggle.push({
                                                                    field: arrFieldsToHide[i],
                                                                    booShow: !booNeedToHide
                                                                });
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        // Collect groups that we want to toggle
                                        if (arrConditionalFields.hasOwnProperty(k) && arrConditionalFields[k].hasOwnProperty('hide_groups')) {
                                            for (j in arrConditionalFields[k]['hide_groups']) {
                                                if (arrConditionalFields[k]['hide_groups'].hasOwnProperty(j)) {
                                                    var arrGroupsToHide = thisPanel.find('realGroupId', parseInt(arrConditionalFields[k]['hide_groups'][j], 10));
                                                    if (!empty(arrGroupsToHide) && arrGroupsToHide.length) {
                                                        for (i = 0; i < arrGroupsToHide.length; i++) {
                                                            var booFound = false;
                                                            Ext.each(arrGroupedGroupsToToggle, function (oGroupConditionDetails, index) {
                                                                if (parseInt(oGroupConditionDetails['group']['realGroupId'], 10) === parseInt(arrGroupsToHide[i]['realGroupId'], 10)) {
                                                                    booFound = true;

                                                                    if (booNeedToHide) {
                                                                        arrGroupedGroupsToToggle[index]['booShow'] = false;
                                                                    }
                                                                }
                                                            });

                                                            if (!booFound) {
                                                                arrGroupedGroupsToToggle.push({
                                                                    group: arrGroupsToHide[i],
                                                                    booShow: !booNeedToHide
                                                                });
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    // Toggle groups/fields only once
                                    Ext.each(arrGroupedFieldsToToggle, function (oFieldConditionDetails) {
                                        thisPanel.toggleConditionalField(formMemberType, oFieldConditionDetails['field'], oFieldConditionDetails['booShow']);
                                    });

                                    Ext.each(arrGroupedGroupsToToggle, function (oGroupConditionDetails) {
                                        oGroupConditionDetails['group'].ownerCt.ownerCt.setVisible(oGroupConditionDetails['booShow']);
                                    });
                                }
                            });
                            break;
                    }
                }
            }
        }

        // Apply all listeners furing the field rendering event
        if (!empty(arrFieldListeners) && arrFieldListeners.length) {
            var oFieldExtraDetails = {
                listeners: {
                    'beforerender': function (field) {
                        Ext.each(arrFieldListeners, function (oEvent) {
                            field.on(oEvent.eventName, oEvent.eventMethod);
                        });
                    }
                }
            }

            Ext.apply(oFieldNewDetails, oFieldExtraDetails);
        }

        Ext.apply(oFieldDetails, oFieldNewDetails);


        // For already created applicant - change "login fields" to labels and show 'Change' link
        var thisFormId = '#' + thisPanel.getId();
        if (!empty(thisPanel.applicantId) && booHasEditAccess && (field.field_unique_id == 'username' || field.field_unique_id == 'password')) {
            oFieldDetails = {
                xtype:  'container',
                layout: 'table',
                cls: 'x-table-layout-cell-top-align',
                layoutConfig: {
                    columns: 2
                },

                items: [
                    {
                        xtype: 'displayfield',
                        style: oFieldDetails.labelStyle,
                        value: label
                    }, {
                        xtype: 'box',
                        style: 'padding-left: 10px; padding-top: 13px; display: block;',
                        autoEl: {
                            tag: 'a',
                            href: '#',
                            'class': 'blulinkunm x-form-field-value link-change-login-password',
                            html: _('Change')
                        },
                        listeners: {
                            scope:  this,
                            render: function (c) {
                                c.getEl().on('click', function() {
                                    var username = c.ownerCt.ownerCt.ownerCt.find('uniqueCls', 'username-value');
                                    var password = c.ownerCt.ownerCt.ownerCt.find('uniqueCls', 'password-value');
                                    var wnd = new ApplicantsProfileChangePasswordDialog({
                                        memberId: thisPanel.applicantId,
                                        usernameFieldId: username.length ? username[0].getId() : '',
                                        passwordFieldId: password.length ? password[0].getId() : ''
                                    }, thisPanel);

                                    wnd.show();
                                    wnd.center();
                                }, this, {stopEvent: true});
                            }
                        }
                    }, {
                        colspan:    2,
                        xtype:      'displayfield',
                        style:      'font-size: 12px',
                        uniqueCls:  field.field_unique_id + '-value',
                        name:       oFieldDetails.name,
                        hiddenName: oFieldDetails.hiddenName,
                        value:      field.field_unique_id == 'password' ? '' : '-'
                    }
                ]
            };
        }

        // Automatically generate password for the first time user changes/enters username
        if (empty(thisPanel.applicantId) && booHasEditAccess && field.field_unique_id == 'username') {
            var listeners = oFieldDetails.listeners || {};
            listeners = Ext.apply(listeners, {
                'keyup': function () {
                    var pf = $(thisFormId + ' .password-identifier');
                    if (pf.length && empty(pf.val())) {
                        pf.val(generatePassword());
                    }
                }
            });

            Ext.apply(oFieldDetails, {
                enableKeyEvents: true,
                listeners: listeners
            });
        }

        // Automatically set username (same as main email address) for the first time user changes/enters email
        // And additionally generate password, if needed
        if (empty(thisPanel.applicantId) && booHasEditAccess && field.field_unique_id == 'email') {
            Ext.apply(oFieldDetails, {
                listeners: {
                   'blur': function(emailField) {
                       var username = $(thisFormId + ' .username-identifier');
                       if (username) {
                           var usernameField = Ext.getCmp(username.attr('id'));
                           if (usernameField && empty(usernameField.getValue().trim()) && !empty(emailField.getValue()) && emailField.isValid()) {
                               usernameField.setValue(emailField.getValue());
                               usernameField.fireEvent('keyup');
                           }
                       }
                   }
                }
            });
        }

        if (field.field_unique_id == 'nomination_ceiling') {
            oFieldDetails.emptyText = _('No limit');
            oFieldDetails.autoWidth = false;
            oFieldDetails.width = 250;
            oFieldDetails.anchor = '';
        }

        var arrItems = [oFieldDetails];
        if (field.field_type == 'combo' && !booIsDependentGroup) {
            var oOtherField = {
                id: Ext.id(),
                name: 'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '_other[]',
                hiddenName: 'field_' + formMemberType + '_' + groupId + '_' + field.field_id + '_other[]',
                xtype: 'textfield',
                ctCls: 'no-top-padding',
                hideLabel: true,
                hidden: true
            };

            if (fieldWidth == 'auto') {
                oOtherField.autoWidth = true;
            } else {
                oOtherField.width = fieldWidth;
            }
            arrItems.push(oOtherField);
        }

        if (field.field_unique_id == 'nomination_ceiling' && !empty(thisPanel.caseId)) {
            arrItems.push({
                id: 'linked-cases-grid-' + thisPanel.caseId,
                xtype: 'ApplicantsProfileLinkedCasesGrid',
                style: 'border: 1px #E8EAEE solid',
                panelType: this.owner.panelType,
                applicantId: empty(thisPanel.caseEmployerId) ? thisPanel.applicantId : thisPanel.caseEmployerId,
                applicantName: thisPanel.applicantName,
                memberType: thisPanel.memberType,
                caseId: thisPanel.caseId,
                nominationCeilingFieldId: oFieldDetails.id,
                booAllowUpdateCases: booHasEditAccess && thisPanel.hasAccess(thisPanel.memberType, 'edit'),
                owner: thisPanel
            });
        }

        var oField = {
            xtype: 'container',
            layout: 'form',
            labelAlign: 'top',
            style: field.field_container_colspan ? 'grid-column: span ' + field.field_container_colspan + ';' : '',
            items: arrItems
        };

        if (field.field_container_width == 'auto') {
            oField.autoWidth = true;
        } else {
            oField.width = field.field_container_width;
        }

        if (field.field_container_style) {
            oField.style += field.field_container_style;
        }

        return oField;
    },

    /**
     * Get values of all linked fields and get sum of them, result value place in the auto calculated field
     *
     * @param autoCalcField
     * @param arrLinkedFieldIds
     */
    updateAutoCalcField: function (autoCalcField, arrLinkedFieldIds) {
        var thisPanel = this;
        var sum = 0, sourceFields, i, j, val;

        // Calculate sum from all linked fields
        for (i = 0; i < arrLinkedFieldIds.length; i++) {
            sourceFields = thisPanel.find('fieldUniqueName', arrLinkedFieldIds[i]['option_name']);
            for (j = 0; j < sourceFields.length; j++) {
                val = parseInt(sourceFields[j].getValue(), 10);
                sum += empty(val) || isNaN(val) ? 0 : val;
            }
        }

        if (!isNaN(autoCalcField.maxValue) && sum > autoCalcField.maxValue) {
            sum = autoCalcField.maxValue;
        } else if (!isNaN(autoCalcField.minValue) && sum < autoCalcField.minValue) {
            sum = autoCalcField.minValue;
        }

        // Set calculated value, fire "change" event manually because it is readonly
        var oldVal = autoCalcField.getValue();
        autoCalcField.setValue(sum);
        autoCalcField.fireEvent('change', autoCalcField, sum, oldVal);

        // Highlight auto calculated field
        autoCalcField.getEl().stopFx().highlight('FF8432', {
            attr:     'color',
            duration: 2
        });
    },

    sendRequestToReleaseNewCaseNumber: function() {
        var caseReferenceNumberFields = this.find('fieldUniqueName', 'file_number');
        var caseNumberField = caseReferenceNumberFields.length ? caseReferenceNumberFields[0] : '';

        if (!empty(caseNumberField) && caseNumberField.getValue()) {
            Ext.Ajax.request({
                url: baseUrl + '/applicants/profile/release-case-number',
                params: {
                    caseNumber: Ext.encode(caseNumberField.getValue())
                },

                success: function (f) {
                    // Silence...
                },

                failure: function () {
                    // Silence...
                }
            });
        }
    },

    sendRequestToGenerateNewCaseNumber: function(caseNumberField) {
        var thisPanel = this;
        this.mainForm.getForm().submit({
            url: baseUrl + '/applicants/profile/generate-case-number',
            waitMsg: _('Generating...'),
            clientValidation: false,

            success: function(form, action) {
                var res = action.result;
                if (!empty(res.newCaseNumber)) {
                    caseNumberField.setValue(res.newCaseNumber);
                }
            },

            failure: function(form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : 'Internal error.';

                if (action && action.result && action.result.arrErrorFields && action.result.arrErrorFields.length) {
                    thisPanel.markInvalidFieldsAndScroll(action.result.arrErrorFields);
                }
                Ext.simpleConfirmation.error(msg);
            }
        });
    },

    generateNewCaseNumber: function(caseNumberField) {
        var thisPanel = this,
            oldCaseNumber = caseNumberField.getValue();

        if (!empty(oldCaseNumber)) {
            var question = String.format(
                'Are you sure you want to overwrite the current value of {0}?',
                caseNumberField.realFieldLabel
            );
            Ext.Msg.confirm('Please confirm', question, function (btn) {
                if (btn === 'yes') {
                    thisPanel.sendRequestToGenerateNewCaseNumber(caseNumberField);
                }
            });
        } else {
            thisPanel.sendRequestToGenerateNewCaseNumber(caseNumberField);
        }
    },

    sendRequestToGenerateNewKsKey: function(ksKeyField) {
        var thisPanel = this;
        this.mainForm.getForm().submit({
            url: baseUrl + '/applicants/profile/generate-ks-key',
            waitMsg: _('Generating...'),
            clientValidation: false,

            success: function(form, action) {
                var res = action.result;
                if (!empty(res.newKsKey)) {
                    ksKeyField.setValue(res.newKsKey);
                }
            },

            failure: function(form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : 'Internal error.';

                if (action && action.result && action.result.arrErrorFields && action.result.arrErrorFields.length) {
                    thisPanel.markInvalidFieldsAndScroll(action.result.arrErrorFields);
                }
                Ext.simpleConfirmation.error(msg);
            }
        });
    },

    generateNewKsKey: function(ksKeyField) {
        var thisPanel = this,
            oldCaseNumber = ksKeyField.getValue();

        if (!empty(oldCaseNumber)) {
            var question = String.format(
                'Are you sure you want to overwrite the current value of {0}?',
                ksKeyField.realFieldLabel
            );
            Ext.Msg.confirm('Please confirm', question, function (btn) {
                if (btn === 'yes') {
                    thisPanel.sendRequestToGenerateNewKsKey(ksKeyField);
                }
            });
        } else {
            thisPanel.sendRequestToGenerateNewKsKey(ksKeyField);
        }
    },

    generateNewBcpnpNominationCertificateNumber: function(field, caseNumber) {
        var caseNumberParts = caseNumber.split(caseReferenceNumberSeparator);
        var lastCaseNumberPart = caseNumberParts[caseNumberParts.length - 1];
        var newBcpnpNominationCertificateNumber = new Date().getFullYear() + '-' + lastCaseNumberPart.replace(/\D/g, '');
        field.setValue(newBcpnpNominationCertificateNumber);
    },

    getNewBcpnpNominationCertificateNumber: function(field, caseNumberField) {
        var thisPanel = this,
            oldNumber = field.getValue();

        if (!caseNumberField || !caseNumberField.getValue()) {
            Ext.simpleConfirmation.warning('You need to generate ' + caseNumberField.realFieldLabel);
        } else {
            if (!empty(oldNumber)) {
                var question = String.format(
                    'Are you sure you want to overwrite the current value of {0}?',
                    field.realFieldLabel
                );
                Ext.Msg.confirm('Please confirm', question, function (btn) {
                    if (btn === 'yes') {
                        thisPanel.generateNewBcpnpNominationCertificateNumber(field, caseNumberField.getValue());
                    }
                });
            } else {
                thisPanel.generateNewBcpnpNominationCertificateNumber(field, caseNumberField.getValue());
            }
        }
    },

    getCaseTypeLMIALabel: function (caseType) {
        var thisPanel = this;
        var lmiaLabel = '';
        Ext.each(arrApplicantsSettings.case_templates, function (caseTemplate) {
            if (caseTemplate.case_template_id == caseType) {
                lmiaLabel = caseTemplate.case_template_case_reference_as;
                return false;
            }
        });

        if (empty(lmiaLabel)) {
            lmiaLabel = arrApplicantsSettings.default_case_template_case_reference_as;
        }

        return lmiaLabel;
    },

    createGroupsAndFields: function(arrAllGroupsAndFields, formMemberType) {
        var thisPanel = this;
        var arrGroups = [];

        var currentRowId = '';
        var previousBlockContact = '';
        var previousBlockRepeatable = '';
        Ext.each(arrAllGroupsAndFields, function(group){
            if (previousBlockContact != group.group_contact_block || previousBlockRepeatable != group.group_repeatable) {
                currentRowId = thisPanel.generateRowId();
            }
            previousBlockContact = group.group_contact_block;
            previousBlockRepeatable = group.group_repeatable;

            var columnsCount = empty(group.group_cols_count) ? 3 : parseInt(group.group_cols_count, 10);
            var arrFields = [];
            var booHiddenGroup = false;
            var booDependentsSection = false;
            if (group.group_title == 'Dependants') {
                columnsCount = 1;
                booDependentsSection = true;
                arrFields.push({
                    xtype: 'container',
                    items: [
                        {
                            xtype: 'container',
                            uniqueFieldId: 'dependents_container',
                            items: []
                        }, {
                            xtype: 'button',
                            scale: 'medium',
                            style: 'margin-top: 5px',
                            cls: 'main-btn',
                            text: '<i class="las la-plus"></i>' + _('Add Dependant'),
                            disabled: group.group_access != 'F',
                            group_access: group.group_access,
                            uniqueFieldId: 'dependents_container_add_button',
                            handler: function () {
                                thisPanel.booIsDirty = true;
                                thisPanel.addDependentRow(group);
                            }
                        }
                    ]
                });
            } else {
                var totalCellsUsed = 0;
                Ext.each(group.fields, function(field){
                    var newField = Ext.apply({}, field);
                    newField.field_disabled = field.field_disabled == 'Y' || group.group_repeatable == 'Y';
                    newField.field_width = '100%';
                    newField.field_container_width = 'auto';

                    if (newField.field_use_full_row) {
                        newField.field_container_style = 'padding-right: 15px;';

                        var rowsMustBe = Math.ceil((totalCellsUsed + 1) / columnsCount);
                        var columnsMustBeAdded = columnsCount * rowsMustBe - totalCellsUsed;

                        if (columnsMustBeAdded > 1) {
                            newField.field_container_colspan = columnsMustBeAdded;
                            totalCellsUsed += columnsMustBeAdded;
                        } else {
                            totalCellsUsed += 1;
                        }
                    } else {
                        // 275 - tabWidth
                        // 120 - additional paddings
                        var availableWidth = thisPanel.owner.getWidth() - 275 - 120;
                        newField.field_container_width = availableWidth / columnsCount;

                        totalCellsUsed++;
                    }

                    if ((newField.field_unique_id == 'username' || newField.field_unique_id == 'password') && !thisPanel.hasAccess(formMemberType, 'can_client_login') && formMemberType != 'case') {
                        booHiddenGroup = true;
                    }

                    if (newField.field_unique_id == 'nomination_ceiling') {
                        newField.field_name = String.format(
                            'Number of {0} Cases Approved',
                            thisPanel.getCaseTypeLMIALabel(thisPanel.caseType)
                        );
                    }

                    arrFields.push(thisPanel.generateField(group.group_id, newField, formMemberType, empty(thisPanel.caseId) || empty(thisPanel.caseType)));
                });
            }

            var rowIdentifier = {
                xtype: 'hidden',
                name:  formMemberType + '_group_row_' + group.group_id + '[]',
                value: currentRowId
            };

            var arrCls = ['applicants-profile-fieldset-cloned'];
            if (booDependentsSection) {
                arrCls.push('applicants-profile-fieldset-cloned-no-top-padding');
            }

            if (group.group_show_title !== 'Y') {
                arrCls.push('applicants-profile-fieldset-cloned-no-top-right-padding');
            }

            var currentGroup = new Ext.form.FieldSet({
                cls: implode(' ', arrCls),
                title: group.group_repeatable == 'Y' ? 'delete' : null,
                titleCollapse: false,
                collapsible: true,
                autoHeight: true,
                hidden: group.group_repeatable == 'Y',
                realGroupId: parseInt(group.group_id, 10),

                items: [
                    rowIdentifier,
                    {
                        xtype: 'container',
                        style: 'display: grid; grid-template-columns: ' + (' 1fr').repeat(columnsCount),
                        items: arrFields
                    }
                ],
                listeners: {
                    'beforecollapse': function(fieldset) {
                        var msg = 'Removing this row will remove the corresponding data in related cases.<br/><br/>' +
                                  'Are you sure you want to continue?';
                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                            if (btn == 'yes') {
                                fieldset.setVisible(false);

                                // Disable all inner fields: to prevent fields submitting + allow form pass validation
                                fieldset.cascade(function () {
                                    var id = this.id;
                                    if ($('#' + id).hasClass('form-file')) {
                                        $('#' + id).find('.form-file-view').hide();
                                        $('#' + id).find('.form-file-edit').show();
                                        $('#' + id).find('.form-file-edit a[data-rel=cancel]').hide();
                                    } else if ($('#' + id).hasClass('form-image')) {
                                        $('#' + id).find('.form-image-view').hide();
                                        $('#' + id).find('.form-image-edit').show();
                                        $('#' + id).find('.form-image-edit a[data-rel=cancel]').hide();
                                    }
                                    if(typeof this.reset === 'function') {
                                        this.reset();
                                    }
                                    this.disable();
                                });

                                thisPanel.owner.owner.fixParentPanelHeight();
                            }
                        });

                        return false;
                    }
                }
            });

            var currentGroupContainer = new Ext.form.FieldSet({
                id: formMemberType + '-applicant-' + thisPanel.applicantId + '-' + thisPanel.caseId + '-section-' + group.group_id,
                cls: 'applicants-profile-fieldset' + (group.group_collapsed == 'Y' ? ' group_collapsed' : ''),
                title: group.group_show_title == 'Y' ? group.group_title : null,
                titleCollapse: true,
                collapsible: group.group_show_title == 'Y',
                autoHeight: true,
                collapsed: false,
                style: 'width: 100%',
                hidden: booHiddenGroup,

                items: [
                    {
                        xtype: 'container',
                        items: currentGroup
                    }, {
                        xtype: 'container',
                        hidden: group.group_repeatable != 'Y',
                        cls: 'applicants-profile-add-section-container',
                        items: {
                            xtype: 'button',
                            scale: 'medium',
                            cls: 'main-btn',
                            text: '<i class="las la-plus"></i>' + _('Add ') + group.group_title,
                            handler: function() {
                                thisPanel.createSectionCopy(currentGroup, false);
                            }
                        }
                    }
                ],

                listeners: {
                    'afterrender': function () {
                        // For all fields - listen for "change event"
                        // and mark the form as dirty - show a confirmation when is needed
                        for (var i = 0; i < arrFields.length; i++) {
                            if (!empty(arrFields[i].items)) {
                                for (var j = 0; j < arrFields[i].items.length; j++) {
                                    if (!empty(arrFields[i].items[j]['fieldUniqueName'])) {
                                        var arrFieldsToCheck = thisPanel.find('fieldUniqueName', arrFields[i].items[j]['fieldUniqueName']);
                                        if (arrFieldsToCheck.length) {
                                            arrFieldsToCheck[0].addListener('change', function () {
                                                // Mark the form as dirty if value was changed
                                                thisPanel.booIsDirty = true;
                                            });
                                        }
                                    }
                                }
                            }
                        }
                    },

                    'collapse': function() {
                        thisPanel.owner.owner.fixParentPanelHeight();
                    },

                    'expand': function(fieldset) {
                        /*
                        // Collapse other fieldsets
                        var currentFieldsetId = fieldset.getId();
                        var arrCreatedFieldSets = thisPanel.mainForm.getEl().query('fieldset');
                        Ext.each(arrCreatedFieldSets, function(foundFieldset){
                            var foundFieldsetId = $(foundFieldset).attr('id');
                            if ($(foundFieldset).hasClass('applicants-profile-fieldset') && currentFieldsetId != foundFieldsetId) {
                                var cmp = Ext.getCmp(foundFieldsetId);
                                if (cmp) {
                                    cmp.collapse();
                                }
                            }
                        });
                        */
                        thisPanel.owner.owner.fixParentPanelHeight();
                    }
                }
            });

            // Don't show group if there are no fields in it
            if (arrFields.length) {
                arrGroups.push(currentGroupContainer);
            }
        });

        return arrGroups;
    },

    createSectionCopy: function(group, booLoad) {
        var thisPanel = this;

        if (!group.isVisible() && (booLoad || group.ownerCt.items.getCount() < 2)) {
            $('#' + group.id).addClass('first-section applicants-profile-fieldset-cloned-border');
            group.setVisible(true);

            // Also, automatically enable all fields
            group.cascade(function () {
                this.enable();
            });

            // Update changed height
            this.owner.owner.fixParentPanelHeight();

            return;
        }

        // Generate a clone of the fieldset with custom changes
        var newGroup = new Ext.form.FieldSet(group.cloneConfig({
            cls: 'applicants-profile-fieldset-cloned applicants-profile-fieldset-cloned-border',
            hidden: false,
            listeners: {
                'beforecollapse': function (panel) {
                    var fieldset = panel;

                    var msg = 'Removing this row will remove the corresponding data in related cases.<br/><br/>' +
                        'Are you sure you want to continue?';
                    Ext.Msg.confirm('Please confirm', msg, function (btn) {
                        if (btn == 'yes') {
                            Ext.fly(fieldset.getEl()).slideOut('t', {
                                duration: 0.5,
                                remove: false,
                                callback : function() {
                                    fieldset.ownerCt.remove(fieldset);
                                    thisPanel.owner.owner.fixParentPanelHeight();
                                }
                            });

                        }
                    });

                    return false;
                }
            }
        }));

        // Automatically enable all fields
        newGroup.cascade(function () {
            this.enable();
        });


        // For some f... reason store is not copied....
        var arrComboboxes = newGroup.find('xtype', 'combo');
        Ext.each(arrComboboxes, function(newCombo) {
            var oldCombo = group.find('name', newCombo.name);
            if (oldCombo && oldCombo.length) {
                newCombo.store = oldCombo[0].store;
            }
        });

        // Generate unique row id
        var arrRowIds = newGroup.find('xtype', 'hidden');
        Ext.each(arrRowIds, function(rowIdField) {
            if (/^(\w+)_group_row_(\d+)\[\]$/i.test(rowIdField.name)) {
                rowIdField.setValue(thisPanel.generateRowId());
            }
        });

        // Add a clone to the form, show it
        group.ownerCt.insert(group.ownerCt.items.getCount(), newGroup);
        group.ownerCt.doLayout();

        // Update changed height
        this.owner.owner.fixParentPanelHeight();
    },

    getCurrentApplicantTabName: function() {
        var tabName = '';
        var tabId = this.owner.panelType + '-tab-panel__' + this.getCurrentTabId();
        var currentTab = Ext.get(tabId);

        if (currentTab) {
            var currentApplicantTabTitle = currentTab.query('.tab-title-applicant-name');
            var currentCaseTabTitle = currentTab.query('.tab-title-case-name');
            if (currentApplicantTabTitle.length) {
                tabName += $(currentApplicantTabTitle[currentApplicantTabTitle.length - 1]).html();
            }

            if (currentCaseTabTitle.length) {
                tabName += ' ' + $(currentCaseTabTitle[currentCaseTabTitle.length - 1]).html().replace('(', '').replace(')', '');
            }
        }

        return tabName;
    },

    setCurrentApplicantTabName: function(employerName, applicantName, caseName, caseType, booUpdateAll) {
        var tabId = this.owner.panelType + '-tab-panel__' + this.getCurrentTabId();
        var currentTab = Ext.get(tabId);
        var thisPanel = this;

        if (this.memberType == 'employer' && !empty(caseType)) {
            applicantName = employerName + ' | ' + this.owner.owner.getCaseTypeNameByCaseTypeId(caseType);
        } else if (!empty(employerName) && applicantName != employerName) {
            applicantName = employerName + ' | ' + applicantName;
        }

        if (currentTab) {
            var currentApplicantTabTitle = currentTab.query('.tab-title-applicant-name');
            var currentCaseTabTitle = currentTab.query('.tab-title-case-name');
            if (booUpdateAll || this.memberType != 'case') {
                // If case name wasn't provided - use already showed in the tab
                if (empty(caseName)) {
                    caseName = $(currentCaseTabTitle[currentCaseTabTitle.length - 1]).html();
                    if (caseName) {
                        caseName = caseName.replace('(', '').replace(')', '');
                    }
                }

                if (currentApplicantTabTitle.length) {
                    $(currentApplicantTabTitle[currentApplicantTabTitle.length - 1]).html(applicantName);
                }

                if (currentCaseTabTitle.length) {
                    caseName = empty(caseName) ? '' : '(' + caseName + ')';
                    $(currentCaseTabTitle[currentCaseTabTitle.length - 1]).html(caseName);
                    thisPanel.owner.highlightTitles(currentTab);
                }
            } else {
                if (currentCaseTabTitle.length) {
                    if (caseName == '') {
                        caseName == _('Case 1');
                    } else {
                        caseName = empty(caseName) ? '' : '(' + caseName + ')';
                    }

                    $(currentCaseTabTitle[currentCaseTabTitle.length - 1]).html(caseName);
                    thisPanel.owner.highlightTitles(currentTab);
                }
            }

            // We need this to update the selected client's name in the TabUniquesMenu
            var applicantsTab = Ext.getCmp(this.owner.panelType + '-tab-panel');
            applicantsTab.fireEvent('titlechange', applicantsTab);
        }
    },

    updateCaseInfo: function(resultData, booRefreshCasesList) {
        var thisPanel = this;

        // Load 'case id'
        var caseIdField = thisPanel.find('name', 'caseId');
        if (caseIdField.length) {
            if (resultData.caseId) {
                thisPanel.caseId = resultData.caseId;
                caseIdField[0].setValue(resultData.caseId);
            } else {
               // Case id is empty - means that we want to create a new Case
                thisPanel.caseId = 0;
                caseIdField[0].setValue(0);
            }
        }

        // Load 'case name'
        var caseNameField = thisPanel.find('name', 'caseName');
        if (caseNameField.length && resultData.caseName) {
            thisPanel.caseName = resultData.caseName;
            caseNameField[0].setValue(resultData.caseName);
        }

        // Load 'Immigration Program'
        var caseTypeField = thisPanel.find('name', 'caseType');
        if (caseTypeField.length && resultData.caseType) {
            thisPanel.caseType = resultData.caseType;
            if (resultData.caseType != caseTypeField[0].getValue()) {
                caseTypeField[0].setValue(resultData.caseType);
                thisPanel.createCaseGroupsAndFields(resultData.caseType);
            }
        }

        // Load 'case employer id'
        var caseEmployerIdField = thisPanel.find('name', 'caseEmployerId');
        if (caseEmployerIdField.length && resultData.caseEmployerId) {
            thisPanel.caseEmployerId = resultData.caseEmployerId;
            thisPanel.caseEmployerName = resultData.caseEmployerName;

            caseEmployerIdField[0].setValue(resultData.caseEmployerId);
        }
    },

    setComboValueAndFireBeforeSelectEvent: function(combo, value) {
        var record;
        combo.store.each(function (rec) {
            if (rec.data[combo.valueField] == value) {
                record = rec;
                return false;
            }
        });

        if (record) {
            combo.fireEvent('beforeselect', combo, record, value, true);
            combo.setValue(value);
        }
    },

    setLoadedDependentsData: function(resultData) {
        var thisPanel = this;
        var dependentsGroup;

        var dependantGroupAccess = '';
        if (resultData.caseType && arrApplicantsSettings.case_group_templates && arrApplicantsSettings.case_group_templates[resultData.caseType]) {
            Ext.each(arrApplicantsSettings.case_group_templates[resultData.caseType], function(group){
                if (group.group_title == 'Dependants') {
                    dependentsGroup = group;
                    dependantGroupAccess = group.group_access;
                }
            });
        }

        if (empty(dependantGroupAccess)) {
            // if there are no Dependant group or no access to it - don't try to fill the fields
            return;
        }

        // Calculate how many rows must be created
        var totalRows = 0;
        if (resultData.fields['field_case_dependants_relationship']) {
            totalRows = resultData.fields['field_case_dependants_relationship'].length;
        }

        // Add required rows
        for (var i = 0; i < totalRows; i++) {
            thisPanel.addDependentRow(dependentsGroup, resultData.fields.field_case_dependants_dependent_id);
        }

        // Set received values
        $.each(resultData.fields, function (fieldName, arrFieldValues) {
            var match = fieldName.match(/^field_case_dependants_(\w+)$/i);
            if (match != null && arrFieldValues.length) {
                var arrFields = thisPanel.find('name', fieldName + '[]');
                if (arrFields.length) {
                    for (var j = 0; j < arrFieldValues.length; j++) {
                        if (arrFieldValues[j] != '') {
                            thisPanel.fillFieldData(arrFields[j], arrFieldValues[j], null, null, resultData.fields.field_case_dependants_dependent_id[j]);
                        }
                    }
                }
            }
        });
    },

    fillFieldData: function(field, fieldVal, fieldId, currentApplicantId, dependentId) {
        var thisPanel = this;

        switch (field.getXType()) {
            case 'combo':
                var optionName;
                switch (field.comboType) {
                    case 'related_case_selection':
                        optionName = '';
                        break;

                    default:
                        optionName = 'option_name';
                }

                var userFieldTypes = ["assigned_to", "staff_responsible_rma", "active_users"];

                if (userFieldTypes.indexOf(field.comboType) != -1) {
                    var usersList = [];

                    Ext.each(arrApplicantsSettings.options['general'][field.comboType], function(oData){
                        if (parseInt(oData['status']) == 1 || fieldVal == oData['option_id']) {
                            usersList.push([oData['option_id'], oData['option_name']]);
                        }
                    });

                    var newStore = new Ext.data.SimpleStore({
                        fields: ['option_id', 'option_name', 'option_deleted'],
                        data: empty(usersList) ? [
                            [0, '', false]
                        ] : usersList
                    });
                    field.bindStore(newStore);
                }

                // Refresh the list of options in the combo with a filtered list of options
                if (typeof field.store.all_data !== 'undefined' && field.fieldUniqueName !== 'file_status') {
                    var arrComboDataWithoutDeleted = [];
                    Ext.each(field.store.all_data, function (r) {
                        // Skip deleted options only if it is not in the saved value
                        if (typeof r.option_deleted === 'undefined' || !r.option_deleted || r[field.valueField] == fieldVal) {
                            arrComboDataWithoutDeleted.push(r);
                        }
                    });

                    field.store.loadData(arrComboDataWithoutDeleted);
                }


                var indexOther = field.store.find(optionName, 'Other');
                var indexValue = field.store.find(field.valueField, fieldVal);

                if (indexOther != -1 && indexValue == -1) {
                    // Set 'Other' value in the combo
                    var rec = field.store.getAt(indexOther);
                    field.setValue(rec.data.option_id);

                    // Show other field
                    var otherField = field.ownerCt.items.itemAt(1);
                    if (otherField && otherField.getXType() == 'combo') {
                        otherField.setVisible(true);
                        // Set received value
                        otherField.setValue(fieldVal);
                    }
                } else if (indexValue != -1) {
                    thisPanel.setComboValueAndFireBeforeSelectEvent(field, fieldVal);
                    field.fireEvent('keyup', field);
                }

                if (field.fieldUniqueName === 'real_estate_project' && thisPanel.booGovernmentFund) {
                    field.setDisabled(true);
                    field.setValue('-');
                    field.allowBlank = true;
                    $('label[for="' + field.id + '"]').html(field.realFieldLabel);
                }

                break;

            case 'lovcombo':
                try {
                    var arrComboValues = fieldVal.split(field.separator);

                    // Refresh the list of options in the multiple combo with a filtered list of options
                    if (typeof field.store.all_data !== 'undefined' && field.fieldUniqueName !== 'file_status') {
                        var arrMultipleComboDataWithoutDeleted = [];
                        Ext.each(field.store.all_data, function (r) {
                            // Skip deleted options only if it is not in the saved value
                            if (typeof r.option_deleted === 'undefined' || !r.option_deleted || arrComboValues.has(r[field.valueField])) {
                                arrMultipleComboDataWithoutDeleted.push(r);
                            }
                        });

                        field.store.loadData(arrMultipleComboDataWithoutDeleted);
                    }

                    var arrCorrectValues = [];
                    Ext.each(arrComboValues, function(fieldVal) {
                        var indexValue = field.store.find(field.valueField, fieldVal);
                        if (indexValue != -1) {
                            arrCorrectValues.push(fieldVal);
                        }
                    });

                    if (arrCorrectValues.length) {
                        field.setValue(implode(field.separator, arrCorrectValues));
                        field.fireEvent('select', field);
                    }
                } catch (e) {
                }
                break;

            case 'checkbox':
                if (field.fieldUniqueName === 'main_applicant_address_is_the_same' || field.fieldUniqueName === 'include_in_minute_checkbox') {
                    field.setValue(fieldVal === 'Y');
                } else {
                    field.setValue(1);
                }
                break;

            case 'displayfield':
                if (field.realFieldType == 'office_change_date_time') {
                    thisPanel.setOfficeChangeValue(field, fieldVal);
                } else if (field.realFieldType == 'date' || field.realFieldType == 'date_repeatable') {
                    if (fieldVal.length) {
                        // Value can be passed in such format: 08-May-1976
                        // In such case we cannot convert to the date
                        try {
                            var dateFieldValues = fieldVal.split('-');
                            var date = new Date(dateFieldValues[0], dateFieldValues[1] - 1, dateFieldValues[2]);
                            fieldVal = date.format(dateFormatFull);
                        } catch (e) {
                        }
                        field.setValue(fieldVal);
                    }
                } else {
                    field.setValue(fieldVal);
                }
                break;

            case 'datefield':
                try {
                    if (fieldVal.length) {
                        // Date can be in 2 formats: yyyy-mm-dd hh:mm:ss OR yyyy-mm-dd
                        var matches = fieldVal.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
                        if (matches === null) {
                            matches = fieldVal.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        }

                        if (matches !== null) {
                            var dt = new Date(matches[1], matches[2] - 1, matches[3]);
                            field.setValue(dt.format(dateFormatFull));
                        }
                    }
                } catch (e) {
                }
                break;

            default:
                var memberId = thisPanel.memberType == 'case' ? thisPanel.caseId : currentApplicantId;

                if (field.initialConfig.cls == 'form-image') {
                    if (!empty(dependentId)) {
                        thisPanel.updateDependentImageDetails(memberId, dependentId, field.id)
                    } else {
                        thisPanel.updateImageDetails(memberId, fieldId, field.id);
                    }
                } else if (field.initialConfig.cls == 'form-file') {
                    thisPanel.updateFileDetails(memberId, fieldId, field.id, fieldVal);
                } else if (field.realFieldType == 'reference') {
                    field.setValue(fieldVal.value);
                    thisPanel.initMultipleReferenceFields(field.id, fieldVal);
                } else {
                    // Date fields can be showed as displayfields (read only)
                    // so we need format date value correctly
                    if (fieldVal && fieldVal.length && (field.realFieldType == 'date' || field.realFieldType == 'date_repeatable')) {
                        try {
                            var realDateValues = fieldVal.split('-');
                            var realDate = new Date(realDateValues[0], realDateValues[1] - 1, realDateValues[2]);
                            fieldVal = realDate.format(dateFormatFull);
                        } catch (e) {
                        }
                    }

                    // A trick to detect data loading event...
                    if (field.fieldUniqueName === 'third_country_visa') {
                        field.autoValueFill = true;
                    }

                    if (field.fieldUniqueName === 'cbiu_investment_type' && fieldVal == arrApplicantsSettings.government_fund_option_id) {
                        var realEstateProjectFields = thisPanel.find('fieldUniqueName', 'real_estate_project');
                        realEstateProjectFields[0].setDisabled(true);
                        realEstateProjectFields[0].setValue('-');
                        realEstateProjectFields[0].allowBlank = true;
                        $('label[for="' + realEstateProjectFields[0].id + '"]').html(realEstateProjectFields[0].realFieldLabel);
                        thisPanel.booGovernmentFund = true;
                    }

                    field.setValue(fieldVal);
                    field.fireEvent('keyup', field);
                }
        }
    },

    setOfficeChangeValue: function(field, fieldVal) {
        try {
            if (fieldVal !== '') {
                var dateTimeValues = fieldVal.split(' ');
                var dateValues = dateTimeValues[0].split('-');
                var dt = new Date(dateValues[0], dateValues[1] - 1, dateValues[2]);
                var date = dt.format(dateFormatFull);
                field.setValue(date + ' ' + dateTimeValues[1]);
            } else {
                field.setValue('-');
            }
        } catch (e) {
        }
    },

    refreshOptionsList: function() {
        var thisPanel = this;
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/load',
            params: {
                applicantId:         Ext.encode(thisPanel.applicantId),
                caseId:              Ext.encode(thisPanel.caseId),
                caseType:            Ext.encode(empty(thisPanel.caseType) ? 0 : thisPanel.caseType),
                caseEmployerId:      Ext.encode(empty(thisPanel.caseEmployerId) ? 0 : thisPanel.caseEmployerId),
                booLoadCaseInfoOnly: Ext.encode(true)
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    // Load additional comboboxes options (data related to the current case/IA/Employer only)
                    // before data will be set to the combos
                    $.each(resultData.arrAdditionalOptions, function (type, arrData) {
                        if (arrData.length) {
                            var arrFields = thisPanel.mainForm.find('comboType', type);
                            Ext.each(arrFields, function(combo) {
                                combo.getStore().loadData(arrData);
                            });
                        }
                    });
                }
            },

            failure: function () {
                // Silence...
            }
        });
    },

    updateClientInfoEverywhere: function (res) {
        var thisPanel = this;

        // Update current tab title (it can be changed)
        thisPanel.setCurrentApplicantTabName(res.caseEmployerName, res.applicantName, res.caseName, res.caseType);

        // Update 'created/updated on' info
        thisPanel.updateDetailsSection(res.applicantUpdatedOn);

        // Update 'updated on' time
        var updatedOnField = thisPanel.find('name', 'applicantUpdatedOn');
        if (updatedOnField.length) {
            updatedOnField[0].setValue(res.applicantUpdatedOnTime);
        }

        // Reset 'force profile update'
        var forceOverwriteField = thisPanel.find('name', 'forceOverwrite');
        if (forceOverwriteField.length) {
            forceOverwriteField[0].setValue(0);
        }

        if (thisPanel.booChangedCaseType) {
            // Find the combo, disable it and show the 'Change' link
            var caseTypeCombo = thisPanel.find('fieldUniqueName', 'case_type');
            if (caseTypeCombo.length) {
                caseTypeCombo = caseTypeCombo[0];
                var label = caseTypeCombo.getEl().up('div.x-form-item').child('a', true);
                if (label) {
                    caseTypeCombo.setDisabled(true);
                    Ext.get(label).show();
                }
            }

            thisPanel.booChangedCaseType = false;
        }

        thisPanel.toggleLinkToEmployerButton(res);

        thisPanel.updateCaseInfo(res, true);

        // Automatically set new offices (from child applicants)
        thisPanel.updateOffices(res.applicantOfficeFields, res.applicantOffices);

        // Maybe we need refresh image fields
        if (res.imageFieldsToUpdate) {
            thisPanel.toggleImageSection(res.imageFieldsToUpdate);
        }

        if (res.fileFieldsToUpdate) {
            thisPanel.toggleFileSection(res.fileFieldsToUpdate);
        }

        // Update 'office changed on' field(s) values
        if (res.changeOfficeFieldToUpdate && res.changeOfficeFieldToUpdate['value']) {
            var allFields = arrApplicantsSettings.groups_and_fields[thisPanel.memberType][0]['fields'];
            Ext.each(allFields, function (oGroup) {
                Ext.each(oGroup['fields'], function (oFieldInfo) {
                    if (oFieldInfo['field_type'] == 'office_change_date_time' && oFieldInfo['field_id'] == res.changeOfficeFieldToUpdate['field_id']) {
                        var realFieldName     = 'field_' + thisPanel.memberType + '_' + oGroup['group_id'] + '_' + oFieldInfo['field_id'] + '[]';
                        var arrFieldsToUpdate = thisPanel.find('name', realFieldName);
                        Ext.each(arrFieldsToUpdate, function (oFieldToUpdate) {
                            thisPanel.setOfficeChangeValue(oFieldToUpdate, res.changeOfficeFieldToUpdate['value']);
                        });
                    }
                });
            });
        }

        if (!empty(res.caseId) && allowedClientSubTabs.has('accounting')) {
            var clientAccountingPanel = Ext.getCmp('accounting_invoices_panel_' + res.caseId);
            if (clientAccountingPanel) {
                clientAccountingPanel.refreshAccountingTab();
            }
        }

        // Reload quick search result + tasks panel
        thisPanel.owner.owner.refreshClientsList(thisPanel.panelType, thisPanel.applicantId, res.caseId, true);
    },

    makeReadOnlyClient: function () {
        var thisPanel = this;

        // Disable all fields
        // Remove possibility to Save/Delete client
        // Don't allow to change the Office
        // Don't allow to add a new case
        thisPanel.profileToolbar.makeReadOnly();
        thisPanel.owner.applicantsCasesNavigationPanel.addNewCaseButton.setVisible(false);

        var parent = $('#' + thisPanel.getId());
        parent.find('a[data-rel=change]').hide();
        parent.find('a[data-rel=remove]').hide();
        parent.find('a.link-change-login-password').hide();
        parent.find('a.link-generate-file-number').hide();
        parent.find('a.link-change-case-type').hide();
        thisPanel.owner.booCanEdit = false;

        //Disable parent if submit child
        var parentTabToDisable = Ext.getCmp(thisPanel.panelType + '-tab-' + thisPanel.caseEmployerId);
        if (parentTabToDisable) {
            var applicantProfileTabPanel = parentTabToDisable.items.first();
            applicantProfileTabPanel.applicantsProfileForm.profileToolbar.makeReadOnly();
            applicantProfileTabPanel.individualProfileForm.profileToolbar.makeReadOnly();

            var casesGrid = parentTabToDisable.findByType('ApplicantsCasesGrid')[0];
            if (casesGrid) {
                casesGrid.makeReadOnly();
            }
            applicantProfileTabPanel.booCanEdit = false;
        }

        //Disable another child tabs
        var applicantsTab = Ext.getCmp(thisPanel.panelType + '-tab-panel');
        if (applicantsTab) {
            applicantsTab.items.each(function(currentItem){
                var childProfileForm = currentItem.items.first().applicantsProfileForm;
                if (childProfileForm && (currentItem.applicantId === thisPanel.caseEmployerId || thisPanel.applicantId === currentItem.applicantId)) {
                    childProfileForm.profileToolbar.makeReadOnly();
                    if (!empty(childProfileForm.owner.caseProfileForm)) {
                        childProfileForm.owner.caseProfileForm.profileToolbar.makeReadOnly();
                    }

                    if (!empty(childProfileForm.owner.individualProfileForm)) {
                        childProfileForm.owner.individualProfileForm.profileToolbar.makeReadOnly();
                    }

                    if (childProfileForm.caseId) {
                        if (Ext.getCmp('forms-main-grid' + childProfileForm.caseId)) {
                            Ext.getCmp('forms-main-grid' + childProfileForm.caseId).makeReadOnly();
                        }

                        var docsTree = Ext.getCmp('docs-tree-' + childProfileForm.caseId);
                        if (docsTree) {
                            // Refresh client documents list
                            docsTree.getRootNode().reload();
                        }

                        var clientAccountingPanel = Ext.getCmp('accounting_invoices_panel_' + childProfileForm.caseId);
                        if (clientAccountingPanel) {
                            clientAccountingPanel.makeReadOnlyAccountingTab();
                        }
                    }

                    parent = $('#' + childProfileForm.getId());
                    parent.find('a[data-rel=change]').hide();
                    parent.find('a[data-rel=remove]').hide();
                    parent.find('a.link-change-login-password').hide();
                    parent.find('a.link-generate-file-number').hide();
                    parent.find('a.link-change-case-type').hide();

                    if (!empty(childProfileForm.owner.individualProfileForm)) {
                        parent = $('#' + childProfileForm.owner.individualProfileForm.getId());
                        parent.find('a[data-rel=change]').hide();
                        parent.find('a[data-rel=remove]').hide();
                        parent.find('a.link-change-login-password').hide();
                        parent.find('a.link-generate-file-number').hide();
                        parent.find('a.link-change-case-type').hide();
                    }

                    childProfileForm.owner.booCanEdit = false;
                }
            });
        }

        var warning = thisPanel.find('uniqueFieldId', 'case_section_read_only_warning');
        if (warning.length) {
            warning[0].setVisible(true);
            warning[0].ownerCt.setVisible(true);
        }
    },

    showLoadedApplicantData: function(resultData, booRefreshCasesList, booIsNewCase) {
        var thisPanel = this;

        if (!resultData.booCanEdit) {
            thisPanel.makeReadOnlyClient();
            thisPanel.owner.booCanEdit = false;
        }

        if (resultData.booSubmitted) {
            var arrSubmitToGovernmentButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-submit-to-government');
            if (arrSubmitToGovernmentButtons.length) {
                arrSubmitToGovernmentButtons[0].setVisible(false);
            }
        }

        if (!booIsNewCase) {
            if (resultData.applicantType) {
                var applicantTypeField = thisPanel.find('name', 'applicantType')[0];
                applicantTypeField.setValue(resultData.applicantType);

                if (thisPanel.memberType === 'contact') {
                    thisPanel.createContactGroupsAndFields();
                }
            }


            var caseTypeField = thisPanel.find('name', 'caseType');
            var currentCaseType;
            if (caseTypeField.length) {
                currentCaseType = caseTypeField[0].getValue();
            }

            if (thisPanel.booShowChangeCaseTypeLink) {
                thisPanel.mainForm.getForm().reset();
            }

            if (caseTypeField.length) {
                caseTypeField[0].setValue(currentCaseType);
            }


            if (thisPanel.memberType === 'case') {
                thisPanel.updateCaseInfo(resultData, booRefreshCasesList);
            }

            if (resultData.caseEmployerId != thisPanel.applicantId) {
                if (thisPanel.memberType === 'individual') {
                    thisPanel.createCaseGoToEmployerButton(
                        resultData.caseEmployerId,
                        resultData.caseEmployerName,
                        resultData.applicantId,
                        resultData.applicantName,
                        resultData.caseName,
                        resultData.caseType
                    );
                } else if (thisPanel.memberType === 'case' && !empty(resultData.caseId)) {
                    thisPanel.createCaseGoToEmployerButton(
                        resultData.caseEmployerId,
                        resultData.caseEmployerName,
                        thisPanel.applicantId,
                        thisPanel.applicantName,
                        resultData.caseName,
                        resultData.caseType
                    );

                    if (empty(resultData.caseEmployerId)) {
                        var booCanSave = typeof arrApplicantsSettings !== 'undefined' && typeof arrApplicantsSettings['access'][thisPanel.memberType] !== 'undefined' && arrApplicantsSettings['access'][thisPanel.memberType].has('edit') && arrApplicantsSettings['access']['change_case_type'];
                        var caseTypeLink = thisPanel.getUniqueField('case_section_case_type_link');
                        if (caseTypeLink && booCanSave) {
                            caseTypeLink.setVisible(true);
                        }
                    }
                }
            }

            if (!empty(thisPanel.caseId)) {
                thisPanel.createRelatedCaseSection(resultData.arrCasesWithParents);
            }

            // Load dependents section
            if (thisPanel.memberType === 'case') {
                thisPanel.setLoadedDependentsData(resultData);
            }
        }

        if (thisPanel.memberType === 'case') {
            // Show/set the Case Category (the list of options depends on the Case Type and saved value)
            var caseCategoriesCombo = thisPanel.find('fieldUniqueName', 'categories');
            if (caseCategoriesCombo.length) {
                var store = caseCategoriesCombo[0].getStore();
                store.loadData(thisPanel.getCategoriesByCaseType(resultData.caseType, resultData.caseCategory));
            }

            // Show/set the Case Category (the list of options depends on the Case Type and saved value)
            var caseStatusesCombo = thisPanel.find('fieldUniqueName', 'file_status');
            if (caseStatusesCombo.length) {
                var store = caseStatusesCombo[0].getStore();
                store.loadData(thisPanel.getCaseStatusesByCaseSettings(resultData.caseType, resultData.caseCategory, resultData.caseStatus));
            }
        }

        thisPanel.toggleLinkToEmployerButton(resultData);

        // Check if we need show 'Vevo Check' and 'Generate CON' buttons in toolbar
        if (!empty(resultData.caseId) && !empty(resultData.caseType) && resultData.caseEmployerId != thisPanel.applicantId && thisPanel.profileToolbar) {
            if (thisPanel.memberType == 'case' && arrApplicantsSettings.access.vevo) {
                var arrVevoButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-vevo-check');
                if (arrVevoButtons.length) {
                    arrVevoButtons[0].setVisible(true);
                }
            }

            if (arrApplicantsSettings.access.generate_con) {
                var arrGenerateConButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-generate-con');
                if (arrGenerateConButtons.length) {
                    arrGenerateConButtons[0].setVisible(true);
                }
            }

            if (arrApplicantsSettings.access.generate_pdf_letter) {
                var arrGenerateComfortLetterButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-generate-pdf-letter');
                if (arrGenerateComfortLetterButtons.length) {
                    arrGenerateComfortLetterButtons[0].setVisible(true);
                }
            }
        }

        if (thisPanel.memberType === 'case' && !empty(resultData.caseId) && !empty(resultData.caseType) && resultData.casesCount > 1) {
            var arrViewOtherCasesButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-view-other-cases');
            if (arrViewOtherCasesButtons.length) {
                arrViewOtherCasesButtons[0].setVisible(true);
            }
        }

        // Add missing groups
        var arrGroups = resultData.rowIds;
        for (var currentGroupId in arrGroups) {
            if (arrGroups.hasOwnProperty(currentGroupId)) {
                var match = currentGroupId.match(/^group_(\d+)$/i);
                if (match != null && arrGroups[currentGroupId].length) {
                    var mainGroup = Ext.getCmp(thisPanel.memberType + '-applicant-' + thisPanel.applicantId + '-' + thisPanel.caseId + '-section-' + match[1]);
                    if (mainGroup && mainGroup.items && mainGroup.items.getCount()) {
                        var group = mainGroup.items.get(0).items.get(0);
                        var addButtonContainer = mainGroup.items.get(1);
                        if (addButtonContainer.isVisible()) {
                            for (var i = 0; i < arrGroups[currentGroupId].length; i++) {
                                thisPanel.createSectionCopy(group, true);
                            }
                        }
                    }
                }
            }
        }

        // Load additional comboboxes options (data related to the current case/IA/Employer only)
        // before data will be set to the combos
        $.each(resultData.arrAdditionalOptions, function (type, arrData) {
            if (arrData.length) {
                var arrFields = thisPanel.mainForm.find('comboType', type);
                Ext.each(arrFields, function(combo) {
                    combo.getStore().loadData(arrData);
                });
            }
        });

        // Set row ids
        var arrRowIds = resultData.rowIds;
        var rowId;
        for (rowId in arrRowIds) {
            if (arrRowIds.hasOwnProperty(rowId)) {
                match = rowId.match(/^group_(\d+)$/i);
                if (match != null) {
                    var arrFormRowIdFields = thisPanel.find('name', thisPanel.memberType + '_group_row_' + match[1] + '[]');
                    Ext.each(arrFormRowIdFields, function(field, index){
                        if (!empty(arrRowIds[rowId][index])) {
                            field.setValue(arrRowIds[rowId][index]);
                        }
                    });
                }
            }
        }

        // Set received fields data
        var arrFields = resultData.fields;
        for (var fieldName in arrFields) {
            if (arrFields.hasOwnProperty(fieldName)) {
                match = fieldName.match(/^field_(\w+)_(\d+)_(\d+)$/i);
                if (match != null && arrFields[fieldName].length) {
                    var arrFormFields = thisPanel.find('name', match[0] + '[]');
                    var groupId = match[2];
                    var fieldId = match[3];

                    Ext.each(arrFormFields, function(field, index){
                        var currentApplicantId = $('#' + field.id).closest('fieldset').find('input[name="' + thisPanel.memberType + '_group_row_' + groupId + '[]"]').val();
                        thisPanel.fillFieldData(field, arrFields[fieldName][index], fieldId, currentApplicantId);
                    });
                }
            }
        }

        // For all disabled fields - set value "-"
        var arrTypesToFind = ['datefield', 'combo', 'displayfield', 'textfield', 'textarea', 'lovcombo', 'numberfield'],
            arrAllFormFields = [];
        Ext.each(arrTypesToFind, function (itemXType) {
            arrAllFormFields = thisPanel.findByType(itemXType);
            Ext.each(arrAllFormFields, function(item){
                if (item.disabled && empty(item.getValue()) && $("#" + item.id).is(":visible")) {
                    item.setValue('-');
                }
            });
        });

        if (!booIsNewCase) {
            // Update tab title, if applicant name was changed
            if (resultData.applicantName) {
                thisPanel.setCurrentApplicantTabName(resultData.caseEmployerName, resultData.applicantName, resultData.caseName, resultData.caseType);
            }

            // Load 'created/updated on' info
            if (resultData.applicantUpdatedOn) {
                thisPanel.updateDetailsSection(resultData.applicantUpdatedOn);
            }

            // Load 'updated on' time
            if (resultData.applicantUpdatedOnTime) {
                var updatedOnField = thisPanel.find('name', 'applicantUpdatedOn');
                if (updatedOnField.length) {
                    updatedOnField[0].setValue(resultData.applicantUpdatedOnTime);
                }
            }
        }

        // Fire 'check' event, so related listeners will be called (e.g. to hide conditional fields)
        arrTypesToFind = ['combo', 'lovcombo'];
        arrAllFormFields = [];
        Ext.each(arrTypesToFind, function (itemXType) {
            arrAllFormFields = thisPanel.findByType(itemXType, true);
            Ext.each(arrAllFormFields, function (item) {
                if (!item.disabled && empty(item.getValue())) {
                    item.fireEvent('change', item);

                    if (itemXType != 'lovcombo') {
                        // In some cases blur is used too
                        item.fireEvent('blur', item);
                    }
                }
            });
        });

        setTimeout(function () {
            var arrAllFormCheckboxes = thisPanel.findByType('checkbox', true);
            Ext.each(arrAllFormCheckboxes, function (item) {
                if (!item.disabled) {
                    item.fireEvent('check', item, item.getValue(), true);
                }
            });
        }, 100);

        var booCollapseFieldsets = true;
        if (booIsNewCase && (thisPanel.memberType == 'case' || (thisPanel.memberType == 'individual' && empty(thisPanel.applicantId)))) {
            booCollapseFieldsets = false;
        }

        if (booCollapseFieldsets && thisPanel.mainForm.getEl()) {
            // Collapse all fieldsets except of the first one
            var arrCreatedFieldSets = thisPanel.mainForm.getEl().query('fieldset');
            Ext.each(arrCreatedFieldSets, function(foundFieldset, index){
                var foundFieldsetId = $(foundFieldset).attr('id');
                if (index && $(foundFieldset).hasClass('applicants-profile-fieldset')) {
                    var cmp = Ext.getCmp(foundFieldsetId);
                    // Don't collapse case groups, those need to be expanded according to the Immigration Programs settings
                    if (cmp && $(foundFieldset).hasClass('group_collapsed')) {
                        cmp.collapse();
                    }
                }
            });
        }

        thisPanel.owner.owner.fixParentPanelHeight();
    },

    toggleLinkToEmployerButton: function (resultData) {
        var thisPanel = this;
        thisPanel.employerCaseLinkedCaseType = resultData.employerCaseLinkedCaseType;

        if (!is_client && thisPanel.memberType === 'case' && !empty(resultData.caseId) && arrApplicantsSettings.access.employers_module_enabled && thisPanel.profileToolbar) {
            if (empty(resultData.caseEmployerId)) {
                // Check if we need to show 'Link to Employer' button in the toolbar

                // Check if case's category allows assigning to employer
                var oCategory = thisPanel.getCategoryByCaseTypeAndCategoryId(resultData.caseType, resultData.caseCategory);
                var booShowLinkButton = !empty(oCategory) && oCategory.link_to_employer === 'Y';

                var arrButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-link-case-to-lmia-case');
                if (arrButtons.length) {
                    var label = String.format(
                        _('Link to {0} Case'),
                        thisPanel.getCaseTypeLMIALabel(thisPanel.employerCaseLinkedCaseType)
                    );
                    arrButtons[0].setText('<i class="las la-link"></i>' + label);
                    arrButtons[0].setTooltip(label);

                    arrButtons[0].setVisible(booShowLinkButton);
                    // Enable the button if it is visible (maybe previously was disabled)
                    if (booShowLinkButton) {
                        arrButtons[0].setDisabled(false);
                    }
                }

                arrButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-link-case-to-employer');
                if (arrButtons.length) {
                    arrButtons[0].setVisible(true);
                    arrButtons[0].setDisabled(false);
                }
            } else if (!empty(thisPanel.applicantId) && resultData.caseEmployerId != thisPanel.applicantId) {
                // Check if we need to show 'Unlink Case' button in the toolbar
                var arrButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-unlink-case-from-lmia-case-or-employer');
                if (arrButtons.length) {
                    var label = String.format(
                        _('Unlink from {0}'),
                        empty(thisPanel.employerCaseLinkedCaseType) ? _('Employer') : thisPanel.getCaseTypeLMIALabel(thisPanel.employerCaseLinkedCaseType) + ' ' + _('Case')
                    );

                    arrButtons[0].setText('<i class="las la-unlink"></i>' + label);
                    arrButtons[0].setTooltip(label);

                    arrButtons[0].setVisible(true);
                    arrButtons[0].setDisabled(false);
                }
            }
        }
    },

    toggleDisabledLinkToEmployerButton: function () {
        var thisPanel = this;

        var arrButtons = thisPanel.profileToolbar.find('uniqueFieldId', 'btn-link-case-to-lmia-case');
        if (arrButtons.length && arrButtons[0].isVisible()) {
            // Check if case's category allows assigning to employer
            var oCategory = thisPanel.getCategoryByCaseTypeAndCategoryId(thisPanel.caseType, thisPanel.caseCategory);
            var booEnableLinkButton = !empty(oCategory) && oCategory.link_to_employer === 'Y';
            arrButtons[0].setDisabled(!booEnableLinkButton);
        }
    },

    loadApplicantDetails: function(booRefreshCasesList, booNewIAAndCase) {
        var thisPanel = this;
        var booIsNewCase = false;

        if (booNewIAAndCase) {
            booIsNewCase = true;
        } else {
            if (empty(thisPanel.applicantId)) {
                return;
            } else if(thisPanel.memberType == 'case' && empty(thisPanel.caseId)) {
                booIsNewCase = true;

                // Don't send additional request
                // because 'load' request will be sent when 'Immigration Program' will be selected
                if (booRefreshCasesList) {
                    return;
                }
            }
        }

        var parentTab = thisPanel.owner.getEl();

        parentTab.mask(_('Loading...'));

        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/load',
            params: {
                applicantId:         Ext.encode(thisPanel.applicantId),
                caseId:              Ext.encode(thisPanel.caseId),
                caseType:            Ext.encode(empty(thisPanel.caseType) ? 0 : thisPanel.caseType),
                caseEmployerId:      Ext.encode(empty(thisPanel.caseEmployerId) ? 0 : thisPanel.caseEmployerId),
                booLoadCaseInfoOnly: Ext.encode(empty(thisPanel.booLoadCaseInfoOnly) ? false : thisPanel.booLoadCaseInfoOnly)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisPanel.showLoadedApplicantData(resultData, booRefreshCasesList, booIsNewCase);
                    thisPanel.booIsDirty = false;
                    parentTab.unmask();
                } else {
                    // Close current tab
                    var currentTab = Ext.getCmp(thisPanel.getCurrentTabId());
                    if (currentTab) {
                        var tabPanel = currentTab.ownerCt;
                        tabPanel.remove(thisPanel.getCurrentTabId());
                    }

                    Ext.simpleConfirmation.error(resultData.msg);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                parentTab.unmask();
            }
        });
    },

    saveApplicantDetails: function() {
        if (!this.mainForm.getForm().isValid()) {
            // Expand and collapse a fieldset again - to be sure that all inner fields will be checked and submitted
            this.mainForm.items.each(function(item){
                if (item.getXType() == 'fieldset' && item.collapsible && item.collapsed) {
                    item.expand();
                    item.collapse();
                }
            });

            // Find the first incorrect field, show it and scroll to it
            // Also, show an error too (after we scrolled to the first field)
            var wrongFields = this.mainForm.getForm().findInvalid();
            if (wrongFields.length) {
                var f = wrongFields[0];
                f.ensureVisible();
                $($('#' + f.ownerCt.getId()).parents('.x-tab-panel').first().find('.x-panel-body').first()).animate({
                    scrollTop: $('#' + f.ownerCt.getId())[0].offsetTop
                }, 1000, function() {
                    Ext.simpleConfirmation.msg(
                        _('Info'),
                        wrongFields.length === 1 ? _('Please fill out the highlighted required field before saving...') : _('Please fill out the highlighted required fields before saving...')
                    );
                });
                return;
            }
        }

        var dependantsContainer = this.getUniqueField('dependents_container');
        if (dependantsContainer) {
            var arrRelationShipFields = dependantsContainer.find('name', 'field_case_dependants_relationship[]');
            if (arrRelationShipFields) {
                var arrDependentsCount = {};
                var arrRelationshipOptions = [];
                Ext.each(arrRelationShipFields, function(combo){
                    arrRelationshipOptions = combo.getStore().getRange();
                    var relation = combo.getValue();
                    if (arrDependentsCount[relation]) {
                        arrDependentsCount[relation] += 1;
                    } else {
                        arrDependentsCount[relation] = 1;
                    }
                });

                var arrErrors = [];
                for (var i in arrDependentsCount) {
                    var obj = arrDependentsCount[i];
                    Ext.each(arrRelationshipOptions, function (item) {
                        if (item.data['option_id'] == i && arrDependentsCount[i] > item.data['option_max_count']) {
                            arrErrors.push(item.data['option_max_count_error']);
                        }
                    });
                }

                if (arrErrors.length) {
                    dependantsContainer.ensureVisible();
                    dependantsContainer.focus();
                    $('html, body').animate({
                        scrollTop: $('#' + dependantsContainer.ownerCt.getId()).offset().top
                    }, {
                        duration: 1000,
                        complete: function() {
                            Ext.simpleConfirmation.error(implode('<br/>', arrErrors));
                        }
                    });
                } else {
                    this.submitSaveRequest();
                }
            }
        } else {
            this.submitSaveRequest();
        }
    },

    updateOffices: function (arrApplicantOfficeFields, arrApplicantOffices, booFullName) {
        if (arrApplicantOfficeFields && arrApplicantOfficeFields.length) {
            var arrFields;
            var thisPanel = this;

            Ext.each(arrApplicantOfficeFields, function (fieldId) {
                var fieldName = booFullName ? fieldId : 'field_' + thisPanel.memberType + '_' + fieldId + '[]';
                arrFields = thisPanel.find('name', fieldName);

                Ext.each(arrFields, function (thisField) {
                    if (thisField.getXType() == 'lovcombo') {
                        thisField.setValue(arrApplicantOffices);
                    }
                });
            });
        }
    },

    submitSaveRequest: function (booSaveCaseAndClientProfile) {
        var thisPanel = this;

        // Fix: issue with empty data submitting for comboboxes
        var arrCombos = this.mainForm.findByType('combo');
        Ext.each(arrCombos, function (combo) {
            if (combo.getValue() == '') {
                combo.setValue('');
            }
        });

        // Fix: issue with empty data submitting for number fields
        var arrNumberFields = this.mainForm.findByType('numberfield');
        Ext.each(arrNumberFields, function (field) {
            var value = field.getValue();
            if (value == '') {
                field.setRawValue(value === 0 ? 0 : '');
            }
        });

        // Fix: if there are submittable fields, but they are disabled -
        // temporary enable these fields, submit data and disable again
        var allReadonlyNotSubmittableFields = [];
        var allNotSubmittableFields = this.mainForm.find('submittable', false);
        Ext.each(allNotSubmittableFields, function (field) {
            field.setDisabled(true);
            allReadonlyNotSubmittableFields.push(field);
        });

        // Fix: if there are submittable fields, but they are hidden -
        // temporary enable + unhide these fields, submit data and disable + hide again
        var arrTempFieldsToDisable = [];
        var allSubmittableFields = this.mainForm.find('submittable_and_hidden', true);
        Ext.each(allSubmittableFields, function (field) {
            if (field.disabled) {
                field.setDisabled(false);
                field.ownerCt.setVisible(true);
                field.originalAllowBlank = field.allowBlank;
                field.allowBlank = true;

                arrTempFieldsToDisable.push(field);
            }
        });

        // Submit all data
        this.mainForm.getForm().submit({
            url: baseUrl + '/applicants/profile/save',
            waitMsg: _('Saving...'),

            success: function(form, action) {
                switch (thisPanel.memberType) {
                    case 'contact':
                        refreshSettings('agents');
                        break;

                    case 'employer':
                        refreshSettings('employer_settings');
                        break;

                    default:
                        refreshSettings('all');
                        break;
                }

                Ext.each(allReadonlyNotSubmittableFields, function (field) {
                    field.setDisabled(false);
                });

                Ext.each(arrTempFieldsToDisable, function (field) {
                    field.setDisabled(true);
                    field.ownerCt.setVisible(false);
                    field.allowBlank = field.originalAllowBlank;
                });

                var res = action.result;
                var match;
                // Set row ids
                var arrRowIds = res.rowIds;

                if (!res.booAllowEditApplicant) {
                    thisPanel.makeReadOnlyClient();
                }

                // After insert new dependent set dependent_id field
                var curTabId =  thisPanel.getCurrentTabId();

                if (!empty(res.applicantEncodedPassword)) {
                    var thisTabPanel = thisPanel.owner.owner;
                    thisTabPanel.applicantEncodedPassword = res.applicantEncodedPassword;
                }

                if (typeof res.arrDependents !== 'undefined' && res.arrDependents.length) {
                    $('#' + curTabId).find("[name*='field_case_dependants_dependent_id']").each(function(index) {
                        if (empty($(this).val())) {
                            $(this).val(res.arrDependents[index]['dependent_id']);
                        }
                    });
                }

                if (typeof res.arrCreatedFilesDependentIds !== 'undefined' && res.arrCreatedFilesDependentIds.length) {
                    $('#' + curTabId).find("[name*='field_case_dependants_dependent_id']").each(function(index) {
                        if (!empty($(this).val()) && res.arrCreatedFilesDependentIds.indexOf($(this).val()) > -1) {
                            var imageContainerId = $(this).closest('fieldset').find('.form-image-view').parent().attr('id');
                            if(imageContainerId.length) {
                                thisPanel.updateDependentImageDetails(thisPanel.caseId, $(this).val(), imageContainerId);
                            }
                        }
                    });
                }

                var rowId;
                for (rowId in arrRowIds) {
                    if (arrRowIds.hasOwnProperty(rowId)) {

                        match = rowId.match(/^group_(\d+)$/i);
                        if (match != null) {
                            var arrFormRowIdFields = thisPanel.find('name', thisPanel.memberType + '_group_row_' + match[1] + '[]');

                            var booShiftArray = false;

                            Ext.each(arrFormRowIdFields, function(field){
                                var $hiddenFieldParent = $('#' + field.id).closest('fieldset');
                                if ($hiddenFieldParent.hasClass('first-section') && !$hiddenFieldParent.is(":visible")) {
                                    booShiftArray = true;
                                }
                            });

                            if (booShiftArray) {
                                arrFormRowIdFields.shift();
                            }

                            Ext.each(arrFormRowIdFields, function(field, index){
                                if (!empty(arrRowIds[rowId][index])) {
                                    field.setValue(arrRowIds[rowId][index]);
                                }
                            });
                        }
                    }
                }

                if (!empty(thisPanel.applicantId) && !empty(res.generatedUsername)) {
                    var username = thisPanel.find('uniqueCls', 'username-value');
                    Ext.getCmp(username[0].getId()).setValue(res.generatedUsername);
                }

                var booCheckOtherProfileTab = false;
                var currentTab, tabPanel;
                var caseIdLinkedTo = thisPanel.caseIdLinkedTo;
                if ((thisPanel.memberType == 'case' && !empty(thisPanel.applicantId) && (empty(thisPanel.caseId) || !thisPanel.booShowChangeCaseTypeLink)) ||
                    (thisPanel.memberType == 'individual' && empty(thisPanel.applicantId) && (empty(thisPanel.caseId) || !thisPanel.booShowChangeCaseTypeLink))) {
                    currentTab = Ext.getCmp(curTabId);
                    currentTab.really_deleted = true;
                    tabPanel = currentTab.ownerCt;

                    // Close current tab
                    tabPanel.remove(curTabId);

                    // Open a new tab for new Case
                    tabPanel.openApplicantTab({
                        applicantId: res.applicantId,
                        applicantName: res.applicantName,
                        memberType: res.memberType,
                        caseId: res.caseId,
                        caseName: res.caseName,
                        caseType: res.caseType,
                        caseEmployerId: res.caseEmployerId,
                        caseEmployerName: res.caseEmployerName,
                        caseIdLinkedTo: caseIdLinkedTo
                    }, 'case_details');

                    if (!empty(caseIdLinkedTo) && !empty(res.caseId)) {
                        // Reload "linked cases" (sponsorship) grid
                        var linkedCasesGrid = Ext.getCmp('linked-cases-grid-' + caseIdLinkedTo);
                        if (!empty(linkedCasesGrid)) {
                            linkedCasesGrid.refreshAssignedCasesList();
                        }

                        // Reload "linked cases" (navigation) grid(s)
                        var mainTabPanel = Ext.getCmp(thisPanel.panelType + '-tab-panel');
                        var activeTab = mainTabPanel.getActiveTab();
                        mainTabPanel.items.each(function (oTab) {
                            // Detect if we want to reload the list of cases now or later
                            var booReloadNow = oTab.id == activeTab.id;
                            var activeCasesPanels = oTab.findByType('ApplicantsProfileTabPanel');
                            Ext.each(activeCasesPanels, function (item) {
                                if (!empty(item.applicantsCasesNavigationPanel) && item.applicantsCasesNavigationPanel.caseId == caseIdLinkedTo) {
                                    if (booReloadNow) {
                                        item.applicantsCasesNavigationPanel.refreshCasesList();
                                    } else {
                                        item.applicantsCasesNavigationPanel.autoRefreshCasesList = true;
                                    }
                                }
                            });
                        });
                    }

                    if (!empty(thisPanel.applicantId)) {
                        // Reload quick search result + tasks panel
                        thisPanel.owner.owner.refreshClientsList(thisPanel.panelType, thisPanel.applicantId, res.caseId, true);
                    }
                } else if (empty(thisPanel.applicantId)) {
                    // New applicant was added
                    currentTab = Ext.getCmp(curTabId);
                    currentTab.really_deleted = true;
                    tabPanel = currentTab.ownerCt;

                    // Close current tab
                    tabPanel.remove(curTabId);

                    // Open a new tab for new Applicant
                    if (thisPanel.memberType == 'client' && !empty(thisPanel.caseEmployerId)) {
                        tabPanel.openApplicantTab({
                            applicantId: res.applicantId,
                            applicantName: res.applicantName,
                            memberType: res.memberType,
                            caseId: res.caseId,
                            caseName: res.caseName,
                            caseType: res.caseType,
                            caseEmployerId: res.applicantId,
                            caseIdLinkedTo: caseIdLinkedTo
                        });
                    } else if(thisPanel.booOpenNewCaseTab){
                        // We need to be sure that 'parent' employer will be passed
                        if (res.memberType == 'employer') {
                            res.caseEmployerId = res.applicantId;
                            res.caseEmployerName = res.applicantName
                        }

                        tabPanel.openApplicantTab({
                            applicantId: res.applicantId,
                            applicantName: res.applicantName,
                            memberType: res.memberType,
                            caseId: 0,
                            caseName: 'Case 1',
                            caseType: thisPanel.caseType,
                            caseEmployerId: res.caseEmployerId,
                            caseEmployerName: res.caseEmployerName,
                            showOnlyCaseTypes: res.memberType,
                            caseIdLinkedTo: caseIdLinkedTo
                        }, 'case_details');
                    } else {
                        tabPanel.openApplicantTab({
                            applicantId:   res.applicantId,
                            applicantName: res.applicantName,
                            memberType:    res.memberType
                        });
                    }

                    if (!empty(res.caseId) && allowedClientSubTabs.has('accounting')) {
                        var clientAccountingPanel = Ext.getCmp('accounting_invoices_panel_' + res.caseId);
                        if (clientAccountingPanel) {
                            clientAccountingPanel.refreshAccountingTab();
                        }
                    }

                    // Reload quick search result + tasks panel
                    thisPanel.owner.owner.refreshClientsList(thisPanel.panelType, thisPanel.applicantId, res.caseId, true);
                } else {
                    booCheckOtherProfileTab = true;
                    thisPanel.updateClientInfoEverywhere(res);
                }

                if (res.booShowWelcomeMessage) {
                    thisPanel.showConfirmWelcomeMessage(res.caseId);
                } else {
                    var booShowSuccess = false;
                    if (booCheckOtherProfileTab) {
                        if (thisPanel.memberType == 'case') {
                            // For the Case Details - save parent IA/Employer profile too
                            var thisClientProfileForm = thisPanel.owner.applicantsProfileForm;
                            var thisIndividualProfileForm = thisPanel.owner.individualProfileForm;
                            if (!empty(thisClientProfileForm) && thisClientProfileForm.booRendered && thisClientProfileForm.booIsDirty) {
                                var question = _('Case Details saved. Would you like to save changes on the Profile subtab?');
                                Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                    if (btn === 'yes') {
                                        // Switch to the Profile tab + save
                                        thisPanel.owner.setActiveTab(thisClientProfileForm.ownerCt);
                                        thisClientProfileForm.saveApplicantDetails();
                                    }
                                });
                            } else if (!empty(thisIndividualProfileForm) && thisIndividualProfileForm.booRendered && thisIndividualProfileForm.booIsDirty) {
                                var question = _('Case Details saved. Would you like to save changes on the Employee subtab?');
                                Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                    if (btn === 'yes') {
                                        // Switch to the Profile tab + save
                                        thisPanel.owner.setActiveTab(thisIndividualProfileForm.ownerCt);
                                        thisIndividualProfileForm.saveApplicantDetails();
                                    }
                                });
                            } else {
                                booShowSuccess = true;
                            }
                        } else if (thisPanel.memberType == 'individual' || thisPanel.memberType == 'employer') {
                            // For the Client Profile - save case's details too
                            var thisCaseProfileForm = thisPanel.owner.caseProfileForm;
                            if (!empty(thisCaseProfileForm) && thisCaseProfileForm.booRendered && thisCaseProfileForm.booIsDirty) {
                                var question = _('Profile saved. Would you like to save changes on the Case Details subtab?');
                                Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                    if (btn === 'yes') {
                                        // Switch to the Case Details tab + save
                                        thisPanel.owner.setActiveTab(thisCaseProfileForm.ownerCt);
                                        thisCaseProfileForm.saveApplicantDetails();
                                    }
                                });
                            } else {
                                booShowSuccess = true;
                            }
                        }
                    } else {
                        booShowSuccess = true;
                    }

                    if (booShowSuccess) {
                        Ext.simpleConfirmation.msg(_('Success'), res.message, 1500);
                    }
                }

                thisPanel.booIsDirty = false;
            },

            failure: function(form, action) {
                Ext.each(allReadonlyNotSubmittableFields, function (field) {
                    field.setDisabled(false);
                });

                var msg = action && action.result && action.result.message ? action.result.message : 'Internal error.';
                var msgType = action && action.result && action.result.error_type ? action.result.error_type : 'error';
                if (msg == 'last_update_time_different') {
                    Ext.Msg.confirm('Please confirm', 'Since you have opened this profile, it was changed. If you save the profile, it will update the information already saved in the profile.<br/><br/>Are you sure you want to continue?', function (btn) {
                        if (btn == 'yes') {
                            var forceField = thisPanel.find('name', 'forceOverwrite');
                            forceField[0].setValue(1);
                            thisPanel.saveApplicantDetails();
                        }
                    });
                } else if (msg == 'max_clients_count_reached') {
                    thisPanel.owner.owner.showAddClientNumberExceededNotification();
                } else if (msgType == 'warning') {
                    Ext.simpleConfirmation.warning(msg);
                } else {
                    if (action && action.result && action.result.arrErrorFields && action.result.arrErrorFields.length) {
                        thisPanel.markInvalidFieldsAndScroll(action.result.arrErrorFields);
                    }
                    Ext.simpleConfirmation.error(msg);
                }
            }
        });
    },

    markInvalidFieldsAndScroll: function(arrErrorFields) {
        var thisPanel = this;

        for (var i = 0; i < arrErrorFields.length; i++) {
            var oField = thisPanel.find('name', arrErrorFields[i] + '[]');
            if (oField && oField.length) {
                oField[0].markInvalid();
            }
        }

        var wrongFields = thisPanel.mainForm.getForm().findInvalid();
        if (wrongFields.length) {
            var f = wrongFields[0];
            f.ensureVisible();
            $('html, body').animate({
                scrollTop: $('#' + f.ownerCt.getId()).offset().top
            }, 1000);
        }
    },

    updateFileDetails: function(currentApplicantId, fieldId, imageContainerId, filename) {
        var parent = $('#' + imageContainerId);
        parent.find('.form-file-view').show();
        parent.find('.form-file-edit').hide();
        parent.find('.form-file-input').val('');
        parent.find('.form-file-edit a[data-rel=cancel]').show();

        if (currentApplicantId) {
            var deleteLink = parent.find('.form-file-view a[data-rel=remove]');
            var newDelUrl = topBaseUrl + '/applicants/profile/delete-file?type=file&mid=' + currentApplicantId + '&id=' + fieldId;
            $(deleteLink).attr('href', newDelUrl);
            var downloadLink = parent.find('.form-file-view a[data-rel=download]');
            var newDownloadUrl = topBaseUrl + '/applicants/profile/download-file?mid=' + currentApplicantId + '&id=' + fieldId;
            $(downloadLink).attr('href', newDownloadUrl);
            $(downloadLink).html(filename);
        }
    },

    updateImageDetails: function(currentApplicantId, fieldId, imageContainerId) {
        var parent = $('#' + imageContainerId);
        parent.find('.form-image-view').show();
        parent.find('.form-image-edit').hide();
        parent.find('.form-image-input').val('');
        parent.find('.form-image-edit a[data-rel=cancel]').show();

        if (currentApplicantId) {
            var img = parent.find('.form-image-view img');
            var newImgUrl = topBaseUrl + '/applicants/profile/view-image?mid=' + currentApplicantId + '&id=' + fieldId + '&' + (new Date()).getTime();
            $(img).attr('data-path', newImgUrl);
            $(img).attr('src', newImgUrl);

            $(img).closest('a').attr('href', topBaseUrl + '/applicants/profile/get-profile-image?mid=' + currentApplicantId + '&id=' + fieldId + '&' +  (new Date()).getTime());

            var deleteLink = parent.find('.form-image-view a[data-rel=remove]');
            var newDelUrl = topBaseUrl + '/applicants/profile/delete-file?type=image&mid=' + currentApplicantId + '&id=' + fieldId;
            $(deleteLink).attr('href', newDelUrl);
        }
    },

    updateDependentImageDetails: function(currentApplicantId, dependentId, imageContainerId) {
        var parent = $('#' + imageContainerId);
        parent.find('.form-image-view').show();
        parent.find('.form-image-edit').hide();
        parent.find('.form-image-input').val('');
        parent.find('.form-image-edit a[data-rel=cancel]').show();

        if (currentApplicantId) {
            var img = parent.find('.form-image-view img');
            var newImgUrl = topBaseUrl + '/applicants/profile/view-image?mid=' + currentApplicantId + '&type=thumbnail&did=' + dependentId + '&' + (new Date()).getTime();
            $(img).attr('data-path', newImgUrl);
            $(img).attr('src', newImgUrl);

            $(img).closest('a').attr('href', topBaseUrl + '/applicants/profile/get-profile-image?mid=' + currentApplicantId + '&did=' + dependentId + '&' + (new Date()).getTime());

            var deleteLink = parent.find('.form-image-view a[data-rel=remove]');
            var newDelUrl = topBaseUrl + '/applicants/profile/delete-file?type=image&mid=' + currentApplicantId + '&did=' + dependentId;
            $(deleteLink).attr('href', newDelUrl);
        }
    },

    toggleFileSection: function(fields) {
        var thisPanel = this;
        for(var i=0; i< fields.length; i++) {

            var name = fields[i]["full_field_id"];
            var matchGroupId = name.match(/(\d+)_\d/);
            var value = '';

            if (thisPanel.memberType != 'case') {
                value = '[value="' + fields[i]['applicant_id'] + '"]';
            }
            var fieldset = $('#' + thisPanel.id).find('input[name="' + thisPanel.memberType + '_group_row_' + matchGroupId[1] + '[]"]' + value).closest('fieldset');

            fieldset.find('input[name="' + name + '[]"]').each(function() {
                var parentId = $(this).parent().parent().attr('id');
                thisPanel.updateFileDetails(fields[i]['applicant_id'], fields[i]['field_id'], parentId, fields[i]['filename']);
            });
        }
    },

    toggleImageSection: function(fields) {
        var thisPanel = this;
        for(var i=0; i< fields.length; i++) {

            var name = fields[i]["full_field_id"];
            var matchGroupId = name.match(/(\d+)_\d/);
            var value = '';

            if (thisPanel.memberType != 'case') {
                value = '[value="' + fields[i]['applicant_id'] + '"]';
            }
            var fieldset = $('#' + thisPanel.id).find('input[name="' + thisPanel.memberType + '_group_row_' + matchGroupId[1] + '[]"]' + value).closest('fieldset');

            fieldset.find('input[name="' + name + '[]"]').each(function() {
                var parentId = $(this).parent().parent().parent().parent().parent().attr('id');
                thisPanel.updateImageDetails(fields[i]['applicant_id'], fields[i]['field_id'], parentId);
            });
        }
    },

    initReferenceField: function(fieldId, booAddNewField, booMultipleValues, realFieldId) {
        var thisPanel = this;

        var field = $("#" + fieldId);

        if (booMultipleValues) {
            $(field).closest('div').css({'margin-top': '-4px'});
            $(field).closest('div').closest('div').css({'margin-bottom': '2px'});
        }

        $(field).closest('div').css({'text-align': 'right'});

        field.hide();
        field.after('<a href="#" class="blulinkunm x-form-field-value reference_link" style="display:none; float: left;"></a>');
        field.after('<span class="reference_error" style="display:none; color: red; float: left;"></span>');

        if (booMultipleValues) {
            field.siblings('table').after('<span class="reference_info" style="display:none; float: left;">Press Enter after editing this field</span>');
        }

        field.after('<img class="edit_reference" style="padding: 2px 3px 0 0; cursor: pointer;" src="' + topBaseUrl + '/images/icons/application_form_edit.png" alt="Edit Reference">');

        var editReferenceLink = field.siblings('.edit_reference');

        editReferenceLink.click(function() {
            field.siblings('.reference_link').hide();
            field.siblings('.reference_error').hide();
            field.siblings('.edit_reference').hide();
            field.show();
            field.siblings('.reference_info').show();
        });

        if (booAddNewField) {
            editReferenceLink.click();
        }

        field.keyup(function(e) {
            if (e.which == Ext.EventObject.ENTER) {
                if (field.val() == '') {
                    field.hide();
                    field.siblings('.edit_reference').show();
                    field.siblings('.reference_info').hide();
                } else {
                    thisPanel.getReferenceFieldView(fieldId, field.val(), realFieldId);
                }
            }
        });
    },

    updateReferenceDetails: function(fieldId, data) {
        var thisPanel = this;

        var field = $("#" + fieldId);

        field.hide();
        field.siblings('.edit_reference').show();
        field.siblings('.reference_info').hide();

        if (data.booWrongValue) {
            field.siblings('.reference_error').show();
            field.siblings('.reference_error').html(data.reference);
        } else {
            field.siblings('.reference_link').show();
            field.siblings('.reference_link').html(data.reference);
            field.siblings('.reference_link').click(function() {
                openMemberTab(thisPanel.owner.owner, data)
            });
        }
    },

    initMultipleReferenceFields: function(fieldId, data) {
        var thisPanel = this;

        var el = $("#" + fieldId);
        var arrTextFields = el.parent().find('input');

        var field = Ext.getCmp(fieldId);
        var booMultipleValues = field.xtype != 'textfield';

        arrTextFields.each(function(i) {
            var item = $(this);
            thisPanel.initReferenceField(item.attr('id'), false, booMultipleValues, field.realFieldId);
            thisPanel.updateReferenceDetails(item.attr('id'), data[i]);
        });
    },

    getReferenceFieldView: function(fieldId, value, realFieldId) {
        var thisPanel = this;
        var memberId = thisPanel.memberType == 'case' ? thisPanel.caseId : thisPanel.applicantId;

        Ext.getBody().mask('Generating...');

        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/get-reference-field-view',
            params: {
                applicantId: Ext.encode(memberId),
                value: Ext.encode(value),
                fieldId: Ext.encode(realFieldId)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    Ext.getBody().unmask();
                    thisPanel.updateReferenceDetails(fieldId, resultData);
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                    Ext.getBody().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Cannot get reference. Please try again later.');
                Ext.getBody().unmask();
            }
        });
    },


    deleteApplicant: function (booConfirmed, question) {
        var thisPanel = this;
        if (empty(question)) {
            question = String.format(
                _('Are you sure you want to permanently delete this {0} and all associated information and files?'),
                this.memberType
            );
        }

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/applicants/profile/delete',
                    params: {
                        applicantId: Ext.encode(thisPanel.memberType == 'case' ? thisPanel.caseId : thisPanel.applicantId),
                        confirmed: Ext.encode(booConfirmed ? 1 : 0)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            if (thisPanel.memberType == 'contact') {
                                refreshSettings('agents');
                            } else if (thisPanel.memberType == 'employer') {
                                refreshSettings('employer_settings');
                            }

                            // Close the current tab
                            var thisTabId = thisPanel.getCurrentTabId();
                            var currentTab = Ext.getCmp(thisTabId);
                            currentTab.really_deleted = true;
                            var tabPanel = currentTab.ownerCt;
                            tabPanel.remove(thisTabId);

                            thisPanel.owner.owner.refreshClientsList(thisPanel.panelType, thisPanel.applicantId, thisPanel.caseId, true);

                            if (!empty(resultData.applicantId)) {
                                thisPanel.owner.owner.openApplicantTab({
                                    applicantId: resultData.applicantId,
                                    applicantName: resultData.applicantName,
                                    memberType: resultData.applicantType
                                }, 'profile');
                            }

                            Ext.getBody().mask(resultData.msg);
                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);
                        } else {
                            Ext.getBody().unmask();
                            if (resultData.type == 'confirmation') {
                                thisPanel.deleteApplicant(true, resultData.msg);
                            } else {
                                Ext.simpleConfirmation.error(resultData.msg);
                            }
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot delete applicant. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },

    getRealHTML: function (formId) {
        $(formId + ' input').each(function () {
            this.setAttribute('value', this.value);
            if (this.checked)
                this.setAttribute('checked', 'checked');
            else
                this.removeAttribute('checked');
        });

        $(formId + ' select').each(function () {
            var index = this.selectedIndex;
            var i = 0;
            $(this).children('option').each(function () {
                if (i++ != index)
                    this.removeAttribute('selected');
                else
                    this.setAttribute('selected', 'selected');
            });
        });

        $(formId + ' textarea').each(function () {
            $(this).html($(this).val());
        });

        return $(formId).html();
    },

    getCaseTemplatesForCurrentApplicant: function () {
        var thisPanel = this;
        var caseApplicantIdField = thisPanel.find('name', 'applicantId')[0];
        var caseApplicantIdValue = caseApplicantIdField.getValue();

        var caseEmployerIdField = thisPanel.find('name', 'caseEmployerId')[0];
        var caseEmployerIdValue = caseEmployerIdField.getValue();

        var caseIdLinkedToField = thisPanel.find('name', 'caseIdLinkedTo')[0];
        var caseIdLinkedToValue = caseIdLinkedToField.getValue();

        var arrThisApplicantCaseTemplates = [];
        var checkMemberType = !empty(caseEmployerIdValue) && !empty(caseApplicantIdValue) && caseEmployerIdValue == caseApplicantIdValue ? 'employer' : 'individual';
        var booFilterByLink = !empty(caseIdLinkedToValue) || (!empty(caseApplicantIdValue) && !empty(caseEmployerIdValue) && caseApplicantIdValue != caseEmployerIdValue);
        Ext.each(arrApplicantsSettings.case_templates, function (caseTemplate) {
            // Filter by "categories" if we want to link the current case to a specific one
            var booShow = true;
            if (booFilterByLink && !caseTemplate.case_template_can_be_linked_to_employer) {
                booShow = false;
            }

            if (caseTemplate.case_template_type_names.has(checkMemberType) && booShow && ((caseTemplate.case_template_hidden == 'N' && caseTemplate.case_template_hidden_for_company == 'N') || caseTemplate.case_template_id == thisPanel.caseType)) {
                arrThisApplicantCaseTemplates.push({
                    option_id: caseTemplate.case_template_id,
                    option_name: caseTemplate.case_template_name
                });
            }
        });

        return arrThisApplicantCaseTemplates;
    },

    showConfirmWelcomeMessage: function(member_id) {
        var thisPanel = this;
        var thisTabPanel = thisPanel.owner.owner;
        Ext.MessageBox.maxWidth = 500;
        Ext.Msg.confirm('Info', 'New case was successfully added.<br/><br/> Would you like a welcome message to be emailed to this case?', function (btn) {
            if (btn == 'yes') {
                show_email_dialog({member_id: member_id, encoded_password: thisTabPanel.applicantEncodedPassword, templates_type: 'welcome'});
            }
        });
    },

    printApplicantDetails: function() {
        var title = this.getCurrentApplicantTabName();

        // Get profile html content
        var content = this.getRealHTML('#' + this.mainForm.getId());

        // Create temp container, place required html in it
        var tempContainer = $('<div></div>', { css: { 'display': 'none' }});
        $('body').append(tempContainer);

        // Add content to temp container
        tempContainer.html(content);

        // Open all groups
        tempContainer.find('.x-fieldset-bwrap').show();

        // Don't show 'Add new section' buttons
        tempContainer.find('.applicants-profile-add-section-container').hide();

        // Hide 'expand/collapse' images
        tempContainer.find('.x-tool-toggle').remove();

        // Add some space between the fieldsets
        tempContainer.find('.x-fieldset').css('margin-bottom', '15px');

        // Fix email links
        tempContainer.find('.blulinkunm').removeClass('blulinkunm').css('cssText', 'color: #000 !important;');
        tempContainer.find('.email-field').removeClass('email-field').css('cssText', 'color: #000 !important;');

        // Get modified content
        content = tempContainer.html();

        // Remove temp container
        tempContainer.remove();

        // Print content
        print(content, title);
    },

    hasAccess: function (formMemberType, accessRule) {
        var booHasAccess = false;
        if (typeof arrApplicantsSettings != 'undefined') {
            if (empty(formMemberType)) {
                booHasAccess = (typeof arrApplicantsSettings['access'][accessRule] != 'undefined' && arrApplicantsSettings['access'][accessRule]);
            } else {
                if (typeof arrApplicantsSettings['access'][formMemberType] != 'undefined') {
                    if (Array.isArray(arrApplicantsSettings['access'][formMemberType])) {
                        booHasAccess = arrApplicantsSettings['access'][formMemberType].has(accessRule);
                    } else if (arrApplicantsSettings['access'][formMemberType].hasOwnProperty(accessRule) && arrApplicantsSettings['access'][formMemberType][accessRule]) {
                        booHasAccess = true;
                    }
                }
            }
        }

        return booHasAccess;
    },

    toggleDependentSpouseNameField: function (dependentRowContainer, relationship, marital_status) {
        var arrFields;
        if (marital_status === null) {
            arrFields = dependentRowContainer.find('fieldUniqueName', 'marital_status');
            if (arrFields.length) {
                marital_status = arrFields[0].getValue();
            }
        }

        if (relationship === null) {
            arrFields = dependentRowContainer.find('fieldUniqueName', 'relationship');
            if (arrFields.length) {
                relationship = arrFields[0].getValue();
            }
        }

        // Show the "Name of Spouse" field only if the Marital status is Married,
        // and the dependent is NOT Partner/Spouse
        var booShow = relationship !== 'spouse' && marital_status === 'married';

        // Toggle the field
        arrFields = dependentRowContainer.find('fieldUniqueName', 'spouse_name');
        if (arrFields.length) {
            arrFields[0].setVisible(booShow);
            arrFields[0].ownerCt.setVisible(booShow);
        }
    },

    checkForChangesAndShowDialog: function (showDialogFunction) {
        var thisPanel = this;
        var thisToolbar = this.profileToolbar;
        var thisTabPanel = thisToolbar.owner.owner.owner;
        var oProfilePanel = thisToolbar.owner.owner;

        // Prevent this tab closing if there are unsaved changes
        var thisClientProfileForm = oProfilePanel.applicantsProfileForm;
        var thisIndividualProfileForm = oProfilePanel.individualProfileForm;
        var thisCaseProfileForm = oProfilePanel.caseProfileForm;

        var booIsDirtyClientProfile = !empty(thisClientProfileForm) && thisClientProfileForm.booRendered && thisClientProfileForm.booIsDirty;
        var booIsDirtyIndividualProfile = !empty(thisIndividualProfileForm) && thisIndividualProfileForm.booRendered && thisIndividualProfileForm.booIsDirty;
        var booIsDirtyCasesProfile = !empty(thisCaseProfileForm) && thisCaseProfileForm.booRendered && thisCaseProfileForm.booIsDirty;
        if (booIsDirtyClientProfile || booIsDirtyIndividualProfile || booIsDirtyCasesProfile) {
            // Show different messages - depends on where there are unsaved changes
            var question = '';
            if (booIsDirtyClientProfile) {
                question = _('There are unsaved changes on the Profile subtab. Are you sure you want to continue?');
            } else if (booIsDirtyIndividualProfile) {
                question = _('There are unsaved changes on the Employee subtab. Are you sure you want to continue?');
            } else {
                question = _('There are unsaved changes on the Case Details subtab. Are you sure you want to continue?');
            }

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn === 'yes') {
                    // There are changes. Mark the tasb as there are no changes -> so we can close it
                    if (booIsDirtyClientProfile) {
                        thisClientProfileForm.booIsDirty = false;
                    }

                    if (booIsDirtyIndividualProfile) {
                        thisIndividualProfileForm.booIsDirty = false;
                    }

                    if (booIsDirtyCasesProfile) {
                        thisCaseProfileForm.booIsDirty = false;
                    }

                    showDialogFunction();
                } else {
                    // Switch to the Client Profile or Individual Profile or Case Details
                    // It depends on where the changes are not saved
                    if (booIsDirtyClientProfile) {
                        oProfilePanel.setActiveTab(thisClientProfileForm.ownerCt);
                    } else if (booIsDirtyIndividualProfile) {
                        oProfilePanel.setActiveTab(thisIndividualProfileForm.ownerCt);
                    } else {
                        oProfilePanel.setActiveTab(thisCaseProfileForm.ownerCt);
                    }
                }
            });
        } else {
            // There are no changes - simply show the dialog
            showDialogFunction();
        }
    },

    linkCaseToEmployer: function () {
        var thisToolbar = this.profileToolbar;

        this.checkForChangesAndShowDialog(function () {
            var wnd = new ApplicantsCasesAssignToEmployerDialog({
                caseId: thisToolbar.caseId,
                caseName: thisToolbar.caseName,
                caseType: thisToolbar.caseType,
                applicantId: thisToolbar.applicantId,
                applicantName: thisToolbar.applicantName
            }, thisToolbar);
            wnd.showThisDialogCorrectly();
        });
    },

    linkCaseToLMIACase: function () {
        var thisPanel = this;
        var thisToolbar = this.profileToolbar;

        this.checkForChangesAndShowDialog(function () {
            var wnd = new ApplicantsCasesAssignDialog({
                caseId: thisToolbar.caseId,
                caseName: thisToolbar.caseName,
                caseType: thisToolbar.caseType,
                applicantId: thisToolbar.applicantId,
                applicantName: thisToolbar.applicantName,
                caseTypeLMIALabel: thisPanel.getCaseTypeLMIALabel(thisPanel.employerCaseLinkedCaseType) + ' ' + _('Case')
            }, thisToolbar);
            wnd.showThisDialogCorrectly();
        });
    },

    unlinkCaseFromLMIACaseOrEmployer: function () {
        var thisPanel = this;
        var thisToolbar = thisPanel.profileToolbar;
        var thisTabPanel = thisToolbar.owner.owner.owner;
        var oProfilePanel = thisToolbar.owner.owner;

        // Prevent this tab closing if there are unsaved changes
        var thisClientProfileForm = oProfilePanel.applicantsProfileForm;
        var thisIndividualProfileForm = oProfilePanel.individualProfileForm;
        var thisCaseProfileForm = oProfilePanel.caseProfileForm;

        var booIsDirtyClientProfile = !empty(thisClientProfileForm) && thisClientProfileForm.booRendered && thisClientProfileForm.booIsDirty;
        var booIsDirtyIndividualProfile = !empty(thisIndividualProfileForm) && thisIndividualProfileForm.booRendered && thisIndividualProfileForm.booIsDirty;
        var booIsDirtyCasesProfile = !empty(thisCaseProfileForm) && thisCaseProfileForm.booRendered && thisCaseProfileForm.booIsDirty;
        if (booIsDirtyClientProfile || booIsDirtyIndividualProfile || booIsDirtyCasesProfile) {
            // Show different messages - depends on where there are unsaved changes
            var question = '';
            if (booIsDirtyClientProfile) {
                question = _('There are unsaved changes on the Profile subtab. Are you sure you want to continue?');
            } else if (booIsDirtyIndividualProfile) {
                question = _('There are unsaved changes on the Employee subtab. Are you sure you want to continue?');
            } else {
                question = _('There are unsaved changes on the Case Details subtab. Are you sure you want to continue?');
            }

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn === 'yes') {
                    // There are changes. Mark the tasb as there are no changes -> so we can close it
                    if (booIsDirtyClientProfile) {
                        thisClientProfileForm.booIsDirty = false;
                    }

                    if (booIsDirtyIndividualProfile) {
                        thisIndividualProfileForm.booIsDirty = false;
                    }

                    if (booIsDirtyCasesProfile) {
                        thisCaseProfileForm.booIsDirty = false;
                    }

                    thisPanel.reallyUnlinkCase();
                } else {
                    // Switch to the Client Profile or Individual Profile or Case Details
                    // It depends on where the changes are not saved
                    if (booIsDirtyClientProfile) {
                        oProfilePanel.setActiveTab(thisClientProfileForm.ownerCt);
                    } else if (booIsDirtyIndividualProfile) {
                        oProfilePanel.setActiveTab(thisIndividualProfileForm.ownerCt);
                    } else {
                        oProfilePanel.setActiveTab(thisCaseProfileForm.ownerCt);
                    }
                }
            });
        } else {
            // There are no changes - simply try to unlink
            thisPanel.reallyUnlinkCase();
        }
    },

    reallyUnlinkCase: function () {
        var thisPanel = this;

        var question = String.format(
            _('Are you sure you want to unlink this case from {0}:<br><b>{1}</b>?'),
            empty(thisPanel.employerCaseLinkedCaseType) ? _('Employer') : thisPanel.getCaseTypeLMIALabel(thisPanel.employerCaseLinkedCaseType) + ' ' + _('Case'),
            this.caseEmployerName
        );

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Processing...'));

                Ext.Ajax.request({
                    url: topBaseUrl + '/applicants/profile/unassign-case',
                    params: {
                        applicantId: Ext.encode(thisPanel.caseEmployerId),
                        arrCases: Ext.encode([thisPanel.caseId])
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            // Close this tab
                            var thisTabPanel = thisPanel.owner.owner;
                            var tab = thisTabPanel.getActiveTab();
                            thisTabPanel.remove(tab);

                            // Show a 'success' message + open the same tab
                            Ext.getBody().mask(_('Done!'));
                            setTimeout(function () {
                                thisTabPanel.openApplicantTab({
                                    applicantId: thisPanel.applicantId,
                                    applicantName: thisPanel.applicantName,
                                    memberType: 'individual',
                                    caseId: thisPanel.caseId,
                                    caseName: thisPanel.caseName,
                                    caseType: thisPanel.caseType,
                                    caseEmployerId: null,
                                    caseEmployerName: null
                                }, 'case_details');

                                Ext.getBody().unmask();
                            }, 750);
                        } else {
                            Ext.simpleConfirmation.error(resultData.msg);
                            Ext.getBody().unmask();
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot unlink the case. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    }
});