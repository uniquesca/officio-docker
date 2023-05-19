var AssignAccountDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    thisWindow.TAMainStore = new Ext.data.SimpleStore({
        fields: ['ta_id', 'ta_name', 'ta_currency'],
        data: thisWindow.getFilteredTrustAccounts(thisWindow.primaryCurrency),
        id: 0 // Fix with findById
    });

    thisWindow.TASecondaryStore = new Ext.data.SimpleStore({
        fields: ['ta_id', 'ta_name', 'ta_currency'],
        data: thisWindow.getFilteredTrustAccounts(thisWindow.secondaryTACurrency),
        id: 0 // Fix with findById
    });

    var currenciesStore = new Ext.data.SimpleStore({
        fields: ['currency_id', 'currency_label'],
        data: arrApplicantsSettings.accounting.arrCurrencies
    });

    this.formPanel = new Ext.FormPanel({
        labelWidth: 420,
        defaults: {
            msgTarget: 'side'
        },

        items: [
            {
                fieldLabel: _('Specify the currency quoted in your agreement with this case'),
                emptyText: _('Please select...'),
                ref: '../primaryTACurrency',
                xtype: 'combo',
                store: currenciesStore,
                valueField: 'currency_id',
                displayField: 'currency_label',
                editable: false,
                typeAhead: false,
                mode: 'local',
                triggerAction: 'all',
                selectOnFocus: false,
                width: 240,
                value: empty(thisWindow.primaryCurrency) ? '' : thisWindow.primaryCurrency,

                listeners: {
                    beforeselect: function (n, selRecord) {
                        thisWindow.updateTAAccountsFromCurrency(true, selRecord.data.currency_id);
                    }
                }
            }, {
                fieldLabel: String.format(
                    _('{0} to receive fees'),
                    arrApplicantsSettings.ta_label
                ),
                emptyText: _('Please select...'),
                ref: '../primaryTAAccount',
                xtype: 'combo',
                readOnly: true,
                store: thisWindow.TAMainStore,
                valueField: 'ta_id',
                displayField: 'ta_name',
                editable: false,
                typeAhead: false,
                mode: 'local',
                triggerAction: 'all',
                selectOnFocus: false,
                value: empty(thisWindow.primaryTAId) ? '' : thisWindow.primaryTAId,
                width: 420,
                listWidth: 420
            }, {
                xtype: 'radiogroup',
                fieldLabel: String.format(
                    _('Do you want to add a second {0} to this case?'),
                    arrApplicantsSettings.ta_label
                ),
                labelSeparator: '',
                labelStyle: 'width: 420px; padding-top: 5px',
                ref: '../useAnotherTARadio',
                allowBlank: false,
                width: 150,
                preventMark: false,
                items: [
                    {boxLabel: _('No'), name: 'assign-radio-show-other', inputValue: 'no', checked: empty(thisWindow.secondaryTAId)},
                    {boxLabel: _('Yes'), name: 'assign-radio-show-other', inputValue: 'yes', checked: !empty(thisWindow.secondaryTAId)}
                ],

                listeners: {
                    change: function (thisRadioGroup) {
                        var booShow = thisRadioGroup.getValue().getGroupValue() == 'yes';
                        thisWindow.secondaryTACurrency.setVisible(booShow);
                        thisWindow.secondaryTAAccount.setVisible(booShow);

                        // Automatically switch a secondary currency to the site's default one if the primary one isn't site's default too
                        if (booShow && empty(thisWindow.secondaryTACurrency.getValue()) && thisWindow.primaryTACurrency.getValue() !== site_currency) {
                            thisWindow.secondaryTACurrency.setValue(site_currency);
                            thisWindow.updateTAAccountsFromCurrency(false, site_currency);
                        }
                    }
                }
            }, {
                fieldLabel: String.format(
                    _('Currency of {0}'),
                    arrApplicantsSettings.ta_label
                ),
                emptyText: _('Please select...'),
                ref: '../secondaryTACurrency',
                xtype: 'combo',
                store: currenciesStore,
                valueField: 'currency_id',
                displayField: 'currency_label',
                editable: false,
                typeAhead: false,
                mode: 'local',
                triggerAction: 'all',
                selectOnFocus: false,
                value: empty(thisWindow.secondaryTACurrency) ? '' : thisWindow.secondaryTACurrency,
                width: 240,

                listeners: {
                    beforeselect: function (n, selRecord) {
                        thisWindow.updateTAAccountsFromCurrency(false, selRecord.data.currency_id);
                    }
                }
            }, {
                fieldLabel: String.format(
                    _('{0} to receive fees'),
                    arrApplicantsSettings.ta_label
                ),
                emptyText: _('Please select...'),
                ref: '../secondaryTAAccount',
                xtype: 'combo',
                readOnly: true,
                store: thisWindow.TASecondaryStore,
                valueField: 'ta_id',
                displayField: 'ta_name',
                editable: false,
                typeAhead: false,
                mode: 'local',
                triggerAction: 'all',
                selectOnFocus: false,
                value: empty(thisWindow.secondaryTAId) ? '' : thisWindow.secondaryTAId,
                width: 420,
                listWidth: 420
            }, {
                xtype: 'label',
                ref: '../warningTAMessage',
                html: String.format(
                    _('<i>Once a Currency type and {0} is selected for a case, it cannot be changed.<br>If this information is accurate, click Save to continue.</i>'),
                    arrApplicantsSettings.ta_label
                )
            }
        ]
    });

    this.cancelButton = new Ext.Button({
        text: _('Cancel'),
        handler: function () {
            thisWindow.close();
        }
    });

    this.assignAccountButton = new Ext.Button({
        text: _('Save'),
        cls: 'orange-btn',
        handler: this.assignCaseAccount.createDelegate(thisWindow)
    });

    AssignAccountDialog.superclass.constructor.call(this, {
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,
        items: this.formPanel,

        buttons: [
            this.cancelButton,
            this.assignAccountButton
        ],

        listeners: {
            show: function () {
                thisWindow.useAnotherTARadio.fireEvent('change', thisWindow.useAnotherTARadio);

                // Disable combos/radios if T/A cannot be changed
                var booAtLeastOneCanBeChanged = false;
                for (var i = 0; i < thisWindow.switchTAMode.length; i++) {
                    if (!thisWindow.switchTAMode[i]['can_change']) {
                        if (empty(i)) {
                            thisWindow.primaryTACurrency.setDisabled(true);
                            thisWindow.primaryTAAccount.setDisabled(true);
                        } else {
                            $('#' + thisWindow.useAnotherTARadio.container.id).addClass('x-item-disabled');
                            thisWindow.useAnotherTARadio.setDisabled(true);
                            thisWindow.secondaryTACurrency.setDisabled(true);
                            thisWindow.secondaryTAAccount.setDisabled(true);
                        }
                    } else {
                        booAtLeastOneCanBeChanged = true;
                    }
                }

                // If no changes allowed - show another warning message
                if (thisWindow.switchTAMode.length == 2 && !booAtLeastOneCanBeChanged) {
                    Ext.getDom(thisWindow.warningTAMessage.id).innerHTML = String.format(
                        _('Your {0} is associated to at least one transaction and cannot be changed unless you delete those payments first.'),
                        arrApplicantsSettings.ta_label
                    );
                    thisWindow.assignAccountButton.setVisible(false);
                    thisWindow.cancelButton.setText(_('Close'));
                }
            }
        }
    });
};

