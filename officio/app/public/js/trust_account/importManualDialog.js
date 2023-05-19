var ImportManualDialog = function(config) {
    var thisWindow = this;
    Ext.apply(this, config);
    this.taGrid = Ext.getCmp('editor-grid' + config.ta_id);

    this.currentTABalance = parseFloat(this.taGrid.store.reader.jsonData.balance);
    this.lastTransactionDate = this.taGrid.store.reader.jsonData.lastTransactionDate;

    var formattedLastTransactionDate = null;

    if (!empty(this.lastTransactionDate)) {
        var lastTransactionDate = this.lastTransactionDate.split('-');
        formattedLastTransactionDate = new Date();
        formattedLastTransactionDate.setUTCFullYear(lastTransactionDate[0], lastTransactionDate[1]-1, lastTransactionDate[2]);
        formattedLastTransactionDate.setUTCHours(0, 0, 0, 0);
    }

    this.sm = new Ext.grid.CheckboxSelectionModel();

    var TARecord = Ext.data.Record.create([
        {
            name: 'rec_date',
            type: 'date',
            dateFormat: 'n/j/Y'
        }, {
            name: 'rec_description',
            type: 'string'
        }, {
            name: 'rec_withdrawal',
            type: 'float'
        }, {
            name: 'rec_deposit',
            type: 'float'
        }
    ]);

    this.grid = new Ext.grid.EditorGridPanel({
        autoWidth: true,
        height: 300,
        stripeRows: true,
        clicksToEdit: 1,
        border: false,
        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText: 'Please add records',
            forceFit: true
        },

        store: new Ext.data.SimpleStore({
            fields: TARecord,
            data: []
        }),

        tbar: {
            xtype: 'toolbar',
            cls: 'no-bbar-borders',
            items: [{
                text: '<i class="las la-plus"></i>' + _('Add Record'),
                handler: function () {
                    var recCount = thisWindow.grid.store.getCount();
                    var newRec = new TARecord();
                    thisWindow.grid.stopEditing();
                    thisWindow.grid.store.insert(recCount, newRec);
                    thisWindow.grid.startEditing(recCount, 1);
                }
            }, '-', {
                ref: '../removeBtn',
                text: '<i class="las la-trash"></i>' + _('Remove Record'),
                disabled: true,
                handler: function () {
                    thisWindow.grid.stopEditing();
                    var s = thisWindow.grid.getSelectionModel().getSelections();
                    for (var i = 0, r; r = s[i]; i++) {
                        thisWindow.grid.store.remove(r);
                    }
                }
            }]
        },

        sm: this.sm,
        colModel: new Ext.grid.ColumnModel({
            defaults: {
                width: 120,
                sortable: false
            },
            columns: [
                this.sm,
                {
                    header: _('Date'),
                    width: 140,
                    dataIndex: 'rec_date',
                    renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                    fixed: true,
                    editor: new Ext.form.DateField({
                        hideLabel: true,
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        defaultValue: formattedLastTransactionDate > new Date() ? formattedLastTransactionDate : new Date(),
                        maxLength: 12, // Fix issue with date entering in 'full' format
                        minValue: formattedLastTransactionDate,
                        listeners : {
                            'blur': function() {
                                if (this.minValue) {
                                    this.minValue.setHours(0, 0, 0, 0);
                                    if (this.minValue > new Date(this.getValue())) {
                                        Ext.simpleConfirmation.msg('Warning', 'The date in this field must be after ' + this.minValue.format('d M Y'), 3000);
                                    }
                                }
                            }
                        }
                    })

                }, {
                    header: _('Description'),
                    width: 200,
                    dataIndex: 'rec_description',
                    editor: new Ext.form.TextField()
                }, {
                    header: _('Deposit'),
                    width: 120,
                    fixed: true,
                    dataIndex: 'rec_deposit',
                    editor: {
                        xtype: 'numberfield',
                        allowNegative: false,
                        decimalPrecision: 2
                    },
                    renderer: function (val) {
                        return formatMoney(thisWindow.ta_currency, val, false);
                    }
                }, {
                    header: _('Withdrawal'),
                    width: 120,
                    fixed: true,
                    dataIndex: 'rec_withdrawal',
                    editor: {
                        xtype: 'numberfield',
                        allowNegative: false,
                        decimalPrecision: 2
                    },
                    renderer: function (val) {
                        return formatMoney(thisWindow.ta_currency, val, false);
                    }
                }
            ]
        }),

        bbar: {
            xtype: 'toolbar',
            cls: 'no-bbar-borders',
            items: [
                {
                    xtype: 'label',
                    html: 'Current balance in T/A:'
                }, {
                    xtype: 'displayfield',
                    style: 'padding-left: 5px; font-size: 14px',
                    value: formatMoney(thisWindow.ta_currency, this.currentTABalance, true)
                }, {
                    xtype: 'label',
                    style: 'padding-left: 30px;',
                    html: 'Balance with new records:'
                }, {
                    id:    'new-trust-account-balance' + thisWindow.ta_id,
                    xtype: 'displayfield',
                    style: 'padding-left: 5px; font-size: 14px',
                    value: formatMoney(thisWindow.ta_currency, this.currentTABalance, true)
                }
            ]
        }
    });

    this.grid.getSelectionModel().on('selectionchange', function(sm){
        thisWindow.grid.removeBtn.setDisabled(sm.getCount() < 1);
    });

    this.grid.on('afteredit', this.calculateNewBalance.createDelegate(this), this);

    ImportManualDialog.superclass.constructor.call(this, {
        title: _('Manual import'),
        closeAction: 'close',
        modal: true,
        autoHeight: true,
        resizable: false,
        width: 700,
        plain: true,
        border: false,
        items: this.grid,
        buttons: [
            {
                text: _('Cancel'),
                handler: this.close.createDelegate(this)
            },
            {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.saveNewChanges.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ImportManualDialog, Ext.Window, {
    isBlank: function (str){
        return !str || !/[^\s]+/.test(str)
    },

    highlightEl: function(fieldId) {
        // Prevent highlight several times
        var field = Ext.get(fieldId);
        if (field && !field.hasActiveFx()) {
            field.highlight('FF8432', { attr: 'color', duration: 2 });
        }
    },

    calculateNewBalance: function() {
        var thisWindow = this;
        var balanceFieldId = 'new-trust-account-balance' + thisWindow.ta_id;
        var newBalanceField = Ext.getCmp(balanceFieldId);

        var newBalance = this.currentTABalance;
        thisWindow.grid.store.each(function(rec) {
            newBalance -= rec.data.rec_withdrawal == undefined || empty(rec.data.rec_withdrawal) ? 0 : parseFloat(rec.data.rec_withdrawal);
            newBalance += rec.data.rec_deposit == undefined || empty(rec.data.rec_deposit) ? 0 : parseFloat(rec.data.rec_deposit);
        });

        var newBalanceFormatted = formatMoney(thisWindow.ta_currency, newBalance, true);
        var oldBalanceFormatted = newBalanceField.getValue();

        newBalanceField.setValue(newBalanceFormatted);
        if (newBalanceFormatted != oldBalanceFormatted) {
            thisWindow.highlightEl(balanceFieldId);
        }
    },

    saveNewChanges: function() {
        var thisWindow = this;
        var strErrorMessage = thisWindow.grid.store.getCount() ? '' : _('Please add at least one record.');

        if (empty(strErrorMessage)) {
            // Check withdrawal/deposit columns
            thisWindow.grid.store.each(function(rec) {
                var colHighlight;
                if(empty(rec.data.rec_date)) {
                    colHighlight = 1;
                    strErrorMessage = _('Please specify date for the record.');
                }

                if(empty(strErrorMessage) && thisWindow.isBlank(rec.data.rec_description)) {
                    colHighlight = 2;
                    strErrorMessage = _('Please enter description for the record.');
                }

                if(empty(strErrorMessage) && empty(rec.data.rec_withdrawal) && empty(rec.data.rec_deposit)) {
                    colHighlight = 3;
                    strErrorMessage = _('Please specify deposit or withdrawal for the record.');
                }

                if(empty(strErrorMessage) && !empty(rec.data.rec_withdrawal) && !empty(rec.data.rec_deposit)) {
                    colHighlight = 3;
                    strErrorMessage = _('Please specify ONLY deposit or withdrawal for the record.');
                }

                if (!empty(strErrorMessage)) {
                    var index = this.store.indexOf(rec);
                    thisWindow.grid.stopEditing();
                    thisWindow.grid.startEditing(index, colHighlight);
                }

                return empty(strErrorMessage);
            });
        }

        if (empty(strErrorMessage)) {
            this.saveNewTransactions();
        } else {
            thisWindow.grid.getEl().mask(
                String.format('<span style="color: red">{0}</span>', strErrorMessage)
            );

            setTimeout(function () {
                thisWindow.grid.getEl().unmask();
            }, 2000);
        }
    },

    saveNewTransactions: function() {
        var thisWindow = this;
        var arrNewTransactions = [];
        thisWindow.grid.store.each(function(rec) {
            rec.data.rec_date = Ext.util.Format.date(rec.data.rec_date, Date.patterns.ISO8601Short);
            arrNewTransactions.push(rec.data);
        });

        thisWindow.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: baseUrl + "/trust-account/import/add-manual-transactions",
            params: {
                ta_id: thisWindow.ta_id,
                ta_records: Ext.encode(arrNewTransactions)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    thisWindow.close();
                    Ext.simpleConfirmation.msg('Info', _('Information was saved successfully.'));

                    if (thisWindow.taGrid) {
                        thisWindow.taGrid.store.reload();
                    }
                } else {
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.msg);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot save information. PLease try again later.'));
            }
        });
    }
});