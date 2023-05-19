function faq_section(params) {
    switch (params.action) {
        case 'up' :
        case 'down' :
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-faq/section-' + params.action,
                params: {
                    faq_section_id: params.faq_section_id,
                    section_type: Ext.encode(Ext.getCmp('combo-help-type').getValue())
                },
                success: function () {
                    reloadFAQ();
                    Ext.getBody().unmask();
                },
                failure: function () {
                    Ext.simpleConfirmation.error('Can\'t load Help Section information');
                    Ext.getBody().unmask();
                }
            });
            break;

        case 'add' :
        case 'edit' :
            //get FAQ sections detail info
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-faq/get-faq-section',
                params: {
                    faq_section_id: Ext.encode(params.faq_section_id),
                    section_type: Ext.encode(Ext.getCmp('combo-help-type').getValue())
                },

                success: function (result) {
                    var section = Ext.decode(result.responseText);

                    var editor = new Ext.ux.form.FroalaEditor({
                        id: 'faq-description',
                        fieldLabel: 'Description',
                        width: 780,
                        height: 300,
                        anchor: '98%',
                        allowBlank: true,
                        value: section.section_description,
                        booAllowImagesUploading: true
                    });

                    var parentSections = new Ext.data.Store({
                        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                            'faq_section_id',
                            'section_name'
                        ])),
                        data: []
                    });

                    parentSections.loadData(section.parent_sections);

                    var title;
                    if (empty(params.parent_category_id)) {
                        title = params.action == 'add' ? 'Add Category' : 'Edit Category';
                    } else {
                        title = params.action == 'add' ? 'Add Sub Category' : 'Edit Sub Category';
                    }

                    var win = new Ext.Window({
                        title: title,
                        layout: 'form',
                        modal: true,
                        width: 950,
                        y: 10,
                        autoHeight: true,
                        resizable: true,
                        minWidth: 550,

                        items: new Ext.FormPanel(
                            {
                                id: 'faq-panel',
                                layout: 'form',
                                style: 'background-color:#fff; padding:5px;',
                                labelWidth: 155,
                                items: [
                                    {
                                        id: 'faq-name',
                                        xtype: 'textfield',
                                        fieldLabel: empty(params.parent_category_id) ? 'Category Name' : 'Sub Category Name',
                                        width: '98%',
                                        value: section.section_name
                                    }, {
                                        id: 'faq-subtitle',
                                        xtype: 'textfield',
                                        fieldLabel: 'Subtitle',
                                        width: '98%',
                                        hidden: !empty(params.parent_category_id),
                                        value: section.section_subtitle
                                    }, editor, {
                                        id: 'faq-color',
                                        xtype: 'textfield',
                                        fieldLabel: 'Color: <img src="/images/icons/help.png" ext:qtip="Supported formats: RGB, HEX, HSL, RGBA, HSLA (e.g. #CCCCCC; or red)" />',
                                        labelSeparator: '',
                                        width: 375,
                                        hidden: !empty(params.parent_category_id),
                                        value: section.section_color
                                    }, {
                                        id: 'faq-class',
                                        xtype: 'textfield',
                                        fieldLabel: 'Icon class',
                                        width: 375,
                                        emptyText: 'E.g.: fas fa-check',
                                        hidden: !empty(params.parent_category_id),
                                        value: section.section_class
                                    }, {
                                        style: 'padding-left: 160px; margin-bottom: 10px',
                                        hidden: !empty(params.parent_category_id),
                                        html: 'Supported fontawesome, check <a href="https://fontawesome.com/icons?d=gallery&m=free" target="_blank">here</a>'
                                    }, {
                                        id: 'faq-parent',
                                        xtype: 'combo',
                                        mode: 'local',
                                        store: parentSections,
                                        fieldLabel: 'Parent Category Name',
                                        width: 375,
                                        valueField: 'faq_section_id',
                                        displayField: 'section_name',
                                        editable: false,
                                        triggerAction: 'all',
                                        hidden: empty(params.parent_category_id),
                                        value: params.parent_category_id ? params.parent_category_id : section.parent_section_id
                                    }, {
                                        id: 'faq-before',
                                        xtype: 'combo',
                                        mode: 'local',
                                        store: parentSections,
                                        fieldLabel: 'Insert Category Before',
                                        width: 375,
                                        valueField: 'faq_section_id',
                                        displayField: 'section_name',
                                        editable: false,
                                        triggerAction: 'all',
                                        hidden: params.action === 'edit' || !empty(params.parent_category_id),
                                        value: parentSections.getCount() ? parentSections.getAt(0).data.faq_section_id : ''
                                    }, {
                                        xtype: 'container',
                                        layout: 'column',
                                        hidden: !empty(params.parent_category_id),

                                        items: [
                                            {
                                                id: 'faq-external-link',
                                                xtype: 'checkbox',
                                                boxLabel: 'External Link',
                                                checked: !empty(section.section_external_link),
                                                listeners: {
                                                    'check': function (checkbox, booChecked) {
                                                        Ext.getCmp('faq-external-link-url').setDisabled(!booChecked);
                                                        if (!booChecked) {
                                                            Ext.getCmp('faq-external-link-url').setValue();
                                                        }
                                                    }
                                                }
                                            }, {
                                                style: 'padding: 2px 10px 2px 39px;',
                                                html: 'URL:'
                                            }, {
                                                id: 'faq-external-link-url',
                                                xtype: 'textfield',
                                                width: 380,
                                                disabled: empty(section.section_external_link),
                                                value: section.section_external_link
                                            }
                                        ]
                                    }, {
                                        html: '&nbsp;' // spacer
                                    }, {
                                        id: 'faq-show-as-heading',
                                        xtype: 'checkbox',
                                        boxLabel: 'Show as a heading - not a hyperlink',
                                        hidden: empty(params.parent_category_id),
                                        checked: section.section_show_as_heading === 'Y'
                                    }, {
                                        id: 'faq-client-view',
                                        xtype: 'checkbox',
                                        boxLabel: 'Visible to Clients',
                                        checked: section.client_view == 'Y'
                                    }, {
                                        id: 'faq-section-hidden',
                                        xtype: 'checkbox',
                                        boxLabel: 'Hide in Help Center and searches',
                                        checked: section.section_is_hidden === 'Y'
                                    }
                                ]
                            }
                        ),

                        buttons: [
                            {
                                text: 'Cancel',
                                handler: function () {
                                    win.close();
                                }
                            }, {
                                text: params.action == 'add' ? 'Add' : 'Save',
                                cls: 'orange-btn',
                                handler: function () {
                                    var name = Ext.getCmp('faq-name').getValue();
                                    if (name === '') {
                                        Ext.getCmp('faq-name').markInvalid();
                                        return false;
                                    }

                                    //save msg
                                    var fp = Ext.getCmp('faq-panel').getForm();
                                    fp.getEl().mask('Saving...');

                                    //save
                                    Ext.Ajax.request({
                                        url: baseUrl + "/manage-faq/section-" + params.action,
                                        params: {
                                            faq_section_id: params.faq_section_id,
                                            section_type: Ext.encode(Ext.getCmp('combo-help-type').getValue()),
                                            section_name: Ext.encode(name),
                                            section_subtitle: Ext.encode(Ext.getCmp('faq-subtitle').getValue()),
                                            section_description: Ext.encode(Ext.getCmp('faq-description').getValue()),
                                            section_color: Ext.encode(Ext.getCmp('faq-color').getValue()),
                                            section_class: Ext.encode(Ext.getCmp('faq-class').getValue()),
                                            section_external_link: Ext.encode(Ext.getCmp('faq-external-link-url').getValue()),
                                            section_show_as_heading: Ext.encode(Ext.getCmp('faq-show-as-heading').getValue()),
                                            section_is_hidden: Ext.encode(Ext.getCmp('faq-section-hidden').getValue()),
                                            parent_section_id: Ext.getCmp('faq-parent').getValue(),
                                            before_section_id: Ext.getCmp('faq-before').getValue(),
                                            client_view: Ext.encode(Ext.getCmp('faq-client-view').getValue())
                                        },

                                        success: function () {
                                            fp.getEl().mask('Done!');

                                            reloadFAQ();

                                            setTimeout(function () {
                                                fp.getEl().unmask();
                                                win.close();
                                            }, 750);
                                        },
                                        failure: function () {
                                            Ext.Msg.alert('Status', 'Saving Error');
                                            fp.getEl().unmask();
                                        }
                                    });
                                }
                            }
                        ]
                    });

                    win.show();

                    Ext.getBody().unmask();
                },
                failure: function () {
                    Ext.simpleConfirmation.error('Can\'t load Help Section information');
                    Ext.getBody().unmask();
                }
            });
            break;

        case 'delete' :
            Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this Section?', function (btn) {
                if (btn == 'yes') {
                    Ext.getBody().mask('Deleting...');

                    Ext.Ajax.request({
                        url: baseUrl + '/manage-faq/section-delete',
                        params: {
                            faq_section_id: params.faq_section_id
                        },
                        success: function (f) {
                            Ext.getBody().unmask();

                            var result = Ext.decode(f.responseText);

                            if (result.success)
                                reloadFAQ();
                            else
                                Ext.simpleConfirmation.error('Delete or move all the items in this category before being able to delete the category.', 'Error');
                        },
                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error('This Section can\'t be deleted. Please try again later.', 'Error');
                        }
                    });
                }
            });
            break;

        default:
            break;
    }
}

