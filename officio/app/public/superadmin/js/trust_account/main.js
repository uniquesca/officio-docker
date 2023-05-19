Ext.onReady(function(){
    Ext.QuickTips.init();

    // shorthand aliases
    var xg = Ext.grid;

    function submitChanges( data, action ) {
        var maskText = '';
        var submitUrl = baseUrl + "/trust-account-settings";

        switch (action) {
            case 'delete':
                maskText = 'Deleting...';
                submitUrl += "/delete";
                break;

            default:
                maskText = 'Saving...';
                submitUrl += "/manage";
                break;
        }


        var body = action != 'delete' ? Ext.getCmp('edit-ta-window').getEl() : Ext.getBody();
        body.mask(maskText);
        
        Ext.Ajax.request({
            url: submitUrl,
            params: { changes: Ext.encode( data ) },

            success: function(result){
                var resultData = Ext.decode(result.responseText);
                if (!resultData.error) {
                    // Show confirmation message
                    body.mask('Done.');

                    // Apply local changes
                    TAStore.loadData(resultData.arrTA, false);

                    updateTATabs(resultData.arrTA);

                    setTimeout(function(){
                        if(action != 'delete') {
                            Ext.getCmp('edit-ta-window').close();
                        } else {
                            body.unmask();
                        }
                    }, 1000);
                } else {
                    Ext.simpleConfirmation.error('<span style="color: red;">' +resultData.error_message + '</span>');
                    body.unmask();
                }
            },

            failure: function(){
                Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                body.unmask();
            }
        });
    }


    var showEditTAWindow = function(ta_rec) {
        var booNewTA = empty(ta_rec.ta_id);

        var i, date_obj;
        var possible_dates=[['', _('All reconciliation reports')]];
        for (i=0; i<ta_rec.ta_recon_dates.length; i++)
        {
            date_obj = new Date(ta_rec.ta_recon_dates[i].substr(0, 4), parseInt(ta_rec.ta_recon_dates[i].substr(5, 2))-1);

            possible_dates.push([ta_rec.ta_recon_dates[i], Ext.util.Format.date(date_obj, 'M Y')]);
        }

        var possible_dates_iccrc=[['', _('All reconciliation reports')]];
        for (i=0; i<ta_rec.ta_recon_dates_iccrc.length; i++)
        {
            date_obj = new Date(ta_rec.ta_recon_dates_iccrc[i].substr(0, 4), parseInt(ta_rec.ta_recon_dates_iccrc[i].substr(5, 2))-1);

            possible_dates_iccrc.push([ta_rec.ta_recon_dates_iccrc[i], Ext.util.Format.date(date_obj, 'M Y')]);
        }

        var arrOffices = [],
            booChecked = false;
        for (var j = 0; j < arrCompanyOffices.length; j++) {
            if (!empty(ta_rec.ta_divisions)) {
                booChecked = ta_rec.ta_divisions.has(arrCompanyOffices[j]['division_id']);
            }

            arrOffices.push({
                boxLabel: arrCompanyOffices[j]['name'],
                name: 'ta-office-' + arrCompanyOffices[j]['division_id'],
                itemCls: 'no-margin-bottom',
                checked: booChecked,
                inputValue: arrCompanyOffices[j]['division_id']
            });
        }

        if (!arrOffices.length) {
            arrOffices.push({
                xtype: 'label',
                style: 'color: #666666',
                html: 'There are no offices in the company.'
            });
        }

        var officesForm = new Ext.FormPanel({
            frame: false,
            bodyStyle: 'padding: 0 10px',
            labelWidth: 170,
            labelAlign: 'top',
            defaults: {
                msgTarget: 'side'
            },

            items: [
                {
                    id: 'edit-ta-office',
                    xtype: 'checkboxgroup',
                    fieldLabel: officeLabel + _('s with Access to this ') + ta_label,
                    columns: 4,
                    items: arrOffices
                }
            ]
        });

        var booDisableOpeningBalance = true;
        if ((ta_rec.ta_last_reconcile === null || ta_rec.ta_last_reconcile == '0000-00-00') && (ta_rec.ta_last_reconcile_iccrc === null || ta_rec.ta_last_reconcile_iccrc == '0000-00-00')) {
            booDisableOpeningBalance = false;
        }

        var defaultGeneralReconDate = ta_rec.ta_last_reconcile && ta_rec.ta_last_reconcile != '0000-00-00' ? ta_rec.ta_last_reconcile.substr(0, 7) : '';
        var defaultIccReconDate = ta_rec.ta_last_reconcile_iccrc && ta_rec.ta_last_reconcile_iccrc != '0000-00-00' ? ta_rec.ta_last_reconcile_iccrc.substr(0, 7) : ''
        var question = _('WARNING: You have opted to delete the reconciliation reports based on the selected option. This will delete all reconciliation reports generated after the selected option.<br><br>Do you want to continue?');

        var pan = new Ext.FormPanel({
            id: 'edit-ta-form',
            frame: false,
            labelAlign: 'top',
            bodyStyle: 'padding: 16px 10px 0 10px',
            labelWidth: 160,
            defaultType: 'textfield',

            items: [
                {
                    id: 'edit-ta-name',
                    fieldLabel: ta_label + _(' Name'),
                    regex: /^([a-zA-Z0-9\s.\-()!?,:$]*)$/i,
                    regexText: _('Only such symbols are allowed: alphabetical symbols .,:-()?!$ and space'),
                    width: 600,
                    value: ta_rec.ta_name,
                    allowBlank: false
                }, {
                    id: 'edit-ta-detailed-description',
                    fieldLabel: _('Detailed Account Description'),
                    xtype: 'textarea',
                    value: ta_rec.ta_detailed_description,
                    width: 605,
                    allowBlank: true
                }, {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        {
                            columnWidth: 0.5,
                            layout: 'form',
                            items: {
                                id: 'edit-ta-balance',
                                fieldLabel: _('Opening Balance'),
                                xtype: 'numberfield',
                                value: ta_rec.ta_balance,
                                width: 290,
                                allowBlank: false,
                                allowDecimals: true,
                                allowNegative: true,
                                disabled: booDisableOpeningBalance
                            }
                        }, {
                            columnWidth: 0.5,
                            layout: 'form',
                            items: {
                                id: 'edit-ta-currency',
                                fieldLabel: _('Currency'),
                                xtype: 'combo',
                                width: 300,
                                value: ta_rec.ta_currency,
                                store: new Ext.data.SimpleStore({
                                    fields: ['currency_id', 'currency_name'],
                                    data : arrSupportedCurrencies
                                }),
                                mode: 'local',
                                valueField: 'currency_id',
                                displayField: 'currency_name',
                                typeAhead: true,
                                triggerAction: 'all',
                                disabled: !ta_rec.ta_can_change_currency,
                                lazyRender:true
                            }
                        }
                    ]
                }, {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        {
                            columnWidth: 0.5,
                            layout: 'form',
                            style: 'padding-top: 17px',
                            items: {
                                xtype: 'label',
                                forId: 'edit-ta-view',
                                hideLabel: true,
                                style: 'font-size: 14px;',
                                text: _('Show Transactions of Past (months):')
                            }
                        }, {
                            columnWidth: 0.5,
                            layout: 'form',
                            items: {
                                id: 'edit-ta-view',
                                hideLabel: true,
                                xtype: 'numberfield',
                                value: ta_rec.ta_view_month,
                                width: 65,
                                allowBlank: false,
                                allowDecimals: false,
                                allowNegative: false,
                                maxValue: 1000
                            }
                        }
                    ]
                }, {
                    id: 'edit-ta-allow',
                    boxLabel: _('New Bank Account') + String.format(
                        _("<i class='las la-question-circle help-icon' ext:qtip='{0}' ext:qwidth='450' style='cursor: help; margin-left: 10px; vertical-align: bottom'></i>"),
                        _('Only check this box if you are transitioning to a new account, yet retaining the transactions from the old account.<br><i>NOTE: The current data will <u>not</u> be erased.</i>')
                    ),
                    hideLabel: true,
                    xtype: 'checkbox',
                    checked: ta_rec.allow_new_bank_id==1
                }, {
                    xtype: 'container',
                    layout: 'column',
                    items: [{
                        columnWidth: 0.5,
                        layout: 'form',
                        items: {
                            id: 'edit-ta-last-reconcile',
                            fieldLabel: _('Delete general reconciliation reports'),
                            hidden: site_version == 'australia',
                            xtype: 'combo',
                            editable: false,
                            width: 290,
                            value: defaultGeneralReconDate,
                            store: new Ext.data.SimpleStore({
                                fields: ['date', 'date_name'],
                                data : possible_dates
                            }),
                            mode: 'local',
                            valueField: 'date',
                            displayField: 'date_name',
                            typeAhead: true,
                            triggerAction: 'all',
                            disabled: ta_rec.ta_last_reconcile===null || ta_rec.ta_recon_dates.length===0,
                            lazyRender: true,

                            listeners: {
                                'beforeselect': function (combo, record) {
                                    if (record.data[combo.valueField] != defaultGeneralReconDate) {
                                        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                            if (btn !== 'yes') {
                                                combo.setValue(defaultGeneralReconDate);
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    }, {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: {
                            id: 'edit-ta-last-reconcile-iccrc',
                            fieldLabel: site_version == 'australia' ? _('Delete reconciliation reports') : _('Delete CICC reconciliation reports'),
                            xtype: 'combo',
                            editable: false,
                            width: 300,
                            value: defaultIccReconDate,
                            store: new Ext.data.SimpleStore({
                                fields: ['date', 'date_name'],
                                data : possible_dates_iccrc
                            }),
                            mode: 'local',
                            valueField: 'date',
                            displayField: 'date_name',
                            typeAhead: true,
                            triggerAction: 'all',
                            disabled: ta_rec.ta_last_reconcile_iccrc===null || ta_rec.ta_recon_dates_iccrc.length===0,
                            lazyRender: true,

                            listeners: {
                                'beforeselect': function (combo, record) {
                                    if (record.data[combo.valueField] != defaultIccReconDate) {
                                        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                            if (btn !== 'yes') {
                                                combo.setValue(defaultIccReconDate);
                                            }
                                        });
                                    }
                                }
                            }
                        }
                    }]
                }
            ]
        });
        
        var win = new Ext.Window({
            id: 'edit-ta-window',
            title: booNewTA ? '<i class="las la-plus"></i>' + _('New ') + ta_label : '<i class="las la-edit"></i>' + _('Edit ') + ta_label,
            modal: true,
            y: 10,
            autoWidth: true,
            autoHeight: true,
            layout: 'form',
            items: [pan, officesForm],
            buttons: [
                {
                    text: _('Cancel'),
                    handler : function() {
                        win.close();
                    }
                },
                {
                    text: booNewTA ? _('New ') + ta_label : _('Update ') + ta_label,
                    width: 150,
                    cls:  'orange-btn',
                    handler: function() {
                        var fp = Ext.getCmp('edit-ta-form').getForm();

                        if (fp.isValid()) {
                            var oStatus = Ext.getCmp('edit-ta-status');
                            var status = (oStatus) ? oStatus.getValue() : true;

                            var arrTaDivisions = [];
                            var oGroup = Ext.getCmp('edit-ta-office');
                            if (oGroup.items.getCount()) {
                                for (var j = 0; j < oGroup.items.getCount(); j++) {
                                    var checkbox = oGroup.items.item(j);
                                    if (checkbox.checked) {
                                        arrTaDivisions.push(checkbox.inputValue);
                                    }
                                }
                            }

                            var r = new TARecord({
                                ta_id:                   ta_rec.ta_id,
                                ta_name:                 Ext.getCmp('edit-ta-name').getValue(),
                                ta_last_reconcile:       Ext.getCmp('edit-ta-last-reconcile').getValue(),
                                ta_last_reconcile_iccrc: Ext.getCmp('edit-ta-last-reconcile-iccrc').getValue(),
                                ta_currency:             Ext.getCmp('edit-ta-currency').getValue(),
                                ta_balance:              Ext.getCmp('edit-ta-balance').getValue(),
                                ta_view_month:           Ext.getCmp('edit-ta-view').getValue(),
                                ta_allow:                Ext.getCmp('edit-ta-allow').checked,
                                ta_divisions:            arrTaDivisions,
                                ta_status:               status,
                                ta_detailed_description: Ext.getCmp('edit-ta-detailed-description').getValue()
                            });

                            submitChanges(r.data, 'manage');
                        }
                    }
                }
            ]
        });

        win.show();
        win.syncShadow();
    };

    // Custom T/A status column
    var customStatus = function(status) {
        var src = imagesUrl + '/icons/';
        src += (status) ? 'tick.png' : 'cross.png';
        var alt = (status) ? 'Enabled' : 'Disabled';
        return '<img src="'+src +'" alt="'+alt +'" title="'+alt +'" />';
    };


    var TARecord = Ext.data.Record.create([
        {name: 'ta_id'},
        {name: 'ta_name'},
        {name: 'ta_detailed_description'},
        {name: 'ta_currency'},
        {name: 'ta_currency_label'},
        {name: 'ta_view_month'},
        {name: 'ta_balance', type: 'float'},
        {name: 'ta_status'},
        {name: 'allow_new_bank_id'},
        {name: 'ta_can_change_currency', type: 'boolean'},
        {name: 'ta_last_reconcile'},
        {name: 'ta_last_reconcile_iccrc'},
        {name: 'ta_recon_dates'},
        {name: 'ta_recon_dates_iccrc'},
        {name: 'ta_divisions'}
    ]);


    var TAStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader(
            {id: 0},
            TARecord
        ),
        data: xg.taSettings
    });

    var selections = new xg.CheckboxSelectionModel();
    var cm = new xg.ColumnModel({
        columns: [
            selections,
            {
                id: 'ta_id',
                header: "Name",
                dataIndex: 'ta_name'
            },
            {
                header: "Detailed Account Description",
                width: 100,
                dataIndex: 'ta_detailed_description',
                renderer: function(value){
                    return Ext.util.Format.nl2br(value);
                }
            },
            {
                header: "Currency",
                width: 20,
                dataIndex: 'ta_currency_label',
                align: 'center'
            },
            {
                header: "View Transactions (months)",
                width: 30,
                dataIndex: 'ta_view_month',
                align: 'center'
            },
            {
                header: "Enabled?",
                width: 20,
                dataIndex: 'ta_status',
                renderer: customStatus,
                hidden: true,
                align: 'center'
            }
        ],
        defaultSortable: true
    });

    var TAGrid = new xg.GridPanel({
        store: TAStore,
        cm: cm,
        sm: selections,
        autoExpandColumn: 'ta_id',
        stripeRows: true,
        cls: 'extjs-grid',

        viewConfig: {
            deferEmptyText: _('No entries found.'),
            emptyText: _('No entries found.'),
            forceFit: true
        },

        // inline toolbars
        tbar:[{
            text: '<i class="las la-plus"></i>' + _('New ') + ta_label,
            tooltip: _('Add a new ') + ta_label,
            cls: 'main-btn',
            handler: function () {
                var r = new TARecord({
                    ta_id: 0,
                    ta_name: _('New ') + ta_label.replace(/\//, ' '),
                    ta_currency: site_version == 'australia' ? 'usd' : 'cad',
                    ta_view_month: site_version == 'australia' ? 2 : 0,
                    ta_balance: 0,
                    ta_status: 1,
                    ta_can_change_currency: true,
                    ta_last_reconcile: null,
                    ta_last_reconcile_iccrc: null,
                    ta_recon_dates: [],
                    ta_recon_dates_iccrc: [],
                    dirty: true,
                    ta_detailed_description: null
                });
                showEditTAWindow(r.data);
            }
        }, '-', {
            text: '<i class="las la-edit"></i>' + _('Edit ' + ta_label),
            tooltip:'Edit selected ' + ta_label,
            handler: function() {
                loadTADetails();
            }
        }, '-', {
            text: '<i class="las la-trash"></i>' + _('Delete ') + ta_label,
            tooltip: _('Delete the selected ') + ta_label,
            handler: function () {
                var arrSelected = getSelectedTAIds();
                if (arrSelected.length > 0) {
                    var question;
                    if (arrSelected.length == 1)
                        question = 'Are you sure you want to delete <i>' + arrSelected[0].ta_name + '</i>?';
                    else
                        question = 'Are you sure you want to delete selected ' + arrSelected.length + ta_label + ' ?';

                    Ext.MessageBox.buttonText.yes = "Delete";
                    Ext.Msg.confirm('Please confirm', question,
                        function(btn){
                            if(btn == 'yes'){
                                // Send request to delete the record
                                submitChanges( arrSelected, 'delete' );
                            }
                        }
                    );
                } else {
                    Ext.simpleConfirmation.msg('Info', 'Please select at least one ' + ta_label + ' to delete');
                }
            }
        }],

        autoWidth: true,
        autoHeight: true,
        iconCls: 'icon-grid'
    });

    TAGrid.on('rowdblclick', function () {
        loadTADetails();
    }, this);

    var loadTADetails = function () {
        var arrSelected = getSelectedTAIds();
        if (arrSelected.length == 1) {
            var body = Ext.getBody();
            body.mask('Loading...');

            Ext.Ajax.request({
                url:    baseUrl + '/trust-account-settings/get-settings',
                params: {
                    company_ta_id: Ext.encode(arrSelected[0]['ta_id'])
                },

                success: function (result) {
                    body.unmask();

                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        showEditTAWindow(resultData.arrInfo);
                    } else {
                        Ext.simpleConfirmation.error('<span style="color: red;">' + resultData.message + '</span>');
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                    body.unmask();
                }
            });
        } else {
            var infoText = (arrSelected.length === 0) ? 'Please select one ' + ta_label + '' : 'Please select only one ' + ta_label;
            Ext.simpleConfirmation.msg('Info', infoText);
        }
    };

    var getSelectedTAIds = function() {
        var arrSelectedIds = [];

        if (selections) {
            var s = selections.getSelections();
            if(s.length > 0){
                for(var i=0; i<s.length; i++) {
                    arrSelectedIds[arrSelectedIds.length] = s[i].data;
                }
            }
        }

        return arrSelectedIds;
    };


    // Update opened T/A tabs
    var updateTATabs = function (arrTA) {
        if (window.parent.Ext.getCmp('trustac-tab')) {
            var newArr = [];

            for (var i = 0; i < arrTA.length; i++) {
                var tab = window.parent.Ext.getCmp('ta_tab_' + arrTA[i]['ta_id']);
                // Now we update title only, later maybe we need update other things
                // e.g. currency, view transactions month
                if (tab) {
                    tab.setTitle(arrTA[i][1]);
                }

                newArr.push({
                    company_ta_id: arrTA[i]['ta_id'],
                    tabId: 'ta_tab_' + arrTA[i]['ta_id'],
                    title: arrTA[i]['ta_name'],
                    currency: arrTA[i]['ta_currency'],
                    currency_label: arrTA[i]['ta_currency_label'],
                    view_ta_months: arrTA[i]['ta_view_month'],
                    last_reconcile: arrTA[i]['ta_last_reconcile'],
                    last_reconcile_iccrc: arrTA[i]['ta_last_reconcile_iccrc']
                });
            }

            //update TA tabs array
            window.parent.arrTATabs = newArr;

            //update TA tabpanel
            window.parent.Ext.getCmp('trustac-tab').booRefreshOnActivate = true;
        }
    };


    var arrTATabs = [];

    if (booAdmin) {
        arrTATabs.push({
            id: 'edit_ta_tab',
            xtype: 'panel',
            title: _('Add/Edit ') + ta_label,
            items: [TAGrid]
        });
    }

    arrTATabs.push(new Ext.form.FormPanel({
        id: 'edit_settings_tab',
        title: _('Transaction Settings'),
        labelWidth: 120,

        items: [
            {
                id: 'ta_type',
                xtype: 'combo',
                fieldLabel: 'Option to Edit',
                labelStyle: 'font-size: 16px; width: 120px; padding-top: 12px',
                typeAhead: true,
                triggerAction: 'all',
                width: 250,
                editable: false,
                mode: 'local',
                displayField: 'type_name',
                valueField: 'type_id',
                lazyInit: false,
                emptyText: 'Please Select An Option...',
                value: 'deposit',
                store: new Ext.data.SimpleStore({
                    fields: ['type_id', 'type_name'],
                    data: [
                        ['deposit', 'Special Deposit Types'],
                        ['withdrawal', 'Special Withdrawal Types'],
                        ['destination', 'Destination Account Types']
                    ]
                }),

                listeners: {
                    'beforeselect': function (combo, rec) {
                        applyTypeParams(rec.data.type_id);

                        // Reload the list
                        type_store.reload();
                    }
                }

            },
            ta_type_grid
        ]
    }));


    var settinngsTabs = new Ext.TabPanel({
        renderTo: 'trust_account_settings',
        deferredRender: false,
        frame: false,
        plain: true,
        height: getSuperadminPanelHeight(),
        cls: 'tabs-second-level',

        defaults: {
            autoWidth: true,
            autoScroll: true
        },

        items: arrTATabs,

        listeners: {
            'tabchange': function () {
                var transactionsGrid = Ext.getCmp('deposit-transactions-editor-grid');
                if (transactionsGrid) {
                    transactionsGrid.getView().fitColumns();
                }
                if (TAGrid && booAdmin) {
                    TAGrid.getView().fitColumns();
                }
            },
            afterrender: function () {
                updateSuperadminIFrameHeight('#trust_account_settings');
            }
        }
    });
    
    // Show this tab only for admin
    if (booAdmin) {
        if (activeAdminTab) {
            settinngsTabs.setActiveTab('edit_ta_tab');
        } else {
            settinngsTabs.setActiveTab('edit_settings_tab');
        }

        // Resize columns to fix 'columns width' bug
        if (TAGrid) {
            TAGrid.getView().fitColumns();
        }
    } else {
        settinngsTabs.setActiveTab('edit_settings_tab');
    }
});
