function reloadTicketsForm(company_id) {
    if (Ext.getCmp('tickets-grid-' + company_id)) {
        Ext.getCmp('tickets-grid-' + company_id).store.reload();
    }
}

function ticket(params) {
    if (params.action == 'open') {

        var ticketsTextareaReadonly = new Ext.form.TextArea({
            style: {
                paddingTop: '5px'
            },
            fieldLabel: 'Notes',
            width: 500,
            height: 280,
            readOnly: true
        });

        var pan = new Ext.FormPanel({
            bodyStyle: 'padding:5px',
            items: [
                {
                    layout: 'form',
                    items: [
                        ticketsTextareaReadonly
                    ]
                }
            ]
        });

        var win = new Ext.Window({
            title: 'Ticket',
            layout: 'form',
            modal: true,
            width: 630,
            height: 325,
            resizable: false,
            items: pan
        });

        win.show();

        //save msg
        win.getEl().mask('Loading...');
        //get note detail info
        Ext.Ajax.request({
            url: baseUrl + '/tickets/get-ticket',
            params: {
                ticket_id: params.ticket_id
            },
            success: function (result) {
                win.getEl().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    var ticket = resultDecoded.ticket;
                    ticketsTextareaReadonly.setRawValue(Ext.util.Format.stripTags(ticket.ticket));
                } else {
                    win.close();
                    Ext.simpleConfirmation.error('This note has invalid information and cannot be opened.<br/>Please notify Officio support with the details.');
                }
            },
            failure: function () {
                win1.getEl().unmask();
                Ext.Msg.alert('Status', 'Can\'t load note information');
            }
        });
    } else if (params.action == 'change_status') {
            //save msg
            Ext.getCmp('tickets-grid-' + params.company_id).getEl().mask('Processing...');
            //get note detail info
            Ext.Ajax.request({
                url: baseUrl + '/tickets/change-status',
                params: {
                    ticket_id: params.ticket_id
                },
                success: function (result) {
                    Ext.getCmp('tickets-grid-' + params.company_id).getEl().unmask();
                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        Ext.simpleConfirmation.success('Status was changed.', 'Information');
                        reloadTicketsForm(params.company_id);
                    } else {
                        Ext.simpleConfirmation.error('Status was not changed.');
                    }
                },
                failure: function () {
                    Ext.Msg.alert('Status', 'Can\'t load note information');
                }
            });
    } else if (params.action == 'add') {
        var dateField = new Ext.form.DateField({
            id: 'ticket-create-date',
            width: 150,
            allowBlank: false,
            fieldLabel: 'Date',
            value: new Date(),
            format: dateFormatFull,
            maxLength: 12,
            disabled: true,
            altFormats: dateFormatFull + '|' + dateFormatShort
        });

        var contactedByGroup = new Ext.form.RadioGroup({
            id: 'contactedBy',
            xtype: 'radiogroup',
            fieldLabel: 'Contacted by',
            allowBlank: false,
            width: 380,
            preventMark: false,
            items: [
                {boxLabel: 'Phone',  name: 'contacted_by', inputValue: 'phone', checked: true},
                {boxLabel: 'Email',  name: 'contacted_by', inputValue: 'email'},
                {boxLabel: 'Chat',   name: 'contacted_by', inputValue: 'chat'},
                {boxLabel: 'System', name: 'contacted_by', inputValue: 'system'}
            ]
        });

        var ticketStatus = new Ext.form.RadioGroup({
            id: 'ticketStatus',
            xtype: 'radiogroup',
            fieldLabel: 'Status',
            width: 220,
            allowBlank: false,
            preventMark: false,
            items: [
                {boxLabel: 'Resolved',     name: 'ticket_status', inputValue: 'resolved'},
                {boxLabel: 'Not Resolved', name: 'ticket_status', inputValue: 'not_resolved', checked: true}
            ]
        });

        if (params.company_id != 'all') {
            arrCompanies = [];
        }
        var companiesList = new Ext.form.ComboBox({
            store: new Ext.data.SimpleStore({
                fields: ['company_id', 'companyName'],
                data: empty(arrCompanies) ? [
                    [0, '']
                ] : arrCompanies
            }),
            mode: 'local',
            hidden: params.company_id != 'all',
            displayField: 'companyName',
            valueField: 'company_id',
            typeAhead: false,
            allowBlank: false,
            triggerAction: 'all',
            selectOnFocus: false,
            editable: true,
            grow: true,
            fieldLabel: 'Company',
            width: 280,
            listWidth: 280,
            listeners: {
                select: function () {
                    if (!empty(this.getValue())) {
                        usersList.setDisabled(false);
                        usersList.setValue();
                        var newArrCompanyUsers = [];
                        for (var i=0, j=0; i < arrCompanyUsers.length; i++) {
                            if (arrCompanyUsers[i][2] == this.getValue()) {
                                newArrCompanyUsers[j] = arrCompanyUsers[i];
                                j++;
                            }
                        }

                        var store = new Ext.data.SimpleStore({
                            fields: ['user_id', 'user_full_name'],
                            data: empty(newArrCompanyUsers) ? [
                                [0, '']
                            ] : newArrCompanyUsers
                        });
                        usersList.bindStore(store);
                    }
                },
                change: function () {
                    if (!empty(this.getValue())) {
                        usersList.setDisabled(false);
                    }
                }
            }
        });

        var usersList = new Ext.form.ComboBox({
            store: new Ext.data.SimpleStore({
                fields: ['user_id', 'user_full_name'],
                data: empty(arrCompanyUsers) ? [
                    [0, '']
                ] : arrCompanyUsers
            }),
            mode: 'local',
            displayField: 'user_full_name',
            valueField: 'user_id',
            typeAhead: false,
            triggerAction: 'all',
            selectOnFocus: false,
            editable: true,
            grow: true,
            disabled: params.company_id == 'all',
            fieldLabel: 'User',
            width: 280,
            listWidth: 280
        });

        var ticketsTextarea = new Ext.form.TextArea({
            style: {
                paddingTop: '5px'
            },
            fieldLabel: 'Notes',
            width: 500,
            height: 220
        });

        pan = new Ext.FormPanel({
            bodyStyle: 'padding:5px',
            items: [
                {
                    layout: 'form',
                    items: [
                        dateField,
                        contactedByGroup,
                        ticketStatus,
                        companiesList,
                        usersList,
                        ticketsTextarea
                    ]
                }
            ]
        });

        var addSaveBtn = new Ext.Button({
            text: 'Save',
            cls: 'orange-btn',
            handler: function () {
                var text = ticketsTextarea.getValue();

                var companyId = params.company_id == 'all' ? companiesList.getValue() : params.company_id;
                var companyMemberId = usersList.getValue();
                var contactedBy = contactedByGroup.items.get(0).getGroupValue();
                var status = ticketStatus.items.get(0).getGroupValue();

                if (empty(companyId)) {
                    companiesList.markInvalid();
                    return;
                }

                if (empty(text)) {
                    ticketsTextarea.markInvalid();
                    return;
                }

                if (HtmlTagSearch(text)) {
                    ticketsTextarea.markInvalid('Html tags are not allowed');
                    return;
                }

                //save msg
                win.getEl().mask('Saving...');

                //save
                Ext.Ajax.request({
                    url: baseUrl + '/tickets/' + params.action,
                    params: {
                        ticket_id: params.ticket_id,
                        company_id: companyId,
                        company_member_id: companyMemberId,
                        status: Ext.encode(status),
                        contacted_by: Ext.encode(contactedBy),
                        ticket: Ext.encode(text)
                    },
                    success: function () {
                        {
                            reloadTicketsForm(params.company_id);
                        }

                        win.getEl().mask('Done!');

                        setTimeout(function () {
                            win.getEl().unmask();
                            win.close();
                        }, 750);
                    },
                    failure: function () {
                        Ext.Msg.alert('Status', 'Saving Error');
                        win.getEl().unmask();
                    }
                });
            }
        });

        var closeBtn = new Ext.Button({
            text: 'Cancel',
            handler: function () {
                win.close();
            }
        });

        win = new Ext.Window({
            title: '<i class="las la-folder-plus"></i>' + _('Add Ticket'),
            layout: 'form',
            modal: true,
            width: 630,
            height: params.company_id == 'all'? 600 : 580,
            resizable: false,
            items: pan,
            buttons: [closeBtn, addSaveBtn],
            listeners: {
                beforeshow: function () {
                    ticketsTextarea.focus.defer(100, ticketsTextarea);
                }
            }
        });

        win.show();
        ticketsTextarea.focus.defer(100, ticketsTextarea);
    }

    return false;
}