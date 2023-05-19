var DCHelm = new Array(1,2,3,4,5,6);

function ItaTitleChanger(a_id, a_new_val)
{    
    var b_id=0;
    for(i=0; i<6; i++)
    {
        if(DCHelm[i] == a_new_val) b_id = i + 1;
    }
    
    var temp = DCHelm[a_id - 1];
    DCHelm[a_id - 1] = DCHelm[b_id - 1];
    DCHelm[b_id - 1] = temp;
    
    $('#DCH_' + a_id).val(DCHelm[a_id - 1]);
    $('#DCH_' + b_id).val(DCHelm[b_id - 1]);
}

function set_default() {
    //jQuery
    var records = $('#records').val();

    for (var i = 0; i < 6; i++)
        $('#DCH_' + i).val(i);

    var match_line = $('#match_line').val();
    for (i = 0; i < records; i++) {
        var checked = (match_line.substr(i * 2, 1) == 1);
        $('#cb' + i).prop('checked', checked);
    }
}
/*************************************************************************/

var showImportDialog1 = function(ta_id, ta_currency, ta_last_transaction_date)
{
    function getfileextension(filename) 
    {  
        if( filename.length === 0 ) return ''; 
        var dot = filename.lastIndexOf('.'); 
        if( dot == -1 ) return ''; 
        return filename.substr(dot + 1, filename.length);
    }
    
    var fibasic = new Ext.form.FileUploadField({
        id :'import-dialog1-file',
        height: 40,
        width: 450,
        fieldLabel: 'Select file to upload',
        itemCls: 'no-margin-bottom',
        labelStyle: 'padding-top: 10px; width: 150px',
        buttonText: '<i class="las la-folder-open"></i>' + _('Browse...'),
        name: 'import-dialog1-file'
    });    
    
    var fi_ta_id = new Ext.form.Hidden({
        id :'import-dialog1-ta_id',
        name: 'ta_id',
        value: ta_id
    });
    
    var pan = new Ext.FormPanel({
        id :'import-dialog1-form',
        fileUpload: true,
        frame:false,
        bodyStyle:'padding:5px',
        labelWidth: 150,
        items: [
            fibasic, 
            {
                xtype: 'label',
                style: 'display: block; margin-left: 150px; margin-bottom: 20px',
                text: _('(Supported formats: QuickBooks, Quicken, MSMoney)')
            },
            fi_ta_id
        ]
    });

    var win = new Ext.Window({
        id: 'import-dialog1',
        cls: 'ta_import_dialog',
        title: _('Import Bank File : Select File'),
        layout: 'form',
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        items: pan,
        bbar: new Ext.Toolbar({
            cls: 'no-bbar-borders',
            items: [
                {
                    xtype: 'box',
                    'autoEl': {'tag': 'a', 'href': '#', 'class': 'bluelink', 'html': _('Cannot import data? Click here for manual entry.')}, // Thanks to IE - we need to use quotes...
                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                win.close();
                                var newWnd = new ImportManualDialog({
                                    ta_id: ta_id,
                                    ta_currency: ta_currency,
                                    ta_last_transaction_date: ta_last_transaction_date
                                });
                                newWnd.show();
                            }, this, {stopEvent: true});
                        }
                    }
                }, '->',
                {
                    text: 'Cancel',
                    handler: function () {
                        win.close();
                    }
                },
                {
                    text: 'Continue',
                    cls: 'orange-btn',
                    handler: function () {
                        var v = fibasic.getValue();
                        if (empty(v)) {
                            Ext.simpleConfirmation.error('Please select file to upload!');
                        } else {
                            var ext = getfileextension(v);
                            switch (ext.toLowerCase()) {
                                case 'qbo' :
                                case 'qfx' :
                                case 'ofx' :
                                case 'qif' :
                                    // case 'csv' :
                                    // case 'xls' :
                                    var obj = Ext.getCmp('import-dialog1-form');
                                    if (obj.getForm().isValid()) {

                                        Ext.MessageBox.show({
                                            title: 'Uploading...',
                                            // progressText: 'Uploading...',
                                            msg: 'Uploading...',
                                            width: 300,
                                            wait: true,
                                            waitConfig: {interval: 200},
                                            closable: false,
                                            icon: 'ext-mb-upload'
                                        });

                                        obj.getForm().submit({
                                            url: baseUrl + '/trust-account/import/upload-file',
                                            success: function (f, o) {
                                                Ext.MessageBox.hide();

                                                var resultData = Ext.decode(o.response.responseText);
                                                if (empty(resultData.error)) {
                                                    if (resultData.show_opening_balance) {
                                                        showImportDialog2(ta_id, resultData.opening_balance, resultData.first_transaction_date, resultData.file, resultData.fileName);
                                                    } else {
                                                        showImportDialog3(resultData.file, resultData.fileName, ta_id);
                                                    }
                                                    Ext.getCmp('import-dialog1').close();
                                                } else {
                                                    Ext.simpleConfirmation.error(resultData.error);
                                                }
                                            },
                                            failure: function () {
                                                Ext.MessageBox.hide();
                                                Ext.simpleConfirmation.error('Can\'t upload file! Please try again.');
                                            }
                                        });
                                    }
                                    break;

                                default :
                                    Ext.simpleConfirmation.error('Incorrect file format! Please select QBO, QFX or OFX file.');
                                    break;
                            }
                        }
                    }
                }
            ]
        })
    });
                
    win.show();
    win.center();
};

var showImportDialog2 = function(ta_id, openingBalance, firstTransactionDate, file, fileName)
{

    var openingBalanceField = new Ext.form.NumberField({
        fieldLabel: 'Please enter the opening balance for ' + firstTransactionDate + ' as it is appears in your bank statement',
        value: openingBalance,
        allowNegative: true,
        allowBlank: false,
        allowDecimals: true
    });

    var pan = new Ext.FormPanel({
        id :'import-dialog2-form',
        bodyStyle:'padding:5px',
        labelWidth: 320,
        items: [openingBalanceField]
    });

    var win = new Ext.Window({
        id: 'import-dialog2',
        title: 'Enter the Opening Balance',
        layout: 'form',
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        items: pan,
        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            }, {
                text: 'Continue',
                ctCls: 'orange-btn',
                handler: function () {
                    Ext.Ajax.request({
                        url: baseUrl + "/trust-account/import/save-opening-balance",
                        waitMsg: 'Saving...',
                        params:
                        {
                            ta_id: ta_id,
                            balance: openingBalanceField.getValue()
                        },
                        success:function(f)
                        {
                            var resultData = Ext.decode(f.responseText);

                            if (resultData.success) {
                                Ext.getCmp('import-dialog2').close();
                                Ext.getCmp('editor-grid' + ta_id).store.reload();
                                showImportDialog3(file, fileName, ta_id);
                            }
                            else {
                                Ext.simpleConfirmation.error('Error while saving opening balance');
                            }
                        },
                        failure:function()
                        {
                            Ext.simpleConfirmation.error('Can\'t save opening balance');
                        }
                    });

                }
            }
        ]
    });

    win.show();
    win.center();
};

var showImportDialog3 = function(file, fileName, ta_id)
{    
    function submit_validate()
    {
        //jQuery
        var records = $('#records').val();
        
        var s = '';
        for (var i = 0; i < 6; i++)
            s += DCHelm[i] + ',';
    
        var cb = '';
        var booOneChecked = false;
        for(i=0; i<records; i++) {
            var booChecked = $('#cb' + i).prop('checked');
            if(booChecked) {
                booOneChecked = true;
            }
            cb += (booChecked ? 1 : 0) + ',';
        }
        
        if(!booOneChecked) {
            Ext.simpleConfirmation.warning('All transactions are already imported.');
        } else {
            Ext.Ajax.request({
                url: baseUrl + "/trust-account/import/save",
                waitMsg: 'Saving...',
                params: 
                {
                    ta_id: ta_id,
                    file: Ext.encode(file),
                    fileName: Ext.encode(fileName),
                    dch:s, 
                    cb:cb
                },
                success:function(f)
                {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success)
                        showImportDialog4(resultData.records, ta_id);
                    else
                        Ext.simpleConfirmation.error('Error while importing transactions');

                },
                failure:function()
                { 
                    Ext.simpleConfirmation.error('Can\'t save Bank file');
                }
            });
        }
        
        Ext.getCmp('import-dialog3').close();
    }
    
    var pan = new Ext.FormPanel({
        id :'import-dialog3-form',
        frame:false,
        bodyStyle:'padding:5px',
        labelWidth: 120,
        autoLoad: 
        {
            url: baseUrl + '/trust-account/import/show-validation', 
            params: 
            {
                ta_id: ta_id,
                file: Ext.encode(file), 
                fileName: Ext.encode(fileName)
            },
            callback: function()
            {
                if ($('#import-dialog3-form').find('div[class="error"]').length>0)
                  Ext.getCmp('btn_import_select_trans').disable();
                else
                {
                    Ext.getCmp('btn_import_select_trans').enable();

                    // warning
                    if ($('#warning').html()==='1') // FFS, I know... But... Have to parse!!
                    {
                        Ext.Msg.show({
                            title:'Please confirm',
                            msg:'The opening balance of your new account and the closing balance of your old account do not match.<br><br>Are you sure you want to continue?',
                            buttons:Ext.Msg.YESNO,
                            fn:function (btn)
                            {
                                if (btn=='yes')
                                {
                                    this.close();
                                }
                                else
                                {
                                    this.close();
                                    Ext.getCmp('import-dialog3').close();
                                }
                            }
                        });
                    }
               }
            }
        },
        items: {xtypel: 'label'}
    });

    var win = new Ext.Window({
        id: 'import-dialog3',
        title: 'Import Bank File : Validate',
        layout: 'form',
        modal: true,
        width: 1000,
        autoHeight: true,
        resizable: false,
        items: pan,
        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            },
            {
                text: 'Import Selected Transactions',
                cls: 'orange-btn',
                id: 'btn_import_select_trans',
                disabled: true,
                handler: function () {
                    submit_validate();
                }
            }
        ]
    });

    win.show();
};

var showImportDialog4 = function (records, ta_id) {
    var pan = new Ext.FormPanel({
        id: 'import-dialog4-form',
        frame: false,
        bodyStyle: 'padding:5px',
        labelWidth: 120,
        items: {
            xtypel: 'label',
            html: records + ' transaction' + (records == 1 ? ' was' : 's were') + ' imported!',
            style: 'text-align:center;'
        }
    });

    var win = new Ext.Window({
        id: 'import-dialog4',
        title: 'Import Bank File : Finish',
        layout: 'form',
        modal: true,
        width: 300,
        autoHeight: true,
        resizable: false,
        items: pan,
        buttons: [
            {
                text: 'Finish',
                cls:  'orange-btn',
                handler: function () {
                    Ext.getCmp('editor-grid' + ta_id).store.reload();
                    win.close();
                }
            }
        ]
    });

    win.show();
};