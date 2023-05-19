var TicketsFilterPanel = function (config) {
    var filterForm = this;
    Ext.apply(this, config);
    TicketsFilterPanel.superclass.constructor.call(this, {
        collapsible: true,
        collapsed: false,
        initialSize: 290,
        width: 290,
        split: true,

        labelAlign: 'top',
        buttonAlign: 'center',
        cls: 'filter-panel',
        style: {
            background: '#ffffff'
        },
        bodyStyle: {
            padding: '7px'
        },

        items: [
            {
                xtype: 'label',
                forId: 'tickets_filter_status',
                text: 'Status:',
                style: 'font-size: 14px;'
            }, {
                layout:'column',
                bodyStyle: {
                    background: '#ffffff'
                },
                items:[
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff',
                            paddingLeft: '0px'
                        },
                        items: [{
                            id: 'tickets_filter_status',
                            xtype: 'radio',
                            hideLabel: true,
                            itemCls: 'no-padding-top no-padding-bottom',
                            boxLabel: 'Resolved',
                            name: 'ticket_status',
                            inputValue: 'resolved'
                        }]
                    }, {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff'
                        },
                        items: [{
                            xtype: 'radio',
                            hideLabel: true,
                            itemCls: 'no-padding-top no-padding-bottom',
                            boxLabel: 'Not Resolved',
                            name: 'ticket_status',
                            inputValue: 'not_resolved'
                        }]
                    }
                ]
            }, {
                xtype: 'label',
                forId: 'tickets_filter_date',
                text: 'Sort by date:',
                style: 'font-size: 14px;'
            }, {
                layout:'column',
                id: 'status_column',
                bodyStyle: {
                    background: '#ffffff'
                },
                items:[
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff',
                            paddingLeft: '0px'
                        },
                        items: [{
                            id: 'tickets_filter_date',
                            xtype: 'radio',
                            hideLabel: true,
                            itemCls: 'no-padding-top no-padding-bottom',
                            boxLabel: 'New tickets first',
                            checked: true,
                            name: 'ticket_date',
                            inputValue: 'DESC'
                        }]
                    }, {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff'
                        },
                        items: [{
                            xtype: 'radio',
                            hideLabel: true,
                            itemCls: 'no-padding-top no-padding-bottom',
                            boxLabel: 'Old tickets first',
                            name: 'ticket_date',
                            inputValue: 'ASC'
                        }]
                    }
                ]
            }, {
                id: 'tickets_filter_company',
                xtype: 'combo',
                fieldLabel: 'Company',
                labelStyle: 'font-size: 14px;',
                cls: 'with-right-border',
                store: new Ext.data.Store({
                    proxy: new Ext.data.HttpProxy({
                        url: baseUrl + '/manage-company/company-search',
                        method: 'post'
                    }),

                    reader: new Ext.data.JsonReader({
                        root: 'rows',
                        totalProperty: 'totalCount',
                        id: 'companyId'
                    }, [
                        {name: 'companyId', mapping: 'company_id'},
                        {name: 'companyName', mapping: 'companyName'},
                        {name: 'companyEmail', mapping: 'companyEmail'}
                    ])
                }),
                valueField:   'companyId',
                displayField: 'companyName',
                typeAhead: false,
                emptyText: 'Type to search for company...',
                loadingText: 'Searching...',
                width: 285,
                listWidth: 285,
                listClass: 'no-pointer',
                pageSize: 10,
                minChars: 1,
                hideTrigger: true,
                tpl: new Ext.XTemplate(
                    '<tpl for="."><div class="x-combo-list-item" style="padding: 7px;">',
                        '<h3>{companyName}</h3>',
                        '<p style="padding-top: 3px;">Email: {companyEmail}</p>',
                    '</div></tpl>'
                ),
                itemSelector: 'div.x-combo-list-item'
            }

        ],

        buttons: [{
            text: 'Reset',
            handler: function() {
                filterForm.getForm().reset();
            }
        },{
            text: 'Apply Filter',
            cls: 'orange-btn',
            handler: function() {
                if(filterForm.getForm().isValid()) {
                    Ext.getCmp('tickets-grid-all').getStore().reload();
                }
            }
        }]
    });
};


Ext.extend(TicketsFilterPanel, Ext.form.FormPanel, {
});
