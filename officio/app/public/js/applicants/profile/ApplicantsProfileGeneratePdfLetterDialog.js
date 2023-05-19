var ApplicantsProfileGeneratePdfLetterDialog = function (config, owner) {
    var thisDialog = this;
    this.owner     = owner;
    Ext.apply(this, config);

    this.buttons = [
        {
            text:     '<i class="las la-file-pdf"></i>' + _('Generate PDF'),
            ref:      '../generatePdfBtn',
            disabled: true,
            handler:  this.submitData.createDelegate(this)
        }, {
            text:    _('Close'),
            scope:   this,
            handler: function () {
                this.close();
            }
        }
    ];

    // @Note: at this point, we support only "comfort_letter"
    // But later we can support more types, so we can change this field to the combobox
    this.templatesType = new Ext.form.Hidden({
        value: 'comfort_letter'
    });

    var templatesComboStore = new Ext.data.Store({
        autoLoad: true,
        proxy:    new Ext.data.HttpProxy({
            url: baseUrl + '/applicants/profile/get-letter-templates-by-type'
        }),

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount',
            idProperty:    'templateId',

            fields: [
                'templateId',
                'templateName'
            ]
        })
    });
    templatesComboStore.on('beforeload', function (store, options) {
        options.params = options.params || {};

        var params = {
            'templateType': Ext.encode(thisDialog.templatesType.getValue())
        };

        Ext.apply(options.params, params);
    });
    templatesComboStore.on('load', this.checkLoadedResult.createDelegate(this));
    templatesComboStore.on('loadexception', this.checkLoadedResult.createDelegate(this));


    this.templatesCombo = new Ext.form.ComboBox({
        fieldLabel:     _('Please select the template of your choice'),
        emptyText:      _('Please select...'),
        store:          templatesComboStore,
        mode:           'local',
        displayField:   'templateName',
        valueField:     'templateId',
        triggerAction:  'all',
        forceSelection: true,
        selectOnFocus:  true,
        editable:       true,
        width:          250,

        listeners: {
            'beforeselect': function () {
                thisDialog.generatePdfBtn.setDisabled(false);
            },

            'change': function (combo, newValue) {
                thisDialog.generatePdfBtn.setDisabled(empty(newValue));
            }
        }
    });

    var form = new Ext.form.FormPanel({
        bodyStyle:  'padding: 20px',
        labelWidth: 65,
        labelAlign: 'top',

        items: [
            this.templatesType,
            this.templatesCombo
        ]
    });

    ApplicantsProfileGeneratePdfLetterDialog.superclass.constructor.call(this, {
        title:   '<i class="las la-book"></i>' + _('Generate Comfort Letter'),

        y:           10,
        autoWidth:   true,
        autoHeight:  true,
        closeAction: 'close',
        plain:       false,
        modal:       true,
        buttonAlign: 'center',
        items:       form
    });
};

Ext.extend(ApplicantsProfileGeneratePdfLetterDialog, Ext.Window, {
    checkLoadedResult: function () {
        var store = this.templatesCombo.store;
        if (store.reader.jsonData && store.reader.jsonData.msg && !store.reader.jsonData.success) {
            Ext.simpleConfirmation.error(store.reader.jsonData.msg);
            this.close();
        }

        if (store.getCount() === 1) {
            var rec   = store.getAt(0);
            var value = rec.data[this.templatesCombo.valueField];

            this.templatesCombo.setValue(value);
            this.templatesCombo.fireEvent('beforeselect', this.templatesCombo, rec, value);
        }
    },

    submitData: function () {
        var win = this;

        var templateId = win.templatesCombo.getValue();
        if (empty(templateId)) {
            Ext.simpleConfirmation.warning(_('Please select the template.'));
            return;
        }

        win.getEl().mask(_('Generating, please wait...'));
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/generate-pdf-letter',

            params: {
                caseId:       Ext.encode(win.caseId),
                templateId:   Ext.encode(templateId),
                templateType: Ext.encode(win.templatesType.getValue())
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    Ext.simpleConfirmation.info(_('PDF file was saved in the Correspondence folder.'));
                    win.close();
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                    win.getEl().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('PDF file was not generated. Please try again later.'));
                win.getEl().unmask();
            }
        });
    }
});