function faq(params) {
    Ext.QuickTips.init();

    switch (params.action) {
        case 'up' :
        case 'down' :
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-faq/' + params.action,
                params: {
                    faq_id: params.faq_id
                },
                success: function () {
                    reloadFAQ();
                    Ext.getBody().unmask();
                },
                failure: function () {
                    Ext.Msg.alert('Status', 'Can\'t load information');
                    Ext.getBody().unmask();
                }
            });
            break;

        case 'add' :
        case 'edit' :
            //get FAQ detail info
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-faq/get-faq',
                params: {
                    faq_id: params.faq_id,
                    section_type: Ext.encode(Ext.getCmp('combo-help-type').getValue())
                },

                success: function (result) {
                    var faq = Ext.decode(result.responseText);

                    var parentSections = new Ext.data.Store({
                        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                            'faq_section_id',
                            'level',
                            'section_name'
                        ])),

                        data: faq.parent_sections
                    });

                    var editor = new Ext.ux.form.FroalaEditor({
                        fieldLabel: 'Help Content',
                        hideLabel: false,
                        id: 'faq-answer',
                        width: 820,
                        height: 300,
                        anchor: '98%',
                        allowBlank: false,
                        value: faq.answer,
                        booAllowImagesUploading: true,
                        FroalaConfig: {
                            linkAlwaysBlank: false
                        }
                    });

                    var toggleWalkthorughFields = function (comboValue) {
                        var booWalkthrough = comboValue === 'walkthrough';
                        Ext.getCmp('faq-inlinemanual-topic-id').setVisible(booWalkthrough);
                        Ext.getCmp('faq-featured').setVisible(!booWalkthrough);
                        Ext.getCmp('faq-meta-tags').setVisible(!booWalkthrough);
                        editor.setVisible(!booWalkthrough);

                        win.syncShadow();
                    }

                    var win = new Ext.Window({
                        title: params.action == 'add' ? 'Add FAQ/Help Topic' : 'Edit FAQ/Help Topic',
                        layout: 'form',
                        modal: true,
                        width: 950,
                        y: 10,
                        autoHeight: true,
                        resizable: true,
                        minWidth: 550,

                        items: new Ext.FormPanel({
                            id: 'faq-panel',
                            layout: 'form',
                            style: 'background-color:#fff; padding:5px;',
                            labelWidth: 125,
                            items: [
                                {
                                    id: 'faq-question',
                                    xtype: 'textfield',
                                    fieldLabel: 'Help Topic Title',
                                    width: '98%',
                                    value: faq.question
                                }, {
                                    id: 'faq-content-type',
                                    xtype: 'combo',
                                    fieldLabel: 'Content Type',
                                    allowBlank: false,
                                    store: {
                                        xtype: 'arraystore',
                                        fields: ['option_id', 'option_icon', 'option_name'],
                                        data: [
                                            ['text', 'page_white_text.png', 'Text'],
                                            ['video', 'control_play.png', 'Video'],
                                            ['walkthrough', 'page_white_star.png', 'Walkthrough']
                                        ]
                                    },

                                    tpl: new Ext.XTemplate(
                                        '<tpl for=".">',
                                        String.format('<div class="x-combo-list-item" style="height: 16px"><img src="{0}/images/icons/{option_icon}" alt="{option_name}" align="top" />&nbsp;&nbsp;{option_name}</div>', topBaseUrl),
                                        '</tpl>'
                                    ),

                                    value: empty(faq.content_type) ? 'text' : faq.content_type,
                                    displayField: 'option_name',
                                    valueField: 'option_id',
                                    typeAhead: false,
                                    mode: 'local',
                                    triggerAction: 'all',
                                    width: 400,
                                    editable: false,

                                    listeners: {
                                        'beforeselect': function (combo, rec) {
                                            // Automatically toggle related fields
                                            toggleWalkthorughFields(rec.data[combo.valueField]);
                                        },

                                        'render': function (combo) {
                                            // Automatically toggle related fields
                                            toggleWalkthorughFields(combo.getValue());
                                        }
                                    }
                                }, {
                                    id: 'faq-inlinemanual-topic-id',
                                    xtype: 'textfield',
                                    fieldLabel: 'InlineManual Topic Id',
                                    maxLength: 50,
                                    autoCreate: {tag: 'input', type: 'text', size: '50', autocomplete: 'off', maxlength: '50'},
                                    width: 400,
                                    hidden: faq.content_type !== 'walkthrough',
                                    value: faq.inlinemanual_topic_id
                                }, {
                                    id: 'faq-insert-to-section',
                                    xtype: 'combo',
                                    mode: 'local',
                                    store: parentSections,
                                    fieldLabel: 'Insert In Category',
                                    width: 400,
                                    valueField: 'faq_section_id',
                                    displayField: 'section_name',
                                    editable: false,
                                    triggerAction: 'all',
                                    tpl: new Ext.XTemplate('<tpl for="."><div class="x-combo-list-item" style="padding: 5px; padding-left: {level * 15}px">{section_name}</div></tpl>'),
                                    value: parentSections.getCount() ? params.faq_section_id : ''
                                }, editor, {
                                    xtype: 'container',
                                    layout: 'column',
                                    height: 40,
                                    items: [
                                        {
                                            id: 'faq-client-view',
                                            xtype: 'checkbox',
                                            boxLabel: 'Visible to Client',
                                            value: 'Y',
                                            checked: (empty(faq.client_view) || faq.client_view === 'Y')
                                        }, {
                                            id: 'faq-featured',
                                            xtype: 'checkbox',
                                            boxLabel: 'Show as Featured Article',
                                            value: 'Y',
                                            style: 'margin-left: 15px',
                                            checked: faq.featured === 'Y'
                                        }
                                    ]
                                }, {
                                    id: 'faq-tags',
                                    xtype: 'lovcombo',
                                    fieldLabel: 'Assigned Tags',
                                    allowBlank: true,
                                    width: 400,

                                    store: {
                                        xtype: 'store',
                                        reader: new Ext.data.JsonReader({
                                            id: 'faq_tag_id'
                                        }, [
                                            {name: 'faq_tag_id'},
                                            {name: 'faq_tag_text'}
                                        ]),

                                        data: faq.tags
                                    },

                                    triggerAction: 'all',
                                    valueField: 'faq_tag_id',
                                    displayField: 'faq_tag_text',
                                    mode: 'local',
                                    useSelectAll: true,
                                    value: faq.faq_assigned_tags
                                }, {
                                    id: 'faq-meta-tags',
                                    xtype: 'textarea',
                                    fieldLabel: 'Search Meta Tags',
                                    width: '98%',
                                    value: faq.meta_tags,
                                    height: 50
                                }
                            ]
                        }),

                        buttons: [
                            {
                                text: 'Cancel',
                                handler: function () {
                                    win.close();
                                }
                            },
                            {
                                text: params.action == 'add' ? 'Add' : 'Save',
                                cls: 'orange-btn',
                                handler: function () {
                                    var question = Ext.getCmp('faq-question').getValue();
                                    var answer = Ext.getCmp('faq-answer').getValue();
                                    var helpType = Ext.getCmp('faq-content-type').getValue();
                                    var topicId = Ext.getCmp('faq-inlinemanual-topic-id').getValue();
                                    var booWalkthrough = helpType === 'walkthrough';

                                    if (trim(question) === '') {
                                        Ext.getCmp('faq-question').markInvalid('Question cannot be empty');
                                        return false;
                                    }

                                    if (booWalkthrough) {
                                        if (topicId === '') {
                                            Ext.getCmp('faq-inlinemanual-topic-id').markInvalid('Topic Id cannot be empty');
                                            return false;
                                        }
                                    } else {
                                        if (answer === '') {
                                            Ext.simpleConfirmation.error('Answer cannot be empty');
                                            return false;
                                        }
                                    }

                                    //save msg
                                    var fp = Ext.getCmp('faq-panel').getForm();
                                    fp.getEl().mask('Saving...');

                                    //save
                                    Ext.Ajax.request({
                                        url: baseUrl + "/manage-faq/" + params.action,

                                        params: {
                                            faq_id: params.faq_id,
                                            question: Ext.encode(question),
                                            answer: Ext.encode(answer),
                                            faq_assigned_tags: Ext.encode(Ext.getCmp('faq-tags').getValue()),
                                            faq_meta_tags: booWalkthrough ? '' : Ext.encode(Ext.getCmp('faq-meta-tags').getValue()),
                                            faq_content_type: helpType,
                                            faq_client_view: Ext.getCmp('faq-client-view').getValue() ? 'Y' : 'N',
                                            faq_featured: booWalkthrough ? 'N' : (Ext.getCmp('faq-featured').getValue() ? 'Y' : 'N'),
                                            faq_section_id: Ext.getCmp('faq-insert-to-section').getValue(),
                                            faq_inlinemanual_topic_id: booWalkthrough ? topicId : ''
                                        },

                                        success: function (result) {
                                            var resultDecoded = Ext.decode(result.responseText);
                                            if (resultDecoded.success) {
                                                fp.getEl().mask('Done!');

                                                reloadFAQ();

                                                setTimeout(function () {
                                                    fp.getEl().unmask();
                                                    win.close();
                                                }, 750);
                                            } else {
                                                // Show error message
                                                Ext.simpleConfirmation.error(resultDecoded.message);
                                                fp.getEl().unmask();
                                            }
                                        },

                                        failure: function () {
                                            Ext.simpleConfirmation.error(_('Saving Error. Please try again later.'));
                                            fp.getEl().unmask();
                                        }
                                    });
                                }
                            }
                        ]
                    });

                    win.show();

                    Ext.getBody().unmask();
                },
                failure: function () {
                    Ext.Msg.alert('Status', 'Can\'t load information');
                    Ext.getBody().unmask();
                }
            });
            break;

        case 'delete' :
            Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this item?', function (btn) {
                if (btn == 'yes') {
                    Ext.Ajax.request({
                        url: baseUrl + '/manage-faq/delete',
                        params: {
                            faq_id: params.faq_id
                        },
                        success: function () {
                            reloadFAQ();
                        },
                        failure: function () {
                            Ext.Msg.alert('Status', 'This item can\'t be deleted. Please try again later.');
                        }
                    });
                }
            });
            break;

        default:
            break;
    }
}

