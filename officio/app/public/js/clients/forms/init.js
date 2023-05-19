function showForms(member_id) {
    var el = $('#formsTab-' + member_id);
    if (!el.length) {
        return;
    }

    Ext.getDom('formsTab-' + member_id).innerHTML = '';

    // Main tab
    new Ext.TabPanel({
        id: 'forms-tab-' + member_id,
        renderTo: 'formsTab-' + member_id,
        activeTab: 0,
        autoWidth: true,
        autoHeight: true,
        enableTabScroll: true,
        hideMode: 'visibility',
        plain: true,
        plugins: new Ext.ux.TabCloseMenu(),
        style: 'text-align:left',
        autoScroll: true,
        cls: 'clients-sub-tab-panel extjs-grid-noborder',
        items: [{
            id: 'forms-tab-main-' + member_id,
            title: 'Assigned Forms',
            items: new Ext.Panel({
                layout: 'form',
                autoWidth: true,
                bodyStyle: 'padding:0px;',
                items: new FormsGrid(member_id, null, "applicants")
            })
        }]
    });
}