Ext.extend(AssignAccountDialog, Ext.Window, {
    getFilteredTrustAccounts: function (currencyId) {
        var thisWindow = this;

        var arrCompanyTAWithoutEmpty = [];
        for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
            if (!empty(arrApplicantsSettings.accounting.arrCompanyTA[i][0])) {
                arrCompanyTAWithoutEmpty[arrCompanyTAWithoutEmpty.length] = arrApplicantsSettings.accounting.arrCompanyTA[i];
            }
        }


        var arrResult = [];
        if (empty(currencyId)) {
            arrResult = arrCompanyTAWithoutEmpty;
        } else {
            for (var i = 0; i < arrCompanyTAWithoutEmpty.length; i++) {
                if (arrCompanyTAWithoutEmpty[i][2] == currencyId) {
                    arrResult[arrResult.length] = arrCompanyTAWithoutEmpty[i];
                }
            }
        }

        return arrResult;
    },

    updateTAAccountsFromCurrency: function (booPrimaryTACombo, selCurrency) {
        var thisWindow = this;
        var arrUpdatedList = thisWindow.getFilteredTrustAccounts(selCurrency);
        if (booPrimaryTACombo) {
            thisWindow.TAMainStore.loadData(arrUpdatedList);

            if (arrUpdatedList.length === 1) {
                thisWindow.primaryTAAccount.setValue(arrUpdatedList[0][0]);
            } else {
                thisWindow.primaryTAAccount.setValue('');
            }
        } else {
            thisWindow.TASecondaryStore.loadData(arrUpdatedList);

            if (arrUpdatedList.length === 1) {
                thisWindow.secondaryTAAccount.setValue(arrUpdatedList[0][0]);
            } else {
                thisWindow.secondaryTAAccount.setValue('');
            }
        }
    },

    sendChangeTARequest: function (params) {
        var thisWindow = this;
        thisWindow.getEl().mask('Saving...');

        // Save changes
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/manage-ta',
            params: params,
            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisWindow.getEl().mask('Done!');

                    // Refresh the whole tab
                    refreshSettings('accounting');
                    thisWindow.owner.refreshAccountingTab();

                    setTimeout(function () {
                        thisWindow.close();
                    }, 750);
                } else {
                    // Show error message
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }

            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error('Cannot save changes');
            }
        });
    },

    assignCaseAccount: function () {
        var thisWindow = this;

        // Check params
        var primaryTACurrency = thisWindow.primaryTACurrency.getValue();
        var primarySelectedTA = thisWindow.primaryTAAccount.getValue();
        var secondarySelectedTA = thisWindow.secondaryTAAccount.getValue();
        var secondaryTACurrency = thisWindow.secondaryTACurrency.getValue();
        var booIsRadioYesChecked = thisWindow.useAnotherTARadio.getValue().getGroupValue() == 'yes';

        if (empty(primaryTACurrency)) {
            thisWindow.primaryTACurrency.markInvalid(_('Please select a currency'));
            return false;
        }

        if (empty(primarySelectedTA)) {
            Ext.simpleConfirmation.error(
                String.format(
                    _('Please select a primary {0}'),
                    arrApplicantsSettings.ta_label
                )
            );
            return false;
        }

        if (booIsRadioYesChecked) {
            if (empty(secondaryTACurrency)) {
                thisWindow.secondaryTACurrency.markInvalid(_('Please select a currency'));
                return false;
            }

            // Two selected T/A
            if (empty(secondarySelectedTA)) {
                Ext.simpleConfirmation.error(
                    String.format(
                        _('Please select a secondary {0}'),
                        arrApplicantsSettings.ta_label
                    )
                );
                return false;
            }
        } else {
            secondarySelectedTA = 0;
        }

        if (secondarySelectedTA == primarySelectedTA) {
            Ext.simpleConfirmation.error(
                String.format(
                    _('This {0} is already selected as a primary one'),
                    arrApplicantsSettings.ta_label
                )
            );
            return false;
        }

        if (primarySelectedTA == thisWindow.primaryTAId && secondarySelectedTA == thisWindow.secondaryTAId) {
            // Nothing to save
            thisWindow.close();
            return false;
        }

        var params = {
            member_id: Ext.encode(thisWindow.caseId),
            primary_ta_id: Ext.encode(primarySelectedTA),
            secondary_ta_id: Ext.encode(secondarySelectedTA)
        };

        var booAsk = false;
        if ((!empty(thisWindow.primaryTAId) && thisWindow.primaryTAId != primarySelectedTA) || (!empty(thisWindow.secondaryTAId) && thisWindow.secondaryTAId != secondarySelectedTA)) {
            var question = String.format(
                _('If you change the {0} or the Currency, all entries in Fees & Disbursements, and Invoices will be erased.<br/><br/> Are you sure you want to proceed?'),
                arrApplicantsSettings.ta_label
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn, text) {
                if (btn == 'yes') {
                    thisWindow.sendChangeTARequest(params);
                }
            });
        } else {
            // That's mean that this is a first time or we just added a new T/A -> don't ask for a confirmation
            thisWindow.sendChangeTARequest(params);
        }
    }
});