function reloadFAQ(helpType) {
    Ext.getBody().mask('Loading...');
    helpType = empty(helpType) ? Ext.getCmp('combo-help-type').getValue() : helpType;
    location.href = baseUrl + '/manage-faq/index?type=' + helpType;
}

$(document).ready(function () {
    new Ext.form.ComboBox({
        id: 'combo-help-type',
        editable: false,
        readOnly: true,
        typeAhead: false,
        triggerAction: 'all',
        transform: 'help-type',
        width: 135,
        forceSelection: true,

        listeners: {
            'beforeselect': function (combo, record) {
                if (combo.getValue() !== record.data['value']) {
                    reloadFAQ(record.data['value']);
                }
            }
        }
    });

    $('body').on('click', '.faq-section-link', function () {
        var category = $(this).closest('.faq-section-block').find('.faq-section-content:first');
        var categoryManagement = $(this).closest('.faq-section-block').find('.faq-section-block-content:first');
        var section_id = $(this).attr('id').replace('faq-section-id-', '');
        var subCategories = $('.faq-section-parent-block-' + section_id);

        $(this).closest('.faq-section-name').toggleClass('expanded', category.is(":hidden"));

        categoryManagement.toggle();
        category.slideToggle(300, function () {
            subCategories.slideToggle(300, function () {
                updateSuperadminIFrameHeight('.admin-tab-content');
            });

            updateSuperadminIFrameHeight('.admin-tab-content');
        });

        return false;
    });
});