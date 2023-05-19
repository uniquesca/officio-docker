var ExportCompanyPanel = function (companyId, bgColor) {
    thisPanel = this;
    var btnWidth = 110;
    this.items = [
        {
            xtype: 'button',
            text: 'Cases',
            width: btnWidth,
            handler: function() {
                if (clientsTotalCount > exportRangeClients) {
                    var scrollMenu = new Ext.menu.Menu();
                    var start = 0;
                    var end   = 0;
                    var rangeCount = Math.ceil(clientsTotalCount / exportRangeClients);
                    for (var i = 0; i < rangeCount; ++i){
                        start = i * exportRangeClients + 1;
                        end   = (i+1) * exportRangeClients;
                        if (i == (rangeCount - 1)) {
                            end = clientsTotalCount;
                        }
                        scrollMenu.add({
                            text: 'Export ' + (start) + ' - ' + (end) + ' records',
                            listeners:{
                                click: thisPanel.runExport.createDelegate(this, [companyId, 'cases', start - 1, exportRangeClients])
                            }
                        });
                    }

                    scrollMenu.showAt([this.getEl().getX() + this.getWidth(), 0]);
                    if (30 * i > Ext.getBody().getHeight()) {
                        scrollMenu.showAt([this.getEl().getX() + this.getWidth(), 0]);
                    } else {
                        scrollMenu.show(this.getEl())
                    }
                } else {
                    thisPanel.runExport(companyId, 'cases', 0, clientsTotalCount);
                }
            }
        }, {
            xtype: 'button',
            text: 'Prospects',
            width: btnWidth,
            handler: function() {
                if (prospectsTotalCount > exportRangeProspects) {
                    var scrollMenu = new Ext.menu.Menu();
                    var start = 0;
                    var end   = 0;
                    var rangeCount = Math.ceil(prospectsTotalCount / exportRangeProspects);
                    for (var i = 0; i < rangeCount; ++i){
                        start = i * exportRangeProspects + 1;
                        end   = (i+1) * exportRangeProspects;
                        if (i == (rangeCount - 1)) {
                            end = prospectsTotalCount;
                        }
                        scrollMenu.add({
                            text: 'Export ' + (start) + ' - ' + (end) + ' records',
                            listeners:{
                                click: thisPanel.runExport.createDelegate(this, [companyId, 'prospects', start - 1, exportRangeProspects])
                            }
                        });
                    }

                    if (30 * i > Ext.getBody().getHeight()) {
                        scrollMenu.showAt([this.getEl().getX() + this.getWidth(), 0]);
                    } else {
                        scrollMenu.show(this.getEl())
                    }
                } else {
                    thisPanel.runExport(companyId, 'prospects', 0, prospectsTotalCount);
                }
            }
        }, {
            xtype: 'button',
            text:  'Notes',
            width: btnWidth,
            menu:  {
                items: [
                    {
                        text: 'Cases',
                        handler: this.runExport.createDelegate(this, [companyId, 'cases_notes'])
                    },
                    {
                        text: 'Prospects',
                        handler: this.runExport.createDelegate(this, [companyId, 'prospects_notes'])
                    }
                ]
            }
        }, {
            xtype: 'button',
            text: 'Tasks',
            width: btnWidth,
            handler: this.runExport.createDelegate(this, [companyId, 'tasks'])
        }, {
            xtype: 'button',
            text: 'Time Log',
            width: btnWidth,
            handler: this.runExport.createDelegate(this, [companyId, 'time_log'])
        }, {
            xtype: 'button',
            text: 'Clients Balances',
            width: btnWidth,
            handler: this.runExport.createDelegate(this, [companyId, 'client_balances'])
        }, {
            xtype: 'button',
            text: 'Clients Transactions',
            width: btnWidth,
            handler: this.runExport.createDelegate(this, [companyId, 'client_transactions'])
        }, {
            xtype: 'button',
            text: ta_label,
            width: btnWidth,
            handler: this.runExport.createDelegate(this, [companyId, 'trust_account'])
        }, {
            xtype: 'button',
            text: 'Emails',
            width: btnWidth,
            listeners: {
                click: function(){
                    var wnd = new ExportEmailsDialog({
                        companyId: companyId
                    });

                    wnd.show();
                    wnd.center();
                }
            }
        }, {
            xtype: 'button',
            text: _('Profiles and Cases'),
            tooltip: _('This option will export Profiles and Cases without Dependents in csv format (client id is also exported)'),
            width: btnWidth,
            listeners: {
                click: function(){
                    var wnd = new exportProfilesAndCasesDialog({
                        companyId: companyId
                    });

                    wnd.show();
                    wnd.center();
                }
            }
        }
    ];

    ExportCompanyPanel.superclass.constructor.call(this, {
        layout:'table',
        renderTo: 'company_export_container',

        layoutConfig: {
            tableAttrs: {
                style: {
                    width: '100%',
                    'background-color': bgColor ? bgColor : '#fff'
                }
            },
            columns: 4
        }
    });
};

Ext.extend(ExportCompanyPanel, Ext.Panel, {
    runExport: function(companyId, exportWhat, exportStart, exportRange) {
        if (exportStart === undefined) {
            exportStart = false;
        }
        if (exportRange === undefined) {
            exportRange = false;
        }

        if (exportWhat == 'prospects' || exportWhat == 'cases' ) {
            url = String.format(
                '{0}/manage-company/export?company_id={1}&export={2}&exportStart={3}&exportRange={4}',
                baseUrl,
                companyId,
                exportWhat,
                exportStart,
                exportRange
            );
        } else {
            url = String.format(
                '{0}/manage-company/export?company_id={1}&export={2}',
                baseUrl,
                companyId,
                exportWhat
            );
        }

        window.open(url);
    }
});