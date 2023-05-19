function deleteProspects(grid, s) {
    if (!s || s.length === 0) {
        Ext.simpleConfirmation.msg('Info', 'Please select prospect(s) to delete');
        return false;
    }

    Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete Prospect' + (s.length > 1 ? 's' : '') + '?', function(btn) {
        if (btn == 'yes') {
            //get id's
            var ids = [];
            for (var i = 0; i < s.length; i++) {
                ids.push(s[i].data.prospect_id);
            }

            //delete
            Ext.getBody().mask('Deleting...');
            Ext.Ajax.request({
                url: baseUrl + "/manage-prospects/delete",
                params: {
                    prospects: Ext.encode(ids)
                },
                success: function(f) {

                    var result = Ext.decode(f.responseText);

                    if (!result.success) {
                        Ext.getBody().unmask();
                        Ext.Msg.alert('Status', 'Can\'t delete selected prospect(s)');
                    } else {
                        grid.store.reload();

                        Ext.getBody().mask('Done');
                        setTimeout(function() {
                            Ext.getBody().unmask();
                        }, 750);
                    }
                },
                failure: function() {
                    Ext.Msg.alert('Status', 'An error occurred when saving selected prospect(s)');
                    Ext.getBody().unmask();
                }
            });
        }
    });

    return true;
}

