var ProspectsGridPanel = function (config, owner) {
    var thisPanel = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.activeFilterDisplayField = new Ext.form.DisplayField({
        cls: 'not-real-link-big simple_label_with_padding',
        hideLabel: true,
        value: ''
    });

    this.activeFilterQueueCombo = new Ext.ux.form.LovCombo({
        cls: 'simple_combo',
        width: 200,
        minWidth: 200,
        hidden: true,

        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'option_id'
            }, [{name: 'option_id'}, {name: 'option_name'}]),
            data: arrApplicantsSettings.queue_settings.queue_allowed
        },

        triggerAction: 'all',
        valueField: 'option_id',
        displayField: 'option_name',
        mode: 'local',
        useSelectAll: false,
        allowBlank: false,
        listeners: {
            select: function () {
                thisPanel.applyOfficeComboSelectedValues();
                thisPanel.applyFilter();
            },

            'afterrender': thisPanel.loadSettings.createDelegate(this, [])
        }
    });

    this.prospectsGrid = new ProspectsGrid({
        height: initPanelSize() - 55,
        panelType: config.panelType,
        initSettings: config.initSettings
    }, this);

    ProspectsGridPanel.superclass.constructor.call(this, {
        id: config.panelType + '-grid-panel',
        cls: 'extjs-panel-with-border',
        style: 'padding: 15px 20px',
        autoHeight: true,

        items: [
            {
                xtype: 'container',
                layout: 'column',
                items: [
                    this.activeFilterDisplayField,
                    this.activeFilterQueueCombo
                ]
            },
            this.prospectsGrid
        ]
    });
};

Ext.extend(ProspectsGridPanel, Ext.Panel, {
    updateProspectActiveFilter: function () {
        var thisPanel = this;
        var booShowFilterQueueCombo = false;
        var booClearFilterSelection = false;
        var booClearQueueSelection = false;
        var showingLabel = _('Showing:');
        var strSelectedFilter = '';

        var oBaseParams = thisPanel.prospectsGrid.store.baseParams;
        if (!empty(oBaseParams.filter)) {
            showingLabel = _('Search:');
            strSelectedFilter = oBaseParams.filter;
            booClearFilterSelection = true;
            booClearQueueSelection = true;
        } else {
            if (oBaseParams.type === 'search-prospects') {
                strSelectedFilter = _('All Prospects');
                booClearFilterSelection = true;
                booClearQueueSelection = true;
            } else if (oBaseParams.type === 'office') {
                booClearFilterSelection = true;

                var oLeftGrid = Ext.getCmp(this.panelType + '-left-section-grid');
                if (oLeftGrid.isFavoriteChecked()) {
                    booShowFilterQueueCombo = true;

                    showingLabel = oLeftGrid.getSelectedOfficesLabels() + ':';
                    strSelectedFilter = '';
                } else {
                    showingLabel = arrApplicantsSettings.office_label + '(s):';
                    strSelectedFilter = oLeftGrid.getSelectedOfficesLabels();
                }
            } else {
                var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
                Ext.each(tabPanel.arrProspectNavigationTabs, function (oItem) {
                    if (oItem.itemId == oBaseParams.type) {
                        strSelectedFilter = oItem.itemName;
                    }
                });
                booClearQueueSelection = true;
            }
        }

        var title = String.format(
            '<a href="#" onclick="return false;" title="{0}" class="not-real-link-big" /><span class="not-real-link-big-label">{1}</span>{2}</a>',
            showingLabel + strSelectedFilter,
            showingLabel,
            strSelectedFilter
        )

        thisPanel.activeFilterDisplayField.setValue(title);

        // Toggle the Offices combo
        thisPanel.activeFilterQueueCombo.setVisible(booShowFilterQueueCombo);

        if (booClearFilterSelection) {
            var prospectsTodayGrid = Ext.getCmp(thisPanel.panelType + '-tgrid');
            if (prospectsTodayGrid) {
                prospectsTodayGrid.getSelectionModel().clearSelections();
            }
        }

        if (booClearQueueSelection) {
            var prospectsLeftQueueGrid = Ext.getCmp(thisPanel.panelType + '-left-section-grid');
            if (prospectsLeftQueueGrid) {
                prospectsLeftQueueGrid.getSelectionModel().clearSelections();
            }
        }
    },

    loadSettings: function () {
        // Set main params
        var combo = this.activeFilterQueueCombo;

        var leftPanel = Ext.getCmp(this.panelType + '-left-section-panel');
        var arrDefaultRecords = empty(leftPanel) ? [] : leftPanel.getDefaultOffices();
        if (empty(arrDefaultRecords) || (arrDefaultRecords.length === 1 && arrDefaultRecords[0] === 'favourite')) {
            combo.setValue(arrApplicantsSettings.queue_settings.queue_selected);
        } else {
            // If "All" is selected - check all offices
            if (arrDefaultRecords.length === 1 && arrDefaultRecords[0] === 0) {
                arrDefaultRecords = [];
                combo.store.each(function (rec) {
                    arrDefaultRecords.push(rec.data[combo['valueField']]);
                });
            }

            combo.setValue(arrDefaultRecords);
        }
    },

    applyOfficeComboSelectedValues: function () {
        var combo = this.activeFilterQueueCombo;

        // Calculate the width of the entered text (selected offices)
        var metrics = Ext.util.TextMetrics.createInstance(combo.getEl());
        var newComboWidth = metrics.getWidth(combo.getCheckedDisplay()) + 60;

        // The width of the combo should not be less than the minimum width
        newComboWidth = Ext.max([newComboWidth, combo.minWidth]);

        // And cannot be wider than max allowed width (of the tab - width of the label - paddings)
        var maxWidth = this.owner.width - this.activeFilterDisplayField.getEl().getWidth() - 41;
        newComboWidth = Ext.min([newComboWidth, maxWidth]);

        combo.setWidth(newComboWidth);
        combo.getResizeEl().setWidth(newComboWidth);
    },

    applyFilter: function () {
        var thisPanel = this;
        var combo = thisPanel.activeFilterQueueCombo;

        if (combo.isVisible() && combo.isValid()) {
            var selectedOffices = combo.getValue().split(combo.separator);

            if (!empty(selectedOffices)) {
                var store = thisPanel.prospectsGrid.getStore();
                store.setBaseParam('offices', Ext.encode(selectedOffices));
                store.reload();
            }
        }
    }
});
