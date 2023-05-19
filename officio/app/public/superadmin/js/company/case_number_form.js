var companyCaseNumberForm = function (config) {
    var thisPanel = this;
    Ext.apply(this, config);

    this.containerId = Ext.id();
    this.genFamilyNameFieldId = Ext.id();
    this.genGivenNamesFieldId = Ext.id();
    this.genCaseNumberFieldId = Ext.id();

    var caseNumberNamePrefixId = Ext.id(),
        caseNumberStartFromId = Ext.id(),
        caseNumberStartNumberFromId = Ext.id(),
        caseNumberResetEveryId = Ext.id(),
        globalOrBasedOnCaseTypeComboId = Ext.id();

    this.items = [
        {
            xtype: 'radio',
            boxLabel: _('Manually enter Case File Number'),
            hideLabel: true,
            name: 'cn-generate-number',
            inputValue: 'not-generate',
            listeners: {
                'check': function (radio, booChecked) {
                    if (booChecked) {
                        thisPanel.toggleFormFields(false);
                    }
                }
            }
        }, {
            xtype: 'radio',
            boxLabel: _('Automatically generate Case File Number with'),
            hideLabel: true,
            name: 'cn-generate-number',
            inputValue: 'generate',
            listeners: {
                'check': function (radio, booChecked) {
                    if (booChecked) {
                        thisPanel.toggleFormFields(true);
                    }
                }
            }
        }, {
            id: this.containerId,
            xtype: 'container',
            style: 'margin-left: 20px',
            hidden: true,
            items: [
                {
                    id: 'cn-row-' + oCaseNumberSettings['cn-1'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 865,
                            items: [
                                {
                                    xtype: 'button',
                                    tooltip: _('Move parameter up'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-up"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(true, 'cn-1');
                                    }
                                }, {
                                    xtype: 'button',
                                    tooltip: _('Move parameter down'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-down"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(false, 'cn-1');
                                    }
                                }, {
                                    xtype: 'checkbox',
                                    hideLabel: true,
                                    boxLabel: _('a fixed string of characters (you can include the following variables: %YEAR%, %SHORT_YEAR%, %MONTH%, %DAY%)'),
                                    name: 'cn-include-fixed-prefix',
                                    handler: this.toggleCheckboxRelatedFields.createDelegate(this)
                                }
                            ]
                        },
                        {
                            xtype: 'textfield',
                            name: 'cn-include-fixed-prefix-text',
                            allowBlank: false,
                            disabled: true,
                            width: 250,
                            minLength: 1,
                            autoCreate: {tag: 'input', type: 'text', size: 60, autocomplete: 'off'},
                            enableKeyEvents: true,
                            listeners: {
                                'keyup': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-2'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 115,
                            items: [
                                {
                                    xtype: 'button',
                                    tooltip: _('Move parameter up'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-up"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(true, 'cn-2');
                                    }
                                }, {
                                    xtype: 'button',
                                    tooltip: _('Move parameter down'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-down"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(false, 'cn-2');
                                    }
                                }, {
                                    id: caseNumberNamePrefixId,
                                    xtype: 'checkbox',
                                    name: 'cn-name-prefix',
                                    hideLabel: true,
                                    boxLabel: '',
                                    handler: this.toggleCheckboxRelatedFields.createDelegate(this)
                                },
                            ]
                        }, {
                            xtype: 'numberfield',
                            name: 'cn-name-prefix-family-name',
                            width: 60,
                            minValue: 1,
                            maxValue: 20,
                            allowBlank: false,
                            disabled: true,
                            allowNegative: false,
                            allowDecimals: false,
                            enableKeyEvents: true,
                            listeners: {
                                'keyup': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }, {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            layout: 'column',
                            width: 230,
                            items: {
                                xtype: 'label',
                                forId: caseNumberNamePrefixId,
                                html: _('first letter(s) of the ') + oCaseNumberLabels.last_name_label + _(' and')
                            }
                        }, {
                            xtype: 'numberfield',
                            name: 'cn-name-prefix-given-names',
                            width: 60,
                            minValue: 1,
                            maxValue: 20,
                            allowBlank: false,
                            disabled: true,
                            allowNegative: false,
                            allowDecimals: false,
                            enableKeyEvents: true,
                            listeners: {
                                'keyup': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }, {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            layout: 'column',
                            width: 350,
                            items: {
                                xtype: 'label',
                                forId: caseNumberNamePrefixId,
                                html: _('first letter(s) of the client ') + oCaseNumberLabels.first_name_label + _(' as the prefix')
                            }
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-3'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 295,
                            items: [
                                {
                                    xtype: 'button',
                                    tooltip: _('Move parameter up'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-up"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(true, 'cn-3');
                                    }
                                }, {
                                    xtype: 'button',
                                    tooltip: _('Move parameter down'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-down"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(false, 'cn-3');
                                    }
                                }, {
                                    id: caseNumberStartFromId,
                                    xtype: 'checkbox',
                                    name: 'cn-start-from',
                                    hideLabel: true,
                                    boxLabel: _('start Case File # from'),
                                    handler: this.toggleCheckboxRelatedFields.createDelegate(this)
                                }
                            ]
                        }, {
                            xtype: 'textfield',
                            name: 'cn-start-from-text',
                            width: 80,
                            minLength: 1,
                            maxLength: 9,
                            disabled: true,
                            allowBlank: false,
                            autoCreate: {tag: 'input', type: 'text', size: 9, autocomplete: 'off', maxlength: 9},
                            enableKeyEvents: true,
                            listeners: {
                                'keyup': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }, {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            layout: 'column',
                            items: [
                                {
                                    xtype: 'label',
                                    forId: caseNumberStartFromId,
                                    html: oCaseNumberLabels['case_number_start_duplicate']
                                }, {
                                    xtype: 'label',
                                    forId: globalOrBasedOnCaseTypeComboId,
                                    html: _('Global or Based on ') + oCaseNumberLabels['case_type'] + ':'
                                }
                            ],

                            listeners: {
                                'afterrender': function (cnt) {
                                    // Fix issue with dynamic labels
                                    var metrics = Ext.util.TextMetrics.createInstance(cnt.getEl());
                                    cnt.setWidth(metrics.getWidth(oCaseNumberLabels['case_number_start_duplicate'] + _('Global or Based on ') + oCaseNumberLabels['case_type'] + ':') + 30);
                                }
                            }
                        }, {
                            xtype: 'combo',
                            id: globalOrBasedOnCaseTypeComboId,
                            name: 'cn-global-or-based-on-case-type',
                            hiddenName: 'cn-global-or-based-on-case-type',
                            allowBlank: false,
                            store: {
                                xtype: 'arraystore',
                                fields: ['id', 'name'],
                                data: [['global', _('Global')], ['case-type', _('Based on ') + oCaseNumberLabels['case_type']]]
                            },
                            displayField: 'name',
                            valueField: 'id',
                            mode: 'local',
                            disabled: true,
                            value: 'global',
                            width: 140,
                            forceSelection: true,
                            editable: false,
                            triggerAction: 'all',
                            selectOnFocus: true,
                            typeAhead: false
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-4'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'button',
                            tooltip: _('Move parameter up'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-up"></i>',
                            handler: function () {
                                thisPanel.moveParameter(true, 'cn-4');
                            }
                        }, {
                            xtype: 'button',
                            tooltip: _('Move parameter down'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-down"></i>',
                            handler: function () {
                                thisPanel.moveParameter(false, 'cn-4');
                            }
                        }, {
                            xtype: 'checkbox',
                            name: 'cn-subclass',
                            hideLabel: true,
                            boxLabel: oCaseNumberLabels['subclass'],
                            handler: this.generateCaseName.createDelegate(this)
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-5'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 335,
                            items: [
                                {
                                    xtype: 'button',
                                    tooltip: _('Move parameter up'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-up"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(true, 'cn-5');
                                    }
                                }, {
                                    xtype: 'button',
                                    tooltip: _('Move parameter down'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-down"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(false, 'cn-5');
                                    }
                                }, {
                                    xtype: 'checkbox',
                                    name: 'cn-increment',
                                    hideLabel: true,
                                    boxLabel: _('total count of cases for the client.'),
                                    handler: this.toggleCheckboxRelatedFields.createDelegate(this)
                                }
                            ]
                        }, {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 450,
                            items: [
                                {
                                    xtype: 'checkbox',
                                    name: 'cn-increment-employer',
                                    hideLabel: true,
                                    boxLabel: _('Use Employer Case count if the case linked to an Employer')
                                }
                            ]
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-6'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-padding',
                            layout: 'column',
                            width: 295,
                            items: [
                                {
                                    xtype: 'button',
                                    tooltip: _('Move parameter up'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-up"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(true, 'cn-6');
                                    }
                                }, {
                                    xtype: 'button',
                                    tooltip: _('Move parameter down'),
                                    cls: 'move_button',
                                    text: '<i class="las la-arrow-down"></i>',
                                    handler: function () {
                                        thisPanel.moveParameter(false, 'cn-6');
                                    }
                                }, {
                                    id: caseNumberStartNumberFromId,
                                    xtype: 'checkbox',
                                    name: 'cn-start-number-from',
                                    hideLabel: true,
                                    boxLabel: _('start Case File # from'),
                                    handler: this.toggleCheckboxRelatedFields.createDelegate(this)
                                }
                            ]
                        }, {
                            xtype: 'textfield',
                            name: 'cn-start-number-from-text',
                            width: 80,
                            minLength: 1,
                            maxLength: 9,
                            disabled: true,
                            allowBlank: false,
                            autoCreate: {tag: 'input', type: 'text', size: 9, autocomplete: 'off', maxlength: 9},
                            enableKeyEvents: true,
                            listeners: {
                                'keyup': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }, {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            layout: 'column',
                            width: 415,
                            items: [
                                {
                                    xtype: 'label',
                                    forId: caseNumberStartNumberFromId,
                                    html: oCaseNumberLabels['case_number_start_from']
                                }, {
                                    xtype: 'label',
                                    forId: caseNumberResetEveryId,
                                    hidden: site_version != 'canada',
                                    html: _('Reset every:')
                                }
                            ]
                        }, {
                            xtype: 'combo',
                            id: caseNumberResetEveryId,
                            name: 'cn-reset-every',
                            hiddenName: 'cn-reset-every',
                            allowBlank: false,
                            store: {
                                xtype: 'arraystore',
                                fields: ['reset_id', 'reset_name'],
                                data: [['-', _('Not Reset')], ['month', _('Month')], ['year', _('Year')]]
                            },
                            displayField: 'reset_name',
                            valueField: 'reset_id',
                            mode: 'local',
                            disabled: true,
                            value: '-',
                            width: 150,
                            forceSelection: true,
                            hidden: site_version != 'canada',
                            editable: false,
                            triggerAction: 'all',
                            selectOnFocus: true,
                            typeAhead: false,
                            listeners: {
                                afterrender: function (combo) {
                                    new Ext.ToolTip({
                                        target: combo.getEl(),
                                        autoWidth: true,
                                        cls: 'not-bold-header',
                                        header: true,
                                        hideDelay: 1000,
                                        showDelay: 100,
                                        trackMouse: true,
                                        listeners: {
                                            beforeshow: function (tooltip) {
                                                tooltip.setTitle(_('Make sure you have a component granting file number uniqueness when reset will occur'));
                                            }
                                        }
                                    });
                                }
                            }
                        }, {
                            xtype: 'button',
                            name: 'cn-reset-now',
                            text: _('Reset Now'),
                            cls: 'main-btn',
                            disabled: true,
                            hidden: site_version != 'canada',
                            style: 'margin-left: 10px;',
                            handler: thisPanel.showResetNowDialog.createDelegate(thisPanel)
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-7'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    hidden: empty(oClientProfileIdSettings.enabled),
                    items: [
                        {
                            xtype: 'button',
                            tooltip: _('Move parameter up'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-up"></i>',
                            handler: function () {
                                thisPanel.moveParameter(true, 'cn-7');
                            }
                        }, {
                            xtype: 'button',
                            tooltip: _('Move parameter down'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-down"></i>',
                            handler: function () {
                                thisPanel.moveParameter(false, 'cn-7');
                            }
                        }, {
                            xtype: 'checkbox',
                            name: 'cn-client-profile-id',
                            hideLabel: true,
                            boxLabel: _('Client Profile ID (Employer profiles are given a higher priority)'),
                            handler: this.generateCaseName.createDelegate(this)
                        }
                    ]
                }, {
                    id: 'cn-row-' + oCaseNumberSettings['cn-8'],
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'button',
                            tooltip: _('Move parameter up'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-up"></i>',
                            handler: function () {
                                thisPanel.moveParameter(true, 'cn-8');
                            }
                        }, {
                            xtype: 'button',
                            tooltip: _('Move parameter down'),
                            cls: 'move_button',
                            text: '<i class="las la-arrow-down"></i>',
                            handler: function () {
                                thisPanel.moveParameter(false, 'cn-8');
                            }
                        }, {
                            xtype: 'checkbox',
                            name: 'cn-number-of-client-cases',
                            hideLabel: true,
                            boxLabel: _('Number of Client Cases (Employer profiles are given a higher priority)'),
                            handler: this.generateCaseName.createDelegate(this)
                        }
                    ]
                }, {
                    xtype: 'container',
                    layout: 'column',
                    style: 'margin-top: 30px; margin-bottom: 5px',
                    items: [
                        {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            width: 30,
                            items: {
                                xtype: 'label',
                                html: _('Use')
                            }
                        }, {
                            xtype: 'combo',
                            name: 'cn-separator',
                            allowBlank: false,
                            store: {
                                xtype: 'arraystore',
                                fields: ['separator_id', 'separator_name'],
                                data: [['', _('blank')], ['.', '.'], ['-', '-'], ['_', '_'], ['/', '/']]
                            },
                            displayField: 'separator_name',
                            valueField: 'separator_id',
                            mode: 'local',
                            value: '/',
                            width: 90,
                            forceSelection: true,
                            editable: false,
                            triggerAction: 'all',
                            selectOnFocus: true,
                            typeAhead: false,
                            listeners: {
                                'select': thisPanel.generateCaseName.createDelegate(thisPanel)
                            }
                        }, {
                            xtype: 'container',
                            cls: 'with-top-bigger-padding',
                            style: 'padding-left: 10px',
                            width: 120,
                            items: {
                                xtype: 'label',
                                html: _('as the separator')
                            }
                        }
                    ]
                }, {
                    xtype: 'container',
                    style: 'padding-top: 10px',
                    items: {
                        xtype: 'checkbox',
                        name: 'cn-read-only',
                        hideLabel: true,
                        boxLabel: _('Case File # is Read only Field - User cannot edit field')
                    }
                }, {
                    xtype: 'fieldset',
                    title: 'Example',
                    style: 'margin-top: 20px',
                    autoHeight: true,
                    labelWidth: 200,
                    items: [
                        {
                            name: 'example-family-name',
                            xtype: 'displayfield',
                            fieldLabel: oCaseNumberLabels['last_name_label'],
                            value: 'Smith'
                        }, {
                            name: 'example-given-names',
                            xtype: 'displayfield',
                            fieldLabel: oCaseNumberLabels['first_name_label'],
                            value: 'John'
                        }, {
                            name: 'example-subclass-digits',
                            xtype: 'displayfield',
                            fieldLabel: oCaseNumberLabels['subclass'],
                            value: '123'
                        }, {
                            name: 'example-total-cases-count',
                            xtype: 'displayfield',
                            fieldLabel: _('Total count of cases'),
                            value: '456'
                        }, {
                            name: 'example-client-profile-id',
                            xtype: 'displayfield',
                            fieldLabel: _('Client Profile ID'),
                            hidden: empty(oClientProfileIdSettings.enabled),
                            value: oClientProfileIdSettings.format.replaceAll('{client_id_sequence}', oClientProfileIdSettings.start_from)
                        }, {
                            name: 'example-number-of-client-cases',
                            xtype: 'displayfield',
                            fieldLabel: _('Number of Client Cases'),
                            value: '1'
                        }, {
                            name: 'example-generated-case-number',
                            xtype: 'displayfield',
                            fieldLabel: _('Generated Case File #'),
                            labelStyle: 'font-weight: bold; width: 200px;',
                            value: ''
                        }
                    ]
                }
            ]
        }
    ];

    this.buttons = [
        {
            xtype: 'button',
            text: _('Cancel'),
            handler: this.resetSettings.createDelegate(this)
        },
        {
            xtype: 'button',
            text: '<i class="las la-save"></i>' + _('Save'),
            cls: 'orange-btn',
            handler: this.saveSettings.createDelegate(this)
        }
    ];

    companyCaseNumberForm.superclass.constructor.call(this, {
        buttonAlign: 'center'
    });

    this.on('afterlayout', this.loadSettings.createDelegate(this));
    this.on('afterlayout', this.initParametersOrder.createDelegate(this));
};

Ext.extend(companyCaseNumberForm, Ext.form.FormPanel, {
    fixCaseNumberPageHeight: function (formName) {
        var new_height;
        if ($('#' + formName).height() <= $('#admin-left-panel').outerHeight()) {
            new_height = $('#admin-left-panel').outerHeight();
        } else {
            new_height = $('#' + formName).height() + 82;
        }
        $("#admin_section_frame", top.document).height(new_height + 'px');
    },

    toggleFormFields: function (booShow) {
        Ext.getCmp(this.containerId).setVisible(booShow);
        this.generateCaseName();

        this.fixCaseNumberPageHeight('case-number-form');
    },

    toggleCheckboxRelatedField: function (item, booChecked) {
        if (!['label', 'container', 'fieldset', 'button'].has(item.getXType()) || (site_version == 'canada' && item.name == 'cn-reset-now')) {
            if (site_version == 'canada' && item.name == 'cn-start-number-from-text') {
                item.setDisabled(false);
                if (item.getValue() == '') {
                    item.setDisabled(!booChecked);
                } else {
                    item.setDisabled(true);
                }
            } else {
                item.setDisabled(!booChecked);
            }
            if (!['button'].has(item.getXType())) {
                item.clearInvalid();
            }
        }
    },

    toggleCheckboxRelatedFields: function (checkbox, booChecked) {
        var thisForm = this;
        checkbox.ownerCt.ownerCt.items.each(function (item) {
            if (item.getXType() == 'checkbox' && item.name == checkbox.name) {
                return;
            }

            if (item.getXType() == 'container' && item.items.length) {
                item.items.each(function (subitem) {
                    if (subitem.getXType() == 'checkbox' && subitem.name == checkbox.name) {
                        return;
                    }

                    thisForm.toggleCheckboxRelatedField(subitem, booChecked);
                });
            } else {
                thisForm.toggleCheckboxRelatedField(item, booChecked);
            }
        });

        this.generateCaseName();
    },

    highlightEl: function (fieldId) {
        // Prevent highlight several times
        var field = Ext.get(fieldId);
        if (field && !field.hasActiveFx()) {
            field.highlight('FF8432', {attr: 'color', duration: 2});
        }
    },

    generateCaseName: function () {
        var arrCaseNumberParts = [],
            familyNameValue = this.getFieldValueByName('example-family-name'),
            givenNamesValue = this.getFieldValueByName('example-given-names'),
            subclassDigitsValue = this.getFieldValueByName('example-subclass-digits'),
            totalCasesCountValue = this.getFieldValueByName('example-total-cases-count'),
            clientProfileIdValue = this.getFieldValueByName('example-client-profile-id'),
            numberOfClientCasesValue = this.getFieldValueByName('example-number-of-client-cases'),
            separatorValue = this.getFieldValueByName('cn-separator'),
            generatedCaseNumberField = this.getFieldByName('example-generated-case-number');

        if (this.getFieldValueByName('cn-include-fixed-prefix')) {
            var fixedPrefix = this.getFieldValueByName('cn-include-fixed-prefix-text');

            var date = new Date();
            fixedPrefix = fixedPrefix.replace(/%year%/ig, date.getFullYear());
            fixedPrefix = fixedPrefix.replace(/%short_year%/ig, date.getFullYear().toString().substring(2));
            fixedPrefix = fixedPrefix.replace(/%month%/ig, ("0" + (date.getMonth() + 1)).slice(-2));
            fixedPrefix = fixedPrefix.replace(/%day%/ig, ("0" + date.getDate()).slice(-2));

            if (!empty(fixedPrefix)) {
                arrCaseNumberParts[oCaseNumberSettings['cn-1']] = fixedPrefix;
            }
        }

        if (this.getFieldValueByName('cn-name-prefix')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-2']] = familyNameValue.substring(0, parseInt(this.getFieldValueByName('cn-name-prefix-family-name'), 10)) +
                givenNamesValue.substring(0, parseInt(this.getFieldValueByName('cn-name-prefix-given-names'), 10));
        }

        if (this.getFieldValueByName('cn-start-from')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-3']] = this.getFieldValueByName('cn-start-from-text');
        }

        if (this.getFieldValueByName('cn-subclass')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-4']] = subclassDigitsValue;
        }

        if (this.getFieldValueByName('cn-increment')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-5']] = totalCasesCountValue;
        }

        if (this.getFieldValueByName('cn-start-number-from')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-6']] = this.getFieldValueByName('cn-start-number-from-text');
        }

        if (this.getFieldValueByName('cn-client-profile-id')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-7']] = clientProfileIdValue;
        }

        if (this.getFieldValueByName('cn-number-of-client-cases')) {
            arrCaseNumberParts[oCaseNumberSettings['cn-8']] = numberOfClientCasesValue;
        }

        for (var i = arrCaseNumberParts.length; i >= 0; i--) {
            if (!arrCaseNumberParts[i]) arrCaseNumberParts.splice(i, 1);
        }

        if (separatorValue == 'blank') {
            separatorValue = '';
        }

        if (generatedCaseNumberField) {
            generatedCaseNumberField.setValue(implode(separatorValue, arrCaseNumberParts));

            this.highlightEl(generatedCaseNumberField.getId());
        }
    },

    getFieldByName: function (fieldName) {
        var arrFields = this.find('name', fieldName);
        return arrFields.length ? arrFields[0] : undefined;
    },

    getFieldValueByName: function (fieldName) {
        var arrFields = this.find('name', fieldName);
        return arrFields.length ? arrFields[0].getValue() : '';
    },

    moveParameter: function (booUp, containerId) {
        var selectedParameterNumber = parseInt(oCaseNumberSettings[containerId]);
        var booMove = false;
        var newParameterNumber = 0;
        if (booUp && selectedParameterNumber > 1) {
            newParameterNumber = selectedParameterNumber - 1;
            booMove = true;
        } else if (!booUp && selectedParameterNumber < 8) {
            newParameterNumber = selectedParameterNumber + 1;
            booMove = true;
        }
        if (booMove) {
            var selectedParameterId = $('#cn-row-' + selectedParameterNumber);
            var newParameterId = $('#cn-row-' + newParameterNumber);
            if (!booUp) {
                selectedParameterId.insertAfter(newParameterId);
            } else {
                selectedParameterId.insertBefore(newParameterId);
            }
            newParameterId.attr('id', 'cn-row-tmp');
            selectedParameterId.attr('id', 'cn-row-' + newParameterNumber);
            $('#cn-row-tmp').attr('id', 'cn-row-' + selectedParameterNumber);

            for (var key in oCaseNumberSettings) {
                if (oCaseNumberSettings.hasOwnProperty(key)) {
                    if (oCaseNumberSettings[key] == newParameterNumber) {
                        oCaseNumberSettings[key] = selectedParameterNumber;
                    }
                }
            }
            oCaseNumberSettings[containerId] = newParameterNumber;
            this.generateCaseName();
        }
    },

    showResetNowDialog: function () {
        var thisForm = this;

        var notification = new Ext.Panel({
            style: 'font-size: 12px; color: red; margin-bottom: 5px;',
            html: '<span style="padding-bottom: 3px; display:inline-block;">' + _('You are about to change counter, which may lead to duplicate file numbers.') + '</span></br>' +
                '<span>' + _('If you are confident in what you are doing, type new counter value below.') + '</span>'
        });

        var startFrom = new Ext.form.TextField({
            width: 100,
            minLength: 1,
            maxLength: 9,
            allowBlank: false,
            value: thisForm.getFieldValueByName('cn-start-number-from-text')
        });

        var win = new Ext.Window({
            layout: 'form',
            resizable: false,
            title: _('Reset Now'),
            autoHeight: true,
            autoWidth: true,
            modal: true,
            items: [
                notification,
                {
                    xtype: 'panel',
                    style: 'padding-left: 150px;',
                    items: [
                        startFrom
                    ]
                }
            ],

            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: _('OK'),
                    cls: 'orange-btn',
                    handler: function () {
                        var startFromValue = startFrom.getValue();

                        if (empty(startFromValue)) {
                            return false;
                        }

                        win.getEl().mask('Saving...');

                        // Save changes
                        Ext.Ajax.request({
                            url: baseUrl + '/manage-company/case-number-settings-reset-counter',
                            params: {
                                start_from: Ext.encode(startFromValue)
                            },
                            success: function (result) {
                                var resultData = Ext.decode(result.responseText);
                                if (resultData.success) {
                                    win.getEl().mask('Done!');

                                    thisForm.getFieldByName('cn-start-number-from-text').setValue(startFromValue);
                                    thisForm.generateCaseName();

                                    setTimeout(function () {
                                        win.getEl().unmask();
                                        win.close();
                                    }, 750);
                                } else {
                                    // Show error message
                                    win.getEl().unmask();
                                    Ext.simpleConfirmation.error(resultData.message);
                                }
                            },

                            failure: function () {
                                win.getEl().unmask();
                                Ext.simpleConfirmation.error(_('Cannot save changes'));
                            }
                        });
                    }
                }
            ]
        });

        win.show();
        win.center();
    },

    loadSettings: function () {
        var thisPanel = this;

        for (var property in oCaseNumberSettings) {
            if (oCaseNumberSettings.hasOwnProperty(property)) {
                var arrFields = thisPanel.find('name', property);
                Ext.each(arrFields, function (field) {
                    field.setValue(oCaseNumberSettings[property]);
                    if (site_version == 'canada' && field.name == 'cn-start-number-from-text') {
                        if (field.getValue() != '') {
                            field.setDisabled(true);
                        }
                    }
                });
            }
        }

        this.generateCaseName();
    },

    initParametersOrder: function () {
        $('#cn-row-2').insertAfter($('#cn-row-1'));
        $('#cn-row-3').insertAfter($('#cn-row-2'));
        $('#cn-row-4').insertAfter($('#cn-row-3'));
        $('#cn-row-5').insertAfter($('#cn-row-4'));
        $('#cn-row-6').insertAfter($('#cn-row-5'));
        $('#cn-row-7').insertAfter($('#cn-row-6'));
        $('#cn-row-8').insertAfter($('#cn-row-7'));
    },

    saveSettings: function () {
        var thisForm = this;

        if (this.getForm().isValid()) {
            var body = Ext.getBody();
            var startFromField = thisForm.getFieldByName('cn-start-number-from-text');
            body.mask(_('Saving...'));

            if (site_version == 'canada') {
                if (startFromField.getValue() != '') {
                    startFromField.setDisabled(false);
                }
            } else {
                thisForm.getFieldByName('cn-reset-every').setDisabled(true);
            }

            this.getForm().submit({
                url: baseUrl + '/manage-company/case-number-settings-save',
                params: {
                    'cn-1': oCaseNumberSettings['cn-1'],
                    'cn-2': oCaseNumberSettings['cn-2'],
                    'cn-3': oCaseNumberSettings['cn-3'],
                    'cn-4': oCaseNumberSettings['cn-4'],
                    'cn-5': oCaseNumberSettings['cn-5'],
                    'cn-6': oCaseNumberSettings['cn-6'],
                    'cn-7': oCaseNumberSettings['cn-7'],
                    'cn-8': oCaseNumberSettings['cn-8']
                },
                success: function (form, action) {
                    if (site_version == 'canada') {
                        startFromField.setDisabled(true);
                    }
                    var res = action.result;
                    Ext.simpleConfirmation.msg(_('Success'), res.message, 1500);
                    body.unmask();
                },

                failure: function (form, action) {
                    if (site_version == 'canada') {
                        startFromField.setDisabled(true);
                    }

                    var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                    Ext.simpleConfirmation.error(msg);
                    body.unmask();
                }
            });
        }
    },

    resetSettings: function () {
        window.location = window.location.href;
    }
});

Ext.onReady(function () {
    // Init tooltips
    Ext.QuickTips.init();

    $('#case-number-form').css('min-height', getSuperadminPanelHeight() + 'px');

    var oForm = new companyCaseNumberForm({});
    oForm.render('case-number-form');
    updateSuperadminIFrameHeight('#case-number-form');
});