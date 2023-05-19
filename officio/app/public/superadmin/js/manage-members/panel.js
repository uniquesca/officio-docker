var ManageMembersPanel = function (config) {
    Ext.apply(this, config);

    this.membersGrid = new ManageMembersGrid({
        region: 'center'
    }, this);

    this.membersFilterForm = new ManageMembersFilterPanel({
        title:  'Extended Filter',
        region: 'east'
    }, this);

    ManageMembersPanel.superclass.constructor.call(this, {
        id:        'manage-members-panel',
        frame:     true,
        autoWidth: true,
        frame: false,
        border: false,
        height:    Ext.max([getSuperadminPanelHeight(), 530]),
        layout:    'border',
        items:     [
            this.membersGrid, this.membersFilterForm
        ]
    });
};

Ext.extend(ManageMembersPanel, Ext.Panel, {});