var CompaniesGrid = function () {
    // Init basic properties
    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-company/get-companies',
        method: 'POST',
        autoLoad: true,

        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'company_id',
            fields: [
                {name: 'company_id', type: 'int'},
                {name: 'company_name', type: 'string'},
                {name: 'company_admins', type: 'string'},
                {name: 'company_country', type: 'string'},
                {name: 'company_phone', type: 'string'},
                {name: 'company_add_date', type: 'date', dateFormat: 'Y-m-d H:i:s'},
                {name: 'company_trial', type: 'string'},
                {name: 'company_next_billing_date', type: 'date', dateFormat: 'Y-m-d'},
                {name: 'company_last_logged_in', type: 'date', dateFormat: 'Y-m-d H:i:s'},
                {name: 'company_status', type: 'string'}
            ]
        }),

        listeners: {
            'beforeload': function (store, options) {
                var result_grid = Ext.getCmp('companies-grid');
                var booShowLastLoginColumn = null;

                var columns = result_grid.getColumnModel().config;
                for (var i = 0; i < columns.length; i++) {
                    if (!columns[i].hidden && columns[i].dataIndex === 'company_last_logged_in') {
                        booShowLastLoginColumn = true;
                    }
                }

                var params = {
                    booShowLastLoginColumn: booShowLastLoginColumn
                };
                Ext.apply(options.params, params);
            }
        },

        sortInfo: {
            field: "company_id",
            direction: "DESC"
        }
    });

    this.columns = [
        {
            header: 'Id',
            sortable: true,
            dataIndex: 'company_id',
            width: 80
        }, {
            id: 'col_company_name',
            header: 'Company Name',
            sortable: true,
            dataIndex: 'company_name',
            renderer: this.renderCompanyName.createDelegate(this),
            width: 50
        }, {
            header: 'Company Admin(s)',
            sortable: false,
            dataIndex: 'company_admins',
            width: 220,
            renderer: function (val) {
                return empty(val) ? '-' : String.format(
                    '<div style="max-height: 58px; overflow-y: auto; overflow-x: hidden;">{0}</div>', val
                );
            }
        }, {
            header: 'Country',
            sortable: true,
            dataIndex: 'company_country',
            width: 90
        }, {
            header: 'Phone',
            sortable: true,
            dataIndex: 'company_phone',
            width: 110
        }, {
            header: 'Add Date',
            sortable: true,
            dataIndex: 'company_add_date',
            align: 'center',
            renderer: function (val) {
                return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
            },
            width: 100
        }, {
            header: 'Trial',
            sortable: true,
            align: 'center',
            dataIndex: 'company_trial',
            width: 60
        }, {
            header: 'Next Billing Date',
            sortable: true,
            dataIndex: 'company_next_billing_date',
            align: 'center',
            renderer: function (val) {
                return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
            },
            width: 150
        }, {
            header: 'Last Logged In',
            sortable: true,
            dataIndex: 'company_last_logged_in',
            align: 'center',
            hidden: true,
            renderer: function (val) {
                return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
            },
            width: 80
        }, {
            header: 'Status',
            sortable: true,
            dataIndex: 'company_status',
            width: 80
        }, {
            header: 'Action',
            sortable: false,
            dataIndex: 'company_actions',
            align: 'center',
            renderer: this.renderAction.createDelegate(this),
            hidden: !arrAccessRights.login,
            width: 80
        }
    ];

    var grid_sm = new Ext.grid.CheckboxSelectionModel();
    this.columns.unshift(grid_sm);

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New Company'),
            cls: 'orange-btn',
            hidden: !arrAccessRights['add'],
            handler: function () {
                Ext.ux.Popup.show(baseUrl + '/manage-company/add', true);
            }
        },
        {
            text: '<i class="las la-capsules"></i>' +_('Change Status'),

            // Place a reference in the GridPanel
            ref: '../changeStatusBtn',
            disabled: true,
            hidden: !arrAccessRights['change_status'],
            menu: {
                id: 'status-menu',
                cls: 'status-menu',
                items: [
                    {
                        text: 'Active',
                        value: 'active',
                        checked: true,
                        group: 'rp-group',
                        checkHandler: this.changeStatus,
                        scope: this,
                        iconCls: 'preview-bottom'
                    }, {
                        text: 'Inactive',
                        value: 'inactive',
                        checked: false,
                        group: 'rp-group',
                        checkHandler: this.changeStatus,
                        scope: this,
                        iconCls: 'preview-right'
                    }, {
                        text: 'Suspended',
                        value: 'suspended',
                        checked: false,
                        group: 'rp-group',
                        checkHandler: this.changeStatus,
                        scope: this,
                        iconCls: 'preview-hide'
                    }
                ]
            }
        }, {
            text: '<i class="las la-window-close"></i>' + _('Delete'),
            tooltip: 'Delete selected company',

            // Place a reference in the GridPanel
            ref: '../deleteCompanyBtn',
            disabled: true,
            hidden: !arrAccessRights['delete'],
            handler: this.deleteCompany.createDelegate(this)
        }, {
            'xtype': 'tbseparator',
            hidden: !arrAccessRights['delete'] && !arrAccessRights['change_status']
        }, {
            id: 'mailer-send-btn',
            text: '<i class="las la-envelope"></i>' + _('Email'),
            hidden: !arrAccessRights['mass-email'],
            cls: 'x-btn-text-icon',
            menu: {
                items: [
                    {
                        text: 'All companies',
                        handler: this.showMassMailingDialog.createDelegate(this, [true])
                    }, {
                        text: 'Selected companies only',
                        handler: this.showMassMailingDialog.createDelegate(this, [false])
                    }
                ]
            }
        }, {
            'xtype': 'tbseparator',
            hidden: !arrAccessRights['delete'] && !arrAccessRights['change_status'] && !arrAccessRights['mass-email']
        }, {
            text: '<i class="las la-file-download"></i>' + _('Export Main Info'),
            tooltip: _('Export Main Info for currently filtered companies'),
            hidden: !arrAccessRights['delete'],
            handler: this.exportCompaniesMainInfo.createDelegate(this)
        }, '->',
        {
            xtype: 'appGridSearch',
            width: 250,
            emptyText: 'Search company...',
            store: this.store
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 25,
        store: this.store,
        displayInfo: true,
        emptyMsg: 'No companies to display'
    });

    this.selModel = grid_sm;

    this.getSelectionModel().on({
        'selectionchange': function (sm) {
            var selInvoicesCount = sm.getCount();
            if (selInvoicesCount === 1) {
                this.grid.changeStatusBtn.enable();
                this.grid.deleteCompanyBtn.enable();
            } else {
                this.grid.changeStatusBtn.disable();
                this.grid.deleteCompanyBtn.disable();
            }
        }
    });

    CompaniesGrid.superclass.constructor.call(this, {
        id: 'companies-grid',
        width: $('#companies_list_container').outerWidth(),
        height: this.getCorrectThisGridHeight(),
        autoExpandColumn: 'col_company_name',
        stripeRows: true,
        cls: 'extjs-grid',
        viewConfig: {emptyText: 'No companies were found.'},
        renderTo: 'companies_list_container',
        loadMask: true
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(CompaniesGrid, Ext.grid.GridPanel, {
    getCorrectThisGridHeight: function () {
        return initPanelSize() - $('#companies_advanced_search_container').outerHeight() - $('#companies-tab-panel').find('.x-tab-panel-header').outerHeight();
    },

    updatedThisGridHeight: function () {
        this.setHeight(this.getCorrectThisGridHeight());
    },

    showMassMailingDialog: function (booAllCompanies) {
        var i;
        var arrSelectedCompanies = this.getSelectionModel().getSelections();
        if (!booAllCompanies && arrSelectedCompanies.length <= 0) {
            Ext.simpleConfirmation.warning('No company is selected.<br/>Please select at least one company.');
        } else {
            var arrCompanyIds = [];
            if (booAllCompanies) {
                arrCompanyIds = this.store.reader.jsonData.all_ids;
            } else {
                for (i = 0; i < arrSelectedCompanies.length; i += 1) {
                    arrCompanyIds.push(arrSelectedCompanies[i].data.company_id);
                }
            }

            var wnd = new MassEmailDialog(arrCompanyIds);
            wnd.show();
        }
    },

    renderAction: function (val, cell, rec) {
        var strAction = '';
        if (arrAccessRights.login) {
            strAction = String.format(
                '<a href="#" onclick="Ext.getCmp(\'companies-grid\').loginActionConfirmation({1}, \'{2}\')" title="Login as company admin"><i class="las la-sign-in-alt"></i></a>',
                baseUrl,
                rec.data.company_id,
                rec.data.company_status
            );
        }

        return strAction;
    },

    loginActionConfirmation: function (companyId, status) {
        if (status === 'Suspended') {
            Ext.Msg.confirm('Please confirm', 'This company is suspended. Are you sure you want to log in?', function (btn) {
                if (btn === 'yes') {
                    window.location = baseUrl + '/manage-company-as-admin/' + companyId;
                }
            });
        } else {
            window.location = baseUrl + '/manage-company-as-admin/' + companyId;
        }

        return false;
    },

    renderCompanyName: function (val, cell, rec) {
        var strEdit = val;
        if (arrAccessRights.edit) {
            var filteredVal = val.replace(/"/g, "''");
                filteredVal = filteredVal.replace(/'/g, "\\'");

            strEdit = String.format(
                '<a href="#" onclick="showCompanyPage({0}, \'{1}\'); return false;" title="Click to edit this company">{2}</a>',
                rec.data.company_id,
                filteredVal,
                val
            );
        }

        return strEdit;
    },

    getSelectedCompany: function () {
        var sel = this.view.grid.getSelectionModel().getSelections();
        return sel.length > 0 ? sel[0].data : false;
    },

    updateToolbarButtons: function () {
        var sel = this.getSelectedCompany();
        if (sel) {
            var statusMenu = Ext.menu.MenuMgr.get('status-menu');
            var items = statusMenu.items.items;
            var a = items[0], i = items[1], s = items[2];

            switch (sel.company_status) {
                case 'Active':
                    a.setChecked(true, true);
                    break;

                case 'Suspended':
                    s.setChecked(true, true);
                    break;

                //case 'Inactive':
                default:
                    i.setChecked(true, true);
                    break;
            }
        }
    },

    sendRequestToUpdateStatus: function (booCloseWindow) {
        var grid = Ext.getCmp('companies-grid');
        var sel = grid.getSelectedCompany();

        var radios = Ext.getCmp('status-menu').items.items;
        var newCompanyStatus = '';
        Ext.each(radios, function (item) {
            if (item.checked) {
                newCompanyStatus = item.value;
            }
        });


        Ext.getBody().mask('Updating...');
        Ext.Ajax.request({
            url: baseUrl + '/manage-company/update-status',

            params: {
                'company_id': sel.company_id,
                'new_status': newCompanyStatus
            },

            success: function (f) {
                var result = Ext.decode(f.responseText);
                if (result.success) {
                    grid.store.reload();

                    if (booCloseWindow) {
                        var wnd = Ext.getCmp('charge-invoice-window');
                        wnd.close();
                    }

                    Ext.simpleConfirmation.success('Company status was updated successfully.');
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                    grid.updateToolbarButtons();
                }

                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error('Cannot update status. Please try again later.');
                grid.updateToolbarButtons();
                Ext.getBody().unmask();
            }
        });
    },

    changeStatus: function (item, booChecked) {
        if (booChecked) {
            var sel = this.getSelectedCompany();
            var grid = this.view.grid;

            if (sel.company_status === 'Suspended') {
                // Load failed invoices list
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company/get-failed-invoices',

                    params: {
                        company_id: sel.company_id
                    },

                    success: function (f) {
                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            if (result.totalCount > 0) {
                                // Show new window with grid
                                var wnd = new CompaniesFailedInvoicesWindow(result);
                                wnd.show();
                            } else {
                                grid.sendRequestToUpdateStatus();
                            }
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                            grid.updateToolbarButtons();
                        }

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error('Cannot get company failed invoices list. Please try again later.');
                        grid.updateToolbarButtons();
                        Ext.getBody().unmask();
                    }
                });
            } else {
                grid.sendRequestToUpdateStatus();
            }
        }
    },

    deleteCompany: function () {
        var sel = this.getSelectedCompany();
        var grid = this.view.grid;

        var question = String.format(
            'Are you sure want to delete "<i>{0}</i>" company?',
            sel.company_name
        );

        Ext.MessageBox.buttonText.yes = 'Yes, delete company';
        Ext.Msg.confirm('Please confirm', question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company/delete',

                    params: {
                        company_id: sel.company_id
                    },

                    success: function (f) {
                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            grid.store.reload();
                            Ext.simpleConfirmation.success('Company was deleted successfully.');
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                        }

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error('Cannot delete. Please try again later.');
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },

    exportCompaniesMainInfo: function () {
        var arrCompanyIds = this.store.reader.jsonData.all_ids;
        if (empty(arrCompanyIds.length)) {
            Ext.simpleConfirmation.warning('No company is found.');
        } else {
            var params = {
                'companies_ids': Ext.encode(arrCompanyIds)
            };

            submit_post_via_hidden_form(
                baseUrl + '/manage-company/export-companies-main-info',
                params
            );
        }
    }
});