function editProspects(grid, booAddAction, s)
{
    var data = false;
    if(!booAddAction && s) {
        data = s.data;
    }
    
    var countries = grid.getStore().reader.jsonData['countries'];
    var packages = grid.getStore().reader.jsonData['packages_list'];
    
    var salutation = new Ext.form.ComboBox({
        fieldLabel: 'Salutation',
        store: new Ext.data.SimpleStore({
            fields: ['sal_name'],
            data: [['Mr.'], ['Miss'], ['Ms.'], ['Mrs.'], ['Dr.']]
        }),
        mode: 'local',
        valueField: 'sal_name',
        displayField: 'sal_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select salutation...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.salutation
    });
    
    var name = new Ext.form.TextField({
        allowBlank: false,
        fieldLabel: 'Name',
        width: 220,
        value: data.name
    });
    
    var last_name = new Ext.form.TextField({
        fieldLabel: 'Last Name',
        width: 220,
        value: data.last_name
    });
    
    var company = new Ext.form.TextField({
        fieldLabel: 'Company',
        width: 220,
        value: data.company
    });
    
    var email = new Ext.form.TextField({
        fieldLabel: 'Email',
        width: 220,
        value: data.email
    });
    
    var phone_w = new Ext.form.TextField({
        fieldLabel: 'Phone (W)',
        width: 220,
        value: data.phone_w
    });
    
    var phone_m = new Ext.form.TextField({
        fieldLabel: 'Phone (M)',
        width: 220,
        value: data.phone_m
    });
    
    var source = new Ext.form.ComboBox({
        fieldLabel: 'Source',
        store: new Ext.data.SimpleStore({
            fields: ['source_name'],
            data: [['CSIC'], ['Sign-up Page'], ['Special Offer Page'], ['Demo Request'], ['Past Clients']]
        }),
        mode: 'local',
        valueField: 'source_name',
        displayField: 'source_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select Source...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.source
    });
    
    var key = new Ext.form.TextField({
        fieldLabel: 'Registration Key',
        width: 220,
        value: data.key
    });
    
    var key_status = new Ext.form.ComboBox({
        fieldLabel: 'Reg. Key Status',
        store: new Ext.data.SimpleStore({
            fields: ['key_status'],
            data: [['Active'], ['Used once'], ['Disable']]
        }),
        mode: 'local',
        valueField: 'key_status',
        displayField: 'key_status',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select Reg. Key Status...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.key_status
    });
    
    var address = new Ext.form.TextArea({
        fieldLabel: 'Address',
        width: 220,
        value: data.address
    });
    
    var city = new Ext.form.TextField({
        fieldLabel: 'City',
        width: 220,
        value: data.city
    });
    
    var state = new Ext.form.TextField({
        fieldLabel: 'Province/State',
        width: 220,
        value: data.state
    });
    
    var country = new Ext.form.ComboBox({
        fieldLabel: 'Country',
        store: new Ext.data.Store({
            data: countries,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'countries_iso_code_3'}, {name: 'countries_name'}]))
        }),
        mode: 'local',
        valueField: 'countries_iso_code_3',
        displayField: 'countries_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select Country...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.country
    });
    
    var zip = new Ext.form.TextField({
        fieldLabel: 'Postal Code/Zip',
        width: 220,
        value: data.zip
    });
    
    var package_type = new Ext.form.ComboBox({
        fieldLabel: 'Package',
        store: new Ext.data.Store({
            data: packages,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'subscription_id'}, {name: 'subscription_name'}]))
        }),
        mode: 'local',
        valueField: 'subscription_id',
        displayField: 'subscription_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select Package...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.package_type
    });
    
    var support = new Ext.form.ComboBox({
        fieldLabel: 'Training & Support',
        store: new Ext.data.SimpleStore({
            fields: ['support_id', 'support_name'],
            data: [['Y', 'Yes'], ['N', 'No']]
        }),
        mode: 'local',
        valueField: 'support_id',
        displayField: 'support_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Do you need Training?...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.support
    });
    
    var payment_term = new Ext.form.ComboBox({
        fieldLabel: 'Payment Term',
        store: new Ext.data.SimpleStore({
            fields: ['pt_id', 'pt_name'],
            data: [[1, 'Monthly'], [2, 'Annually'], [3, 'Bi-annually']]
        }),
        mode: 'local',
        valueField: 'pt_id',
        displayField: 'pt_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select the payment term...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.payment_term
    });
    
    var paymentech_profile_id = new Ext.form.TextField({
        fieldLabel: 'Paymentech Profile ID',
        width: 220,
        value: data.paymentech_profile_id
    });
    
    var status = new Ext.form.ComboBox({
        fieldLabel: 'Status',
        store: new Ext.data.SimpleStore({
            fields: ['status'],
            data: [['Active'], ['Closed']]
        }),
        mode: 'local',
        valueField: 'status',
        displayField: 'status',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Select Status...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 220,
        value: data.status
    });
    
    var notes = new Ext.form.TextArea({
        fieldLabel: 'Notes',
        width: 220,
        height: 92,
        value: data.notes
    });
    
    var pan1 = new Ext.FormPanel({
        labelWidth: 110,
        items: [salutation,
                name,
                last_name,
                company,
                email,
                phone_w,
                phone_m,
                source,
                key,
                key_status,
                address]
    });
    
    var pan2 = new Ext.FormPanel({
        labelWidth: 145,
        bodyStyle: 'padding-left: 30px;',
        cellCls: 'td-align-top',
        items: [city,
                state,
                country,
                zip,
                package_type,
                support,
                payment_term,
                paymentech_profile_id,
                status,
                notes]
    });
    
    var pan = new Ext.Panel({
        layout: 'table',
        bodyStyle: 'padding:5px',
        viewConfig: {
            columns: 2
        },
        items: [pan1, pan2]
    });
      
    var saveBtn = new Ext.Button({
        cls:  'orange-btn',
        text: '<i class="las la-save"></i>' + _('Save'),
        handler: function() {
            if(!pan1.getForm().isValid() || !pan2.getForm().isValid()) {
                return false;
            }

            win.getEl().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + "/manage-prospects/save",
                params: {
                    act: booAddAction ? 'add' : 'edit',
                    prospect_id: data.prospect_id,
                    salutation: Ext.encode(salutation.getValue()),
                    name: Ext.encode(name.getValue()),
                    last_name: Ext.encode(last_name.getValue()),
                    company: Ext.encode(company.getValue()),
                    email: Ext.encode(email.getValue()),
                    phone_w: Ext.encode(phone_w.getValue()),
                    phone_m: Ext.encode(phone_m.getValue()),
                    source: Ext.encode(source.getValue()),
                    key: Ext.encode(key.getValue()),
                    key_status: Ext.encode(key_status.getValue()),
                    address: Ext.encode(address.getValue()),
                    city: Ext.encode(city.getValue()),
                    state: Ext.encode(state.getValue()),
                    country: Ext.encode(country.getValue()),
                    zip: Ext.encode(zip.getValue()),
                    package_type: Ext.encode(package_type.getValue()),
                    support: Ext.encode(support.getValue()),
                    payment_term: Ext.encode(payment_term.getValue()),
                    paymentech_profile_id: paymentech_profile_id.getValue(),
                    status: Ext.encode(status.getValue()),
                    notes: Ext.encode(notes.getValue())
                },
                success: function(f) {
                    
                    var result = Ext.decode(f.responseText);
                    
                    if(!result.success) {
                        win.getEl().unmask();
                        Ext.Msg.alert('Status', 'Can\'t save prospect');
                    } else {
                        grid.store.reload();
                        win.getEl().mask('Saved');
                        setTimeout(function(){
                            win.getEl().unmask();
                            win.close();
                          }, 750);                
                    }
                },
                failure: function() {
                    Ext.Msg.alert('Status', 'An error occurred when saving prospect');
                    win.getEl().unmask();
                }
            });
        }
    });

    var cancelBtn = new Ext.Button({
        text: 'Cancel',
        handler: function() {
            win.close();
        }
    });
    
    var win = new Ext.Window({
        title: booAddAction ? '<i class="las la-plus"></i>' + _('Add Prospect') : '<i class="las la-edit"></i>' + _('Edit Prospect'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        items: pan,
        resizable: false,
        buttons: [cancelBtn, saveBtn]
    });

    win.show();
    win.center();
}

