// create namespace
Ext.ns('Ext.ux.form');

Ext.ux.form.MultipleTextFields = function (config) {
    // call parent constructor
    Ext.ux.form.MultipleTextFields.superclass.constructor.call(this, config);
};

Ext.extend(Ext.ux.form.MultipleTextFields, Ext.form.Field, {
    defaultAutoCreate: {tag: "div"},

    /**
     * @cfg {String} fieldClass The default CSS class for the field (defaults to <tt>"x-form-multiple-text-fields"</tt>)
     */
    fieldClass: "x-form-multiple-text-fields",

    /**
     * @cfg {String} addButtonText The button text to display on the Add button (defaults to
     * 'Add...').  Note that if you supply a value for {@link #addButtonCfg}, the addButtonCfg.text
     * value will be used instead if available.
     */
    addButtonText: 'Add...',

    /**
     * @cfg {String} deleteButtonText The button text to display on the Delete button (defaults to
     * 'Delete').  Note that if you supply a value for {@link #deleteButtonCfg}, the deleteButtonCfg.text
     * value will be used instead if available.
     */
    deleteButtonText: 'Delete',

    /**
     * @cfg {Int} maxAllowedRowsCount The maximum number of fields that can be added (defaults to 0).
     * Note that if 0 is passed - there is no limit
     */
    maxAllowedRowsCount: 0,

    // private
    currentRowsCount: 0,

    // private
    initEvents: Ext.emptyFn,

    // private
    fieldName: '',

    // private
    onRender: function (ct, position) {
        Ext.ux.form.MultipleTextFields.superclass.onRender.call(this, ct, position);

        // Field name must be used as array
        this.fieldName = this.el.dom.getAttribute('name');
        if (this.fieldName) {
            this.fieldName = this.fieldName.replace(/\[\]$/, '');
        }
        this.el.dom.removeAttribute('name');

        var elp = this.el.findParent('.x-form-element', 5, true);

        var fieldsContainer = ct.createChild({tag: 'div'});
        fieldsContainer.setWidth(elp.getWidth(true));

        this.fieldsPanel = new Ext.Panel({
            renderTo: fieldsContainer,
            items: []
        });

        this.elWidth = elp.getWidth(true);
        var btnContainer = ct.createChild({tag: 'div', 'style': 'padding-top: 5px'});
        btnContainer.setWidth(this.elWidth);

        var btnCfg = Ext.applyIf(this.addButtonCfg || {}, {
            text: this.addButtonText
        });

        this.addButton = new Ext.Button(Ext.apply(btnCfg, {
            renderTo: btnContainer,
            hidden: this.disabled
        }));

        this.addButton.on('click', this.createNewFieldRow, this, false);

    },

    afterRender: function (ct, position) {
        Ext.ux.form.MultipleTextFields.superclass.afterRender.call(this, ct, position);

        // If value was passed - use it (create and fill the fields)
        this.setValue(this.value || '[]');
    },

    setValue: function (strValues) {
        var arrValues = [];

        try {
            arrValues = Ext.decode(strValues);
        } catch (e) {
        }

        // Set fields values, if field is not added yet - add it
        for (var i = 0; i < arrValues.length; i++) {
            if (i > this.maxAllowedRowsCount && this.maxAllowedRowsCount > 0) {
                break;
            }

            var panel = this.fieldsPanel.items.itemAt(i);
            if (typeof panel == 'undefined') {
                this.createNewFieldRow();
                panel = this.fieldsPanel.items.itemAt(i);
            }

            if (panel) {
                panel.items.itemAt(0).setValue(arrValues[i]);
            }
        }

        // If there are too much of fields - remove them
        var currentCount = this.fieldsPanel.items.getCount();
        var correctCount = this.maxAllowedRowsCount > 0 ? Math.min(this.maxAllowedRowsCount, arrValues.length) : arrValues.length;
        while (correctCount < currentCount) {
            this.deleteFieldRow(this.fieldsPanel.items.last().getId());
            currentCount--;
        }

        return this;
    },

    getValue: function () {
        var arrValues = [];
        this.fieldsPanel.items.each(function (panel) {
            arrValues.push(panel.items.itemAt(0).getValue());
        });

        return Ext.encode(arrValues);
    },

    /**
     * Validates the field value
     * @return {Boolean} True if the value is valid, else false
     */
    validate: function () {
        var booValid = this.isValid();
        if (booValid) {
            this.clearInvalid();
        }
        return booValid;
    },

    isValid: function (preventMark) {
        if (this.disabled) {
            return true;
        }

        this.preventMark = preventMark === true;

        var v = true;
        var booThereAreFields = false;
        this.fieldsPanel.items.each(function (panel) {
            if (panel.items.getCount()) {
                booThereAreFields = true;
                if (!panel.items.itemAt(0).isValid(preventMark)) {
                    v = false;
                }
            }
        });

        // If there are no any fields and this is a required field - this field is invalid
        if(!booThereAreFields && !this.allowBlank) {
            v = false;
        }

        if (!v) {
            this.markInvalid();
        }

        return v;
    },

    // This method is used when we check if form is valid and can be submitted
    markInvalid : function(){
        if (this.rendered && !this.preventMark) {
            this.el.addClass(this.invalidClass);
        }
    },

    createNewFieldRow: function () {
        var thisRowId = Ext.id();

        var deleteBtnCfg = Ext.applyIf(this.deleteButtonCfg || {}, {
            text: this.deleteButtonText
        });
        var deleteButton = new Ext.Button(Ext.apply(deleteBtnCfg, {
            hidden: this.disabled
        }));
        deleteButton.on('click', this.deleteFieldRow.createDelegate(this, [thisRowId]), this);

        this.fieldsPanel.add({
            id: thisRowId,
            xtype: 'panel',
            bodyStyle: 'padding-top: 5px',
            layout: this.disabled ? '' : 'column',
            items: [
                {
                    xtype: 'textfield',
                    name: this.fieldName == '' ? '' : this.fieldName + '[]',
                    allowBlank: typeof this.allowBlank == 'undefined' ? true : this.allowBlank,
                    disabled: this.disabled,
                    style: this.disabled ? '' : 'margin-right: 5px',
                    width: this.disabled ? this.getWidth() : this.getWidth() - deleteBtnCfg.width
                }, deleteButton
            ]
        });

        this.fieldsPanel.doLayout();
        this.currentRowsCount += 1;

        this.toggleAddButton();
    },

    deleteFieldRow: function (rowId) {
        this.currentRowsCount -= 1;

        var panel = Ext.getCmp(rowId);
        panel.hide();
        panel.removeAll();
        this.fieldsPanel.doLayout();

        this.toggleAddButton();
    },

    toggleAddButton: function () {
        var booEnableButton = true;
        if (this.disabled) {
            booEnableButton = false;
        } else if (this.maxAllowedRowsCount > 0 && this.currentRowsCount >= this.maxAllowedRowsCount) {
            booEnableButton = false;
        }
        this.addButton.setDisabled(!booEnableButton);
    }
});

Ext.reg('multipletextfields', Ext.ux.form.MultipleTextFields);