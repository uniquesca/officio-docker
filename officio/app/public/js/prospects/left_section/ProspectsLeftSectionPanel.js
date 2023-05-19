var ProspectsLeftSectionPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.ProspectsLeftSectionGrid = new ProspectsLeftSectionGrid({
        id: config.panelType + '-left-section-grid',
        region: 'center',
        arrQueuesToShow: config.arrQueuesToShow,
        panelType: config.panelType,
        height: config.height
    }, this);

    ProspectsLeftSectionPanel.superclass.constructor.call(this, {
        id: config.panelType + '-left-section-panel',
        layout: 'border',
        items: [
            this.ProspectsLeftSectionGrid
        ]
    });
};

Ext.extend(ProspectsLeftSectionPanel, Ext.Panel, {
    getDefaultOffices: function () {
        var arrDefaultRecords = Ext.state.Manager.get(this.panelType + '_checked_offices');

        // Check if all saved in cookies records are still correct (current user has access to)
        if (!empty(arrDefaultRecords)) {
            var filteredOffices = [];
            Ext.each(arrDefaultRecords, function (officeIdInCookie) {
                var booFoundOffice = false;
                Ext.each(arrApplicantsSettings.queue_settings.queue_allowed, function (oOfficeInfo) {
                    if (parseInt(oOfficeInfo['option_id'], 0) === parseInt(officeIdInCookie, 0)) {
                        booFoundOffice = true;
                        return false;
                    }
                });

                if (booFoundOffice) {
                    filteredOffices.push(officeIdInCookie);
                }
            });

            arrDefaultRecords = filteredOffices;
        }

        // Not saved in cookie or no access rights? Preselect the default one
        if (empty(arrDefaultRecords) || empty(arrDefaultRecords.length)) {
            arrDefaultRecords = [arrApplicantsSettings.office_default_selected === 'all' ? 0 : arrApplicantsSettings.office_default_selected];
        }

        return arrDefaultRecords;
    },

    refreshList: function() {
        this.ProspectsLeftSectionGrid.store.reload();
    }
});