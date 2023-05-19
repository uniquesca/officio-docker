AutomatedLogSessionGrid = function(session_id) {
    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: false,

        url: baseUrl + '/automated-billing-log/get-session-details',
        baseParams: {
            session_id: session_id
        },


        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'log_id',
            fields: [
                'log_id',
                'log_company',
                'log_amount',
                'log_retry',
                {name: 'log_session_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
                'log_invoice_id',
                'company_id',
                {name: 'invoice_date', type: 'date', dateFormat: 'Y-m-d'},
                {name: 'log_old_billing_date', type: 'date', dateFormat: 'Y-m-d'},
                {name: 'log_new_billing_date', type: 'date', dateFormat: 'Y-m-d'},
                'log_status',
                'log_error_code',
                'log_error_message'
            ]
        }),

        sortInfo: {
            field:     'log_company',
            direction: 'ASC'
        }
    });
    
    this.sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        this.sm,
        {
            header: 'Company',
            dataIndex: 'log_company',
            renderer: this.formatCompany.createDelegate(this),
            sortable: true
        }, {
            header: 'Amount',
            dataIndex: 'log_amount',
            renderer: 'usMoney',
            width: 20,
            align: 'right',
            sortable: true
        }, {
            header: 'Attempting since',
            dataIndex: 'invoice_date',
            width: 30,
            align: 'center',
            renderer: this.formatAttemptingDate.createDelegate(this),
            sortable: true
        }, {
            header: 'Old billing date',
            dataIndex: 'log_old_billing_date',
            width: 30,
            align: 'center',
            renderer: this.formatDate.createDelegate(this),
            sortable: true
        }, {
            header: 'New billing date',
            dataIndex: 'log_new_billing_date',
            width: 30,
            align: 'center',
            renderer: this.formatDate.createDelegate(this),
            sortable: true
        }, {
            header: 'Status',
            dataIndex: 'log_status',
            width: 20,
            align: 'right',
            renderer: this.formatStatus.createDelegate(this),
            sortable: true
        }
    ];

    this.contextMenu = new Ext.menu.Menu({
        enableScrolling: false,
        items: [{
            text: 'Save error code',
            cls: 'log-menu-error-add',
            handler: function() {
                Ext.getCmp('log-session-grid' + session_id).addErrorCode();
            }
        }]
    });

    AutomatedLogSessionGrid.superclass.constructor.call(this, {
        id: 'log-session-grid' + session_id,
        autoExpandColumn: 'log_company',
        loadMask: {msg: 'Loading...'},
        height: 560,
        stripeRows: true,
        viewConfig: {
            forceFit: true,
            enableRowBody: true
        }
    });

    this.on('rowcontextmenu', this.onContextClick, this);
    this.on('render', this.initRowTips, this);
};

Ext.extend(AutomatedLogSessionGrid, Ext.grid.GridPanel, {
    onContextClick : function(obj, row, e){
        e.stopEvent();

        this.getSelectionModel().selectRow(row);
        var rec = this.getSelectionModel().getSelected();
        if(rec.data.log_status == 'F' && !empty(rec.data.log_error_code)) {
            this.contextMenu.showAt(e.getXY());
        }
    },

    addErrorCode: function() {
        var rec = this.getSelectionModel().getSelected();
        if(rec) {

            Ext.getBody().mask('Saving...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-pt-error-codes/save-code',
                params: {
                    'error-code':        rec.data.log_error_code,
                    'error-description': rec.data.log_error_message
                },

                success: function(res, request) {
                    Ext.getBody().unmask();

                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        Ext.simpleConfirmation.success(result.message);
                    } else {
                        Ext.simpleConfirmation.error(result.message);
                    }
                },

                failure: function(form, action) {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(
                        'Internal server error. Please try again.'
                    );
                }
            });
        }
    },

    initRowTips: function() {
        var logGrid = this;
        logGrid.tip = new Ext.ToolTip({
            title: '<p style="color: red; font-weight: normal;">Error details:</p>',
            view: logGrid.getView(),
            target: logGrid.getView().mainBody,
            delegate: '.x-grid3-row',
            trackMouse: true,
            renderTo: document.body,
            listeners: {
                beforeshow: function updateTipBody(tip) {
                    var currRowIndex = tip.view.findRowIndex(tip.triggerElement);
                    var record = logGrid.store.getAt(currRowIndex);
                    if(record && !empty(record.data['log_error_message'])) {
                        var msg = record.data['log_error_message'];
                        if(record.data['log_retry'] == 'Y') {
                            msg = String.format('[Retry for invoice id #{0}]<br/>', record.data['log_invoice_id']) + msg;
                        }
                        
                        tip.body.dom.innerHTML = msg;
                    } else {
                        return false;
                    }
                }
            }
        });
    },

    formatCompany: function(val, item, rec) {
        return String.format(
            '<a href="{0}/manage-company/edit?company_id={1}" class="normal_link" target="_blank">{2}</a>',
            baseUrl,
            rec.data.company_id,
            val
        );
    },

    formatAttemptingDate: function(value, item, rec){
        if(rec.data.log_retry == 'Y') {
            return this.formatDate(value);
        }
    },

    formatDate: function(value){
        return value ? value.dateFormat(dateFormatFull) : '';
    },

    formatStatus: function(status){
        var strResult = status;
        switch(status) {
            case 'F' :
                strResult = '<span style="color: red">Failed</span>';
                break;
            case 'C' :
                strResult = '<span style="color: green">Completed</span>';
                break;
            default:
                break;
        }
        return strResult;
    }
});


AutomatedLogView = function() {
    var welcomeTabContent = String.format(
        'Please use the navigation links on the left side to open saved log sessions.<br/><br/>' +
        'To run <b>Automatic billing</b> cron now - please click on <a href="{0}/api/index/run-recurring-payments" target="_blank" style="color: red;">this link</a>.',
        topBaseUrl
    );
    AutomatedLogView.superclass.constructor.call(this, {
        region:'center',
        deferredRender:false,
        activeTab:0,
        frame: false,
        plain: true,
        cls: 'tabs-second-level',
        items:[{
            title: '<i class="las la-info-circle"></i>' + _('Welcome'),
            style: 'padding: 5px; font-size: 16px;',
            html: welcomeTabContent,
            autoScroll:true
        }]
    });
};

Ext.extend(AutomatedLogView, Ext.TabPanel, {
    generateTabId: function(id) {
        return 'log-session-tab-' + id;
    },

    openTab: function(node) {
        var tabId = this.generateTabId(node.attributes.session_id);
        var tab = Ext.getCmp(tabId);
        if(!tab) {
            tab = this.add({
                id: tabId,
                title: node.attributes.text,
                closable: true,
                items: new AutomatedLogSessionGrid(node.attributes.session_id)
            });
        }
        tab.show();
    },

    closeTab: function(node_attributes) {
        var tabId = this.generateTabId(node_attributes.session_id);
        var tab = Ext.getCmp(tabId);
        if(tab) {
            tab.ownerCt.remove(tabId);
        }
    }
});
