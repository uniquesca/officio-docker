var submitUrl = baseUrl + '/trust-account-settings';

var Transaction = Ext.data.Record.create([
    {name: 'transactionId', type: 'int'},
    {name: 'transactionName', type: 'string'},
    {name: 'transactionOrder', type: 'int'},
    {name: 'transactionLocked', type: 'string'}
]);

var type_store = new Ext.data.Store({
    url: submitUrl + "/get-type-options",
    baseParams: {
        withoutOther: true
    },
    
    // the return will be Json, so lets set up a reader
    reader: new Ext.data.JsonReader(
    {
        root:'rows',
        totalProperty:'totalCount'
    }, Transaction),
    
    listeners: {
        load: function(){
            ta_type_grid.getSelectionModel().selectFirstRow();
        }
    }
});

var isOptionSelected = function() {
    if(Ext.getCmp('ta_type').getValue() === '') {
        Ext.simpleConfirmation.msg(_('Info'), _('Please select an option and try again'));
        return false;
    }
    
    return true;
};

function title_img(val){
    return  String.format(
        _('Place <i>{0}</i> here'),
        val
    );
}

var selectedRow = 0;
function moveOption(booUp) {
    var sm = ta_type_grid.getSelectionModel();
    var last_selected = sm.last;
    var rows = sm.getSelections();
    
    // Move option only if:
    // 1. It is not the first, if moving up
    // 2. It is not the last, if moving down
    var booMove = false;
    if(type_store.getCount() > 0) {
        var cindex;
        if(booUp && selectedRow > 0) {
            cindex = selectedRow - 1;
            booMove = true;
        } else if(!booUp && selectedRow < type_store.getCount()-1) {
            cindex = selectedRow + 1;
            booMove = true;
        }
    }
    
    if (sm.hasSelection() && booMove) {
        for (i = 0; i < rows.length; i++) {
            type_store.remove(type_store.getById(rows[i].id));
            type_store.insert(cindex,rows[i]);
        }

        // Update order for each of transaction
        for (i = 0; i < type_store.getCount(); i++) {
            rec = type_store.getAt(i);
            
            if(rec.data.transactionOrder != i) {
                rec.beginEdit();
                rec.set("transactionOrder", i);
                
                // Mark as dirty
                var oldName = rec.data.transactionName;
                rec.set("transactionName", oldName + ' ');
                rec.set("transactionName", oldName);
                rec.endEdit();
            }
        }
        
        var movedRow = type_store.getAt(cindex);
        sm.selectRecords(movedRow);
    }

    if (booUp){
      last_selected = last_selected - 1;
      if (Ext.isIE){
        sm.selectRow(last_selected);
      }
      else{
        sm.selectPrevious.defer(1, sm);
      }
    }
    else{
      last_selected = last_selected + 1;
      if (Ext.isIE){
        sm.selectRow(last_selected);
      }
      else{
        sm.selectNext.defer(1, sm);
      }
    }
}

var action = new Ext.ux.grid.RowActions({
    header: _('Order'),
    keepSelection: true,
    widthSlope: 32,

    actions:[{
        iconCls:'move_option_up',
        tooltip:_('Move option Up')
    }, {
        iconCls:'move_option_down',
        tooltip:_('Move option Down')
    }],
    
    callbacks:{
        'move_option_up':function() {
            moveOption(true);
        },
        'move_option_down':function() {
            moveOption(false);
        }
    }
});

