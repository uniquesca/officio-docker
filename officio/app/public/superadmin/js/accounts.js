Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();
    $('#accounts_superadmin_container').css('min-height', getSuperadminPanelHeight() + 'px');

    var arrTabs = [];

    if (arrAccountsAccessRights['pt_invoices']) {
        arrTabs.push(new Ext.Panel({
            title: 'Manage PT Invoices',
            autoWidth: true,
            autoHeight: true,
            items: new PtInvoicesPanel({})
        }));
    }

    if (arrAccountsAccessRights['bad_debts_log']) {
        arrTabs.push(new Ext.Panel({
            title: 'Bad debts log',
            autoWidth: true,
            autoHeight: true,
            items: new BadDebtsGrid({
                height: 450
            })
        }));
    }

    if (arrAccountsAccessRights['automated_billing_log']) {
        arrTabs.push(new Ext.Panel({
            title: 'Automated billing log',
            autoWidth: true,
            autoHeight: true,
            items: new AutomatedLogPanel()
        }));
    }

    if (arrAccountsAccessRights['manage_pt_error_codes']) {
        arrTabs.push(new Ext.Panel({
            title: 'Manage PT Error codes',
            autoWidth: true,
            autoHeight: true,
            items: new PTErrorCodesGrid()
        }));
    }

    if (arrTabs.length) {
        new Ext.TabPanel({
            renderTo: 'accounts_superadmin_container',
            autoHeight: true,
            activeTab: 0,
            deferredRender: true,
            frame: false,
            plain: true,
            cls: 'tabs-second-level',

            defaults:
            {
                autoWidth: true,
                autoScroll: true
            },

            items: arrTabs
        });
    } else {
        new Ext.form.Label({
            renderTo: 'accounts_superadmin_container',
            text: 'You do not have access to the tabs panel.'
        });
    }
    updateSuperadminIFrameHeight('#accounts_superadmin_container');
});