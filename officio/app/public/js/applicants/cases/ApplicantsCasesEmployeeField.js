var ApplicantsCasesEmployeeField = function(config, arrAllOptions, arrSelectedOptions, hiddenCombo) {
    var thisPanel = this;
    Ext.apply(this, config);

    this.hiddenCombo = hiddenCombo;
    this.arrAllOptions = arrAllOptions;
    this.arrSelectedOptions = arrSelectedOptions;

    // Generate list of cases already selected, show them in the panel
    var arrSelectedCases = [];
    Ext.each(this.arrAllOptions, function (oMemberInfo) {
        oMemberInfo.selectable = !thisPanel.arrSelectedOptions.has(oMemberInfo.member_id);
        if (!oMemberInfo.selectable) {
            arrSelectedCases.push(
                thisPanel.generateNewCaseRow(oMemberInfo.member_id, oMemberInfo.member_name)
            );
        }
    });

    this.selectedCasesPanel = new Ext.Panel({
        style: 'padding: 5px 0;',
        items: arrSelectedCases
    });

    this.allCasesCombo = new Ext.form.ComboBox({
        xtype: 'combo',
        store: new Ext.data.Store({
            data: this.arrAllOptions,
            reader: new Ext.data.JsonReader(
                {id: 0},
                Ext.data.Record.create([
                {name: 'member_id'},
                {name: 'member_name'},
                {name: 'selectable'}
                ])
            )
        }),
        tpl: '<tpl for=".">' +
            '<div ext:qtip="{tip}" class="x-combo-list-item ' +
                '<tpl if="selectable == false">' +
                    'x-combo-list-item-unselectable' +
                '</tpl>' +
            '">{member_name}</div>' +
            '</tpl>',
        width: 207,
        mode: 'local',
        style: 'margin-bottom: 3px;',
        displayField: 'member_name',
        valueField: 'member_id',
        typeAhead: true,
        forceSelection: true,
        triggerAction: 'all',
        emptyText: 'Select...',
        selectOnFocus: true,
        editable: false
    });
    this.allCasesCombo.on('beforeselect', this.checkIsCaseSelected.createDelegate(this));

    ApplicantsCasesEmployeeField.superclass.constructor.call(this, {
        cls: 'profile-panel',
        items: [
            this.selectedCasesPanel,
            this.allCasesCombo, {
                xtype: 'button',
                width: 207,
                text: 'Add',
                icon: topBaseUrl + '/images/icons/add.png',
                handler: this.onMarkCaseAsSelected.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ApplicantsCasesEmployeeField, Ext.Panel, {
    checkIsCaseSelected: function(combo, record) {
        return record.data.selectable;
    },

    removeCaseRow: function(btnDelete, event, selCaseId, selCaseName) {
        var thisPanel = this;
        var msg = String.format(
            'Are you sure you want to disassociate <i>"{0}"</i> case?',
            selCaseName
        );

        Ext.Msg.confirm('Please confirm', msg, function(btn){
            if(btn == 'yes') {
                thisPanel.selectedCasesPanel.remove(btnDelete.ownerCt);
                thisPanel.toggleCaseSelected(selCaseId, false);
            }
        });
    },

    generateNewCaseRow: function(selCaseId, selCaseName) {
        var thisPanel = this;
        return new Ext.Panel({
            layout: 'column',
            items: [

                {
                    xtype: 'button',
                    icon: topBaseUrl + '/images/icons/cross.png',
                    handler: thisPanel.removeCaseRow.createDelegate(this, [selCaseId, selCaseName], true)
                }, {
                    xtype: 'box',
                    autoEl: {tag: 'a', href: '#', 'class': 'bluelink', style: 'padding: 5px;', html: selCaseName},
                    listeners: {
                        scope: this,
                        render: function(c){
                            c.getEl().on('click', thisPanel.openMemberProfile.createDelegate(this, [selCaseId]), this, {stopEvent: true});
                        }
                    }
                }
            ]
        });
    },

    toggleCaseSelected: function(selCaseId, booSelected) {
        var idx = this.allCasesCombo.store.find(this.allCasesCombo.valueField, selCaseId);
        if (idx != -1) {
            var rec = this.allCasesCombo.store.getAt(idx);
            rec.beginEdit();
            rec.set('selectable', !booSelected);
            rec.endEdit();

            this.allCasesCombo.store.commitChanges();

            // Check the option in the hidden combo - so correct data will be submitted
            this.hiddenCombo.find('option').each(function(){
                if ($(this).val() ==  selCaseId){
                    if (booSelected) {
                        $(this).attr('selected', 'selected');
                    } else {
                        $(this).removeAttr('selected');
                    }
                }
            });
        }
    },

    markCaseAsSelected: function(selCaseId, selCaseName) {
        this.selectedCasesPanel.add(this.generateNewCaseRow(selCaseId, selCaseName));
        this.selectedCasesPanel.doLayout();

        this.toggleCaseSelected(selCaseId, true);
    },

    onMarkCaseAsSelected: function() {
        var selCaseId = this.allCasesCombo.getValue();
        var selCaseName = this.allCasesCombo.getRawValue();

        if (selCaseId != this.allCasesCombo.emptyText && !empty(selCaseId)) {
            this.markCaseAsSelected(selCaseId, selCaseName);
            this.allCasesCombo.reset();
        }
    },

    openMemberProfile: function(selCaseId) {
        // TODO: refactor
    }
});