// create the editor grid
var autoExpandMappingColumnId = Ext.id();
var ta_type_grid = new Ext.grid.EditorGridPanel({
    id: 'deposit-transactions-editor-grid',
    cls: 'extjs-grid',
    store: type_store,
    autoHeight: true,

    enableDragDrop: true,
    ddGroup: 'types-grid-dd',
    ddText: 'Place this row.',

    sm: new Ext.grid.RowSelectionModel({
        singleSelect: true,
        listeners: {
            beforerowselect: function (sm, i, ke, row) {
                ta_type_grid.ddText = title_img(row.data.transactionName);
            },
            
            rowselect: function(sm, rowIndex){
                selectedRow = rowIndex;
            }
        }
    }),

    cm: new Ext.grid.ColumnModel([
        {
            id: autoExpandMappingColumnId,
            header: _('Name'),
            dataIndex: 'transactionName',
            sortable: false,
            editor: new Ext.form.TextField({
                maxLength: 50,
                allowBlank: false
            })
        }, {
            header: _('Locked'),
            dataIndex: 'transactionLocked',
            width: 100,
            fixed: true,
            align: 'center',
            renderer: function (val, p, record) {
                var booLocked = val === 'Y';
                var help = '';

                if (booLocked) {
                    help = String.format(
                        "<i class='las la-question-circle help-icon' ext:qtip='{0}' ext:qwidth='330' style='cursor: help; margin-left: 5px; vertical-align: middle'></i>",
                        _('This record is locked and cannot be deleted.')
                    );
                }

                return String.format(
                    '<span style="{0}">{1}{2}</span>',
                    booLocked ? 'color: red' : '',
                    booLocked ? _('Yes') : _('No'),
                    help
                );
            }
        },
        action
    ]),

    plugins: [action],

    autoExpandColumn: autoExpandMappingColumnId,
    stripeRows: true,
    viewConfig: {forceFit: true, emptyText: _('No options found.')},
    loadMask: true,
    autoScroll: true,
    autoWidth: true,
    
    tbar: [{
        text: '<i class="las la-plus"></i>' + _('New Transaction Type'),
        cls: 'main-btn',
        handler: function () {
            if (!isOptionSelected()) return false;

            var pos = type_store.getCount();

            var p = new Transaction({
                transactionId: 0,
                transactionName: '',
                transactionOrder: pos
            });

                
                ta_type_grid.stopEditing();
                type_store.insert(pos, p);

                // Hack to make it marked as dirty
                var record = type_store.getAt(pos);
                record.beginEdit();
                record.set("transactionName", _('New Transaction Type'));
                record.endEdit();

                ta_type_grid.startEditing(pos, 0);
            }
        },
        {
            text: '<i class="las la-trash"></i>' + _('Delete Transaction Type'),
            handler: function () {
                if (!isOptionSelected()) return false;

                var selected = ta_type_grid.getSelectionModel().getSelected();

                var warningMessage = '';
                if (!selected) {
                    warningMessage = _('Please select option and try again.');
                }

                if (empty(warningMessage) && selected.data.transactionLocked === 'Y') {
                    warningMessage = _('Selected record is locked and cannot be deleted.');
                }

                if (empty(warningMessage)) {
                    var question = String.format(
                        _('Are you sure you want to delete <i>{0}</i>?'),
                        selected.data.transactionName
                    );

                    Ext.Msg.confirm(_('Please confirm'), question,
                        function (btn) {
                            if (btn == 'yes') {
                                var selId = selected.data.transactionId;

                                if (selId === null) {
                                    //remove new created record
                                    ta_type_grid.store.remove(selected);
                                } else {
                                    submitChanges(selId, 'delete');
                                }
                            }
                        });
                } else {
                    Ext.simpleConfirmation.warning(warningMessage);
                }
            }
        },
        {
            text: '<i class="las la-save"></i>' + _('Save Changes'),
            handler : function()
            {
                if(!isOptionSelected()) return false;
                
                var modifiedRecords = ta_type_grid.store.getModifiedRecords();
                if(modifiedRecords && modifiedRecords.length > 0)
                {
                    var data = [];
                    for(i=0;i<modifiedRecords.length;i++) {
                      data.push(modifiedRecords[i].data);
                    }
                    
                    submitChanges(data, 'save');
                } else {
                    Ext.simpleConfirmation.msg(_('Info'), _('Please make changes and try again'));
                }
            }
        }
    ],
    
    listeners: {
        mouseover: function(e, t){
            var row;
            if((row = this.getView().findRowIndex(t)) !== false){
                this.getView().addRowClass(row, "x-grid3-row-over");
            }
        },

        mouseout: function(e, t){
            var row;
            if((row = this.getView().findRowIndex(t)) !== false && row !== this.getView().findRowIndex(e.getRelatedTarget())){
                this.getView().removeRowClass(row, "x-grid3-row-over");
            }
        },
        
        render: function(){
            new Ext.dd.DropTarget(ta_type_grid.getView().mainBody, {
                ddGroup: 'types-grid-dd',
                notifyDrop: function (dd, e) {

                    var sm = ta_type_grid.getSelectionModel();
                    var rows = sm.getSelections();
                    var cindex = dd.getDragData(e).rowIndex;
                    if (sm.hasSelection()) {
                        for (i = 0; i < rows.length; i++) {
                            type_store.remove(type_store.getById(rows[i].id));
                            type_store.insert(cindex,rows[i]);
                        }

                        // Update order for each of transaction
                        for (i = 0; i < type_store.getCount(); i++) {
                            rec = type_store.getAt(i);
                            
                            if(rec.data.transactionOrder != i) {
                                rec.beginEdit();
                                rec.set("transactionOrder", i);
                                
                                // Mark as dirty
                                var oldName = rec.data.transactionName;
                                rec.set("transactionName", oldName + ' ');
                                rec.set("transactionName", oldName);
                                rec.endEdit();
                            }
                        }
                        
                        sm.selectRecords(rows);
                    }
                    
                }
            });
        }
    }
    
});

var applyTypeParams = function(type_id) {
    // Change baseparam
    type_store.baseParams = type_store.baseParams || {};

    var params = {type: Ext.encode(type_id)};
    Ext.apply(type_store.baseParams, params);
};

function submitChanges( data, action ) {
    var maskText;
    var sendInfo;
    if(action == 'delete') {
        maskText = 'Deleting...';
        sendInfo = data;
        submitToUrl = submitUrl + "/delete-type-option/";
    } else {
        maskText = 'Saving...';
        sendInfo = data;
        submitToUrl = submitUrl + "/manage-type-option/";
    }

    var body = Ext.getBody();
    body.mask(maskText);
    
    var selType = Ext.getCmp('ta_type').getValue();

    Ext.Ajax.request({
        url: submitToUrl,
        params: {
            changes: Ext.encode( sendInfo ),
            type: Ext.encode(selType)
        },

        success: function(result){
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                // Show confirmation message
                body.mask('Done.');

                type_store.commitChanges();

                // Reload the list
                applyTypeParams(selType);
                type_store.reload();


                setTimeout(function(){
                  body.unmask();
                }, 750);
            } else {
                Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' +resultData.error_message + '</span>');
                body.unmask();
            }
        },

        failure: function(){
            Ext.Msg.alert('Status', 'Cannot send information. Please try again later.');
            body.unmask();
        }
    });
}


applyTypeParams('deposit');
type_store.reload();