function showProspects()
{
    var store = new Ext.data.Store({
        url: baseUrl + '/manage-prospects/list',
        autoLoad: true,
        remoteSort: true,
        reader: new Ext.data.JsonReader({
                id: 'client_form_id',
                root: 'rows',
                totalProperty: 'totalCount'
            }, Ext.data.Record.create(
                [
                   {name: 'prospect_id', type: 'int'},
                   {name: 'salutation'},
                   {name: 'name'},
                   {name: 'last_name'},
                   {name: 'company'},
                   {name: 'email'},
                   {name: 'phone_w'},
                   {name: 'phone_m'},
                   {name: 'source'},
                   {name: 'key'},
                   {name: 'key_status'},
                   {name: 'address'},
                   {name: 'city'},
                   {name: 'state'},
                   {name: 'country'},
                   {name: 'country_display'},
                   {name: 'zip'},
                   {name: 'package_type'},
                   {name: 'packages_list'},
                   {name: 'package_display'},
                   {name: 'support'},
                   {name: 'payment_term'},
                   {name: 'payment_term_display'},
                   {name: 'paymentech_profile_id'},
                   {name: 'status'},
                   {name: 'notes'},
                   {name: 'invoices'}
                ]
            )
        ),
        listeners: {
            load: function () {
                var iframe = parent.document.getElementById('admin_section_frame');
                if (!empty(iframe)) {
                    var iframeBodyHeight = iframe.contentWindow.document.body.offsetHeight;
                    iframe.style.height = iframeBodyHeight + 'px';
                }
            }
        }
    });

    var pagingBar = new Ext.PagingToolbar({
        pageSize:    intShowProspectsPerPage,
        store:       store,
        displayInfo: true,
        displayMsg:  'Displaying prospects {0} - {1} of {2}',
        emptyMsg:    'No prospects to display'
    });
    
    var grid = new Ext.grid.GridPanel({
        renderTo: 'prospects-grid',
        autoHeight: true,
        loadMask: true,
        store: store,
        stripeRows: true,
        cls: 'extjs-grid',    
        sm: new Ext.grid.CheckboxSelectionModel(),
        autoExpandColumn: 'col-name',
        autoExpandMin: 100,
        cm: new Ext.grid.ColumnModel({
            columns: [
                new Ext.grid.CheckboxSelectionModel(),
                {header: "Name", dataIndex: 'name', id: 'col-name'},
                {header: "Last Name", dataIndex: 'last_name'},
                {header: "Company", hidden: true, dataIndex: 'company'},
                {header: "Email", hidden: true, dataIndex: 'email'},
                {header: "Phone (W)", hidden: true, dataIndex: 'phone_w'},
                {header: "Phone (M)", hidden: true, dataIndex: 'phone_m'},
                {header: "Source", dataIndex: 'source'},
                {header: "Registration Key", dataIndex: 'key'},
                {header: "Reg. Key Status", dataIndex: 'key_status'},
                {header: "Address", hidden: true, dataIndex: 'address'},
                {header: cityLabel, hidden: true, dataIndex: 'city'},
                {header: "Province/State", hidden: true, dataIndex: 'state'},
                {header: "Country", hidden: true, dataIndex: 'country_display'},
                {header: "Postal Code/Zip", hidden: true, dataIndex: 'zip'},
                {header: "Package", hidden: true, dataIndex: 'package_display'},
                {header: "Training & Support", hidden: true, dataIndex: 'support'},
                {header: "Payment term", hidden: true, dataIndex: 'payment_term_display'},
                {header: "Status", hidden: true, dataIndex: 'status'}
            ],
            defaultSortable: true
        }),
        viewConfig: { 
            emptyText: 'No Prospects found',
            forceFit: true
        },
        bbar: pagingBar,
        tbar:[{
                id: 'prospects-add',
                text: '<i class="las la-plus"></i>' + _('Add Prospect'),
                handler: function() {
                    editProspects(grid, true);
                }            
            },
            {
                id: 'prospects-edit',
                text: '<i class="las la-edit"></i>' + _('Edit Prospect'),
                disabled: true,
                handler: function() {
                    editProspects(grid, false, grid.getSelectionModel().getSelected());
                }            
            },
            {
                id: 'prospects-delete',
                text: '<i class="las la-trash"></i>' + _('Delete Prospect'),
                disabled: true,
                handler: function() {
                    deleteProspects(grid, grid.getSelectionModel().getSelections());
                }
            }, 
            {
                id: 'prospects-email',
                text: '<i class="las la-envelope"></i>' + _('Email'),
                hidden: !booHasAccessToMail || !booHasAccessToManageTemplates,
                disabled: true,
                handler: function() {                    
                    // Get selected prospects list
                    var nodes = grid.getSelectionModel().getSelections();
                    var prospects = [];
                    for(var i=0; i<nodes.length; i++) {
                        prospects.push(nodes[i].data.prospect_id);
                    }
                    
                    //show email dialog
                    sendEmailToProspects({
                        prospects: prospects
                    });
                }
        }, 
        {
            id: 'prospects-invoice',
            text: '<i class="las la-search"></i>' + _('Show Invoice'),
            disabled: true,
            handler: function() {                    
                  var nodes = grid.getSelectionModel().getSelected();
                  var invoices = nodes.data.invoices;
                  var selectedItm = null;
                  
                  if(invoices.length > 1) {
                      
                      var comb = new Ext.form.ComboBox({
                          hideLabel: true,
                          store: new Ext.data.Store({
                              data: invoices,
                              reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'company_id'}, {name: 'company_invoice_id'}, {name: 'invoice_name'}]))
                          }),                          
                          mode: 'local',
                          valueField: 'company_invoice_id',
                          displayField: 'invoice_name',
                          triggerAction: 'all',
                          forceSelection: true,
                          emptyText: 'Select Invoice...',
                          readOnly: true,
                          typeAhead: true,
                          selectOnFocus: true,
                          editable: false,
                          width: 220
                      });
                      
                      comb.on('select', function(obj, rec){
                          selectedItm = rec.data;
                          okBtn.enable();
                      });
                      
                      var pan = new Ext.Panel({
                          layout: 'form',
                          bodyStyle: 'padding:5px',
                          items: [comb]
                      });
                        
                      var okBtn = new Ext.Button({
                          text: 'Select',
                          disabled: true,
                          handler: function() {
                              openInvoice(selectedItm);
                          }
                      });

                      var win = new Ext.Window({
                          title: 'Select Invoice to preview',
                          modal: true,
                          initHidden: false,
                          width: 250,
                          autoHeight: true,
                          items: pan,
                          resizable: false,
                          buttons: [okBtn]
                      });
                          
                      win.show();
                      win.center();
                      
                  } else if(invoices.length == 1) {
                      openInvoice(invoices[0]);
                  }
            }
        }]
    });
    
    grid.getSelectionModel().on('selectionchange', function(){
        var sel = grid.getSelectionModel().getSelections();
        var booIsSelectedOneProspect = sel.length == 1;
        var booIsSelectedAtLeastOneProspect = sel.length >= 1;

        var booDisableInvoiceButton = true;
        if (booIsSelectedOneProspect) {
            var node = grid.getSelectionModel().getSelected();
            if(node && node.data.invoices.length > 0) {
                booDisableInvoiceButton = false;
            }
        }

        Ext.getCmp('prospects-invoice').setDisabled(booDisableInvoiceButton);
        Ext.getCmp('prospects-edit').setDisabled(!booIsSelectedOneProspect);
        Ext.getCmp('prospects-delete').setDisabled(!booIsSelectedAtLeastOneProspect);
        Ext.getCmp('prospects-email').setDisabled(!booIsSelectedAtLeastOneProspect);
    });
    
    grid.on('rowdblclick', function(g, row){
        editProspects(grid, false, grid.getStore().getAt(row));
    });
}

function openInvoice(data) {
    if(data) {
        window.open(baseUrl + '/manage-company/show-invoice-pdf?invoiceId=' + data.company_invoice_id + '&companyId=' + data.company_id);
    }
}

function sendEmailToProspects(options)
{
    Ext.QuickTips.init();

    if(empty(options)) {
        options = {};
    }

    //data options
    var showTemplates = empty(options.template_id);

    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + "/manage-templates/get-email-template",
        params: {
            showTemplates: Ext.encode(showTemplates),
            allowedTemplates: Ext.encode(options.allowedTemplates)
        },
        success: function(f, o) {

            var result = Ext.decode(f.responseText);

            var fillFields = function(template_id)
            {
                win.getEl().mask('Loading...');
                Ext.Ajax.request({
                    url: baseUrl + "/manage-templates/get-message",
                    params: {
                        template_id: template_id,
                        company_id: options.company_id,
                        prospects: Ext.encode(options.prospects)
                    },
                    success: function(f, o) {

                        var data = Ext.decode(f.responseText);
                        if(data) {

                            var set_value = function(obj, value) {
                                if(!empty(value)) {
                                    obj.setValue(value);
                                }
                            };

                            if(empty(options.email)) {
                                set_value(email, data.email);
                            }

                            //if template name is empty (e.g. default template)
                            if(templates) {
                                templates.setValue(template_id);
                            }

                            if(data.message) {
                                set_value(editor, data.message.replace(/\r\n/g, '<br />'));
                            }

                            set_value(subject, data.subject);
                            set_value(from, data.from);
                            set_value(cc, data.cc);
                            set_value(bcc, data.bcc);
                        }

                        win.getEl().unmask();
                    },
                    failure: function() {
                        Ext.Msg.alert('Status', 'Can\'t get template');
                        win.getEl().unmask();
                    }
                });
            };

            //we have template data
            if(showTemplates) {
                options.data = {};

                var templates = new Ext.form.ComboBox({
                    fieldLabel: 'Template',
                    store: new Ext.data.Store({
                        data: result.templates,
                        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'templateId'}, {name: 'templateName'}]))
                    }),
                    mode: 'local',
                    valueField: 'templateId',
                    displayField: 'templateName',
                    triggerAction: 'all',
                    lazyRender: true,
                    forceSelection: true,
                    emptyText: 'Select a template...',
                    readOnly: true,
                    typeAhead: true,
                    selectOnFocus: true,
                    editable: false,
                    grow: true,
                    width: 685
                });

                templates.on('select', function(combo, record, index){
                    fillFields(record.data.templateId);
                });
            }

            var from = new Ext.form.TextField({
                id: 'st-from',
                name: 'from',
                fieldLabel: 'From',
                value: options.data.from,
                vtype: 'email',
                width: 257,
                anchor: '95%'
            });

            var email = new Ext.form.TextField({
                id: 'st-email',
                name: 'email',
                fieldLabel: 'To',
                value: (empty(options.email) ? options.data.email : options.email),
                width: 257,
                allowBlank: false,
                anchor: '100%'
            });

            var cc = new Ext.form.TextField({
                id: 'st-cc',
                name: 'cc',
                fieldLabel: 'CC',
                value: options.data.cc,
                vtype: 'multiemail',
                anchor: '95%'
            });

            var bcc = new Ext.form.TextField({
                id: 'st-bcc',
                name: 'bcc',
                fieldLabel: 'BCC',
                value: options.data.bcc,
                vtype: 'multiemail',
                anchor: '100%'
            });

            var subject = new Ext.form.TextField({
                id: 'st-subject',
                name: 'subject',
                fieldLabel: 'Subject',
                value: options.data.subject,
                width: 685,
                allowBlank: false
            });

            var editor = new Ext.ux.form.FroalaEditor({
                name: 'message',
                hideLabel: true,
                region: 'center',
                height: 300,
                width: 780,
                booAllowImagesUploading: true,
                initialWidthDifference: 40,
                value: options.data.message
            });

            var pan = new Ext.FormPanel({
                cls: 'st-panel',
                fileUpload: true,
                autoHeight: true,
                labelWidth: 60,
                bodyStyle: 'padding:5px',
                items:
                [{
                    layout: 'form',
                    items: (showTemplates ? templates : {})
                },
                {
                    layout: 'column',
                    items:
                    [{
                        columnWidth: 0.5,
                        layout: 'form',
                        items: [from, cc]
                    },
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: [email, bcc]
                    }]
                },
                {
                    layout: 'form',
                    items: subject
                },
                {
                    layout: 'form',
                    items: editor
                }]
            });

            var saveBtn = new Ext.Button({
                text: 'Send',
                cls: 'orange-btn',
                handler: function()
                {
                    //email validation
                    if(empty(email.getValue())){
                        Ext.simpleConfirmation.msg('Info', 'Please enter the recipient\'s email');
                    }

                    //validate form and send
                    var form = pan.getForm();
                    if(form.isValid()) {
                        form.submit({
                            url: baseUrl + '/manage-templates/send',
                            waitMsg: 'Sending...',
                            params: {
                                parseProspects: Ext.encode(options.prospects && options.prospects.length > 1),
                                prospects: Ext.encode(options.prospects)
                            },
                            success: function(f, o) {
                                Ext.simpleConfirmation.msg('Info', 'Message was successfully sent');
                                win.close();
                            },
                            failure: function()
                            {
                                Ext.simpleConfirmation.error('An error occurred when sending mail');
                            }
                        });
                    }
                }
            });

            var closeBtn = new Ext.Button({
                text: 'Cancel',
                handler: function(){
                    win.close();
                }
            });

            var win = new Ext.Window({
                id: 'email-templates-win',
                title: 'Send Email',
                modal: true,
                autoHeight: true,
                resizable: false,
                width: 780,
                layout: 'form',
                items: pan,
                buttons: [closeBtn, saveBtn]
            });

            //show dialog
            win.show();
            win.center();

            //we have template_id
            if(options.template_id) {
                fillFields(options.template_id);
            }

            Ext.getBody().unmask();
        },
        failure: function()
        {
            Ext.Msg.alert('Status', 'Can\'t open send templates dialog');
            Ext.getBody().unmask();
        }
    });
}

Ext.onReady(function(){
    Ext.QuickTips.init();
    $('#prospects-grid').css('min-height', getSuperadminPanelHeight() + 'px');

    showProspects();
});