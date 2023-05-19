var ApplicantsProfileClientReferralsDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var ds = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/profile/get-client-referrals',
            method: 'post',
        }),

        baseParams: {
            applicantId: config.applicantId
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'clientId'},
            {name: 'clientType'},
            {name: 'clientFullName'}
        ])
    });

    var resultTpl = new Ext.XTemplate(
        '<tpl for="."><div class="x-combo-list-item" style="padding: 2px;">',
        '<img border="0" src="{[this.getIcon(values)]}" title="{clientType}" align="absmiddle">&nbsp;&nbsp;',
        '{[this.formatName(values)]}',
        '</div></tpl>', {
            getIcon: function (record_data) {
                return String.format(
                    '{0}/images/mail/16x16/{1}',
                    topBaseUrl,
                    record_data.clientType === 'client' ? 'to-type-clients.png' : 'to-type-prospects.png'
                );
            },

            formatName: function (record_data) {
                return this.highlight(record_data.clientFullName, ds.reader.jsonData.query);
            },

            highlight: function (str, query) {
                var highlightedRow = str.replace(
                    new RegExp('(' + preg_quote(query) + ')', 'gi'),
                    "<b style='background-color: #FFFF99;'>$1</b>"
                );

                return highlightedRow;
            }
        }
    );

    this.applicantId = new Ext.form.Hidden({
        name: 'applicant_id',
        value: config.applicantId
    });

    this.referralId = new Ext.form.Hidden({
        name: 'referral_id',
        value: config.oReferral.referral_id
    });

    this.referralClientType = new Ext.form.Hidden({
        name: 'referral_client_type',
        value: empty(config.oReferral.referral_id) ? 0 : config.oReferral.referral_client_type
    });

    this.referralsCombo = new Ext.form.ComboBox({
        fieldLabel: _('Case or Prospect'),
        emptyText: _('Type and select a Case/Prospect...'),
        name: 'referral_client_id',
        hiddenName: 'referral_client_id',
        store: ds,
        tpl: resultTpl,
        valueField: 'clientId',
        displayField: 'clientFullName',
        forceSelection: true,
        itemSelector: 'div.x-combo-list-item',
        triggerClass: 'x-form-search-trigger',
        listClass: 'no-pointer',
        typeAhead: false,
        selectOnFocus: true,
        allowBlank: false,
        pageSize: 0,
        minChars: 2,
        queryDelay: 750,
        width: 400,
        listWidth: 389,
        doNotAutoResizeList: true,

        listeners: {
            afterrender: function (combo) {
                if (!empty(config.oReferral.referral_id)) {
                    var arrData = [
                        {
                            clientId: config.oReferral.referral_client_id,
                            clientType: config.oReferral.referral_client_type,
                            clientFullName: config.oReferral.referral_client_name
                        }
                    ];

                    ds.loadData({
                        success: true,
                        msg: '',
                        query: '',
                        rows: arrData,
                        totalCount: arrData.length
                    });

                    combo.setValue(config.oReferral.referral_client_id);
                }
            },

            beforeselect: function (combo, record) {
                thisWindow.referralClientType.setValue(record.data.clientType);
            }
        }
    });

    this.compensationAgreementField = new Ext.form.ComboBox({
        fieldLabel: _('Compensation Agreement'),
        name: 'referral_compensation_arrangement',
        allowBlank: false,
        store: new Ext.data.SimpleStore({
            fields: ['compensation_arrangement'],
            data: config.arrCompensationAgreements
        }),
        mode: 'local',
        displayField: 'compensation_arrangement',
        valueField: 'compensation_arrangement',
        typeAhead: true,
        triggerAction: 'all',
        emptyText: config.arrCompensationAgreements.length ? _('Type or select from the list...') : _('Please type the Compensation Agreement...'),
        value: empty(config.oReferral.referral_id) ? undefined : config.oReferral.referral_compensation_arrangement,
        width: 400
    });

    this.isPaidCheckbox = new Ext.form.Checkbox({
        name: 'referral_is_paid',
        boxLabel: _('Paid'),
        checked: empty(config.oReferral.referral_id) ? false : config.oReferral.referral_is_paid === 'Y',
    });

    this.fieldsForm = new Ext.form.FormPanel({
        frame: false,
        bodyStyle: 'padding: 5px',
        labelWidth: 180,
        items: [
            this.applicantId,
            this.referralId,
            this.referralClientType,
            this.referralsCombo,
            this.compensationAgreementField,
            this.isPaidCheckbox
        ]
    });

    ApplicantsProfileClientReferralsDialog.superclass.constructor.call(this, {
        title: empty(config.oReferral.referral_id) ? '<i class="las la-user-plus"></i>' + _('Add Referral') : '<i class="las la-user-edit"></i>' + _('Edit Referral'),
        layout: 'form',
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        modal: true,
        items: this.fieldsForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.saveReferralChanges.createDelegate(this)
            }
        ]
    });

    thisWindow.on('show', function () {
        thisWindow.referralsCombo.clearInvalid();
        thisWindow.compensationAgreementField.clearInvalid();
    });
};

Ext.extend(ApplicantsProfileClientReferralsDialog, Ext.Window, {
    saveReferralChanges: function () {
        var thisDialog = this;

        if (thisDialog.fieldsForm.getForm().isValid()) {
            thisDialog.getEl().mask(_('Saving...'));

            thisDialog.fieldsForm.getForm().submit({
                url: topBaseUrl + '/applicants/profile/save-client-referral',

                success: function (form, action) {
                    thisDialog.owner.store.reload();

                    thisDialog.getEl().mask(empty(action.result.message) ? _('Done!') : action.result.message);
                    setTimeout(function () {
                        thisDialog.close();
                    }, 750);
                },

                failure: function (form, action) {
                    var msg = empty(action.result.message) ? _('Cannot save info.') : action.result.message;
                    Ext.simpleConfirmation.error(msg);
                    thisDialog.getEl().unmask();
                }
            });
        }
    }
});