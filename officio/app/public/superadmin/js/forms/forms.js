var openPdf = function(pdfId) {
    window.open( topBaseUrl +'/forms/index/open-version-pdf?version_id=' + pdfId,'view_version_pdf');
    return false;
};

var openXOD = function(pdfId) {
    window.open( topBaseUrl +'/forms/index/open-xod?version_id=' + pdfId);
    return false;
};

var openHtml = function(htmlId) {
    window.open(topBaseUrl + '/pdf/' + htmlId + "/index.html");
    return false;
};

Ext.onReady(function(){
    Ext.QuickTips.init();

    var msg = function(title, msg){
        Ext.Msg.show({
            title: title,
            msg: msg,
            minWidth: 300,
            modal: true,
            icon: Ext.Msg.INFO,
            buttons: Ext.Msg.OK
        });
    };

    var incomingDateFormat = 'Y-m-d H:i:s';
    var dateRenderer = Ext.util.Format.dateRenderer('M d, Y');
    var assignedForm = Ext.data.Record.create([
       {name: 'form_version_id', type: 'int'},
       {name: 'file_name'},
       {name: 'date_uploaded', type: 'date', dateFormat: incomingDateFormat},
       {name: 'date_version', type: 'date', dateFormat: incomingDateFormat},
       {name: 'size'},
       {name: 'has_pdf'},
       {name: 'has_html'},
       {name: 'has_xod'}

    ]);

    var filesStore = new Ext.data.Store({
        // load using HTTP
        url: topBaseUrl + '/forms/forms-folders/files',
        baseParams: {
            folder_id: 0, 
            version: Ext.encode('latest')
        },
        autoLoad: true,
        remoteSort: true,
        sortInfo:{field:'file_name', direction: 'ASC'},

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
            {
                id: 'form_version_id',
                root:'rows',
                totalProperty:'totalCount'
            }, assignedForm)
    });    
    
    var sm2 = new Ext.grid.CheckboxSelectionModel();
    
    var pagingBar = new Ext.PagingToolbar({
        pageSize: 25,
        store: filesStore,
        displayInfo: true,
        displayMsg: 'Displaying forms {0} - {1} of {2}',
        emptyMsg: "No forms to display"
    });
    
    var viewFormIcon = function(val, p, record) {
        var result = '';

        if (record.data.has_pdf) {
            result += '<a href="#" onclick="return openPdf('+record.data.form_version_id+');" title="Click to open/download pdf file"><img src="'+baseUrl+'/images/pdf.png"/></a>';
        } else {
            result += '<img src="'+baseUrl+'/images/icons/cancel.png" title="Pdf file doesn\'t exists"/>';
        }

        if(record.data.has_html) {
            result += '&nbsp;&nbsp;<a href="#" onclick="return openHtml('+record.data.form_version_id+');" title="Click to open/download html file"><img src="'+baseUrl+'/images/icons/html.png"/></a>';
        }

        if(record.data.has_xod) {
            result += '&nbsp;&nbsp;<a href="#" onclick="return openXOD('+record.data.form_version_id+');" title="Click to open/download xod file"><img src="'+baseUrl+'/images/icons/xod.png"/></a>';
        }

        return result;
    };

    var customFileName = function (val, p, record) {
        var strName = record.data.file_name;
        var version = Ext.getCmp('forms-superadmin-versions-combo').getValue();

        if (version === 'all') {
            strName += ': <span style="color:#666666; font-style: italic;">' + record.data.date_version.format('Y-m-d') + '</span>';
        }

        return strName;
    };
    
    var _showPdfFormVersion = function(action) {
        var requestUrl = baseUrl + "/forms/manage";
        var txtFolder = '';
        var folderId = 0;
        var booAllowSkipFileUpload = false;
        
        switch (action) {
            case 'edit':
                toolbarBtn = 'forms-btn-edit';
                failureMsg = 'Form was not updated. Please try again later.';
                booAllowSkipFileUpload = true;
                
                btnSubmitTitle = wndTitle = 'Edit form';
                break;
                
            case 'add':
                toolbarBtn = 'forms-btn-add';
                failureMsg = 'Form was not created. Please try again later.';
                
                btnSubmitTitle = wndTitle = 'New Form';
                
                // Check if there is selected folder
                var sm = tree.getSelectionModel();
                var selected =  sm.getSelectedNode();
                if (selected === null || selected.attributes.id == 'source') {
                    Ext.simpleConfirmation.msg('Info', 'Please select a folder where you want upload a form');
                    return;
                } else {
                    txtFolder = selected.attributes.text;
                    folderId = selected.attributes.folder_id;
                }
                
                break;
                
            case 'new-version':
                toolbarBtn = 'forms-btn-new-version';
                failureMsg = 'New form version was not created. Please try again later.';
                
                btnSubmitTitle = wndTitle = 'New Form Version';
                break;
                
            default:
                // Incorrect action
                return;
        }

        function extractFile(data){
            data = data.replace(/^\s|\s$/g, "");

            var m;
            if (/\.\w+$/.test(data)) {
                m = data.match(/([^\/\\]+)\.(\w+)$/);
                if (m) {
                    return {
                        filename: m[1],
                        ext:      m[2]
                    };
                } else {
                    return {
                        filename: "",
                        ext:      null
                    };
                }
            } else {
                m = data.match(/([^\/\\]+)$/);
                if (m) {
                    return {
                        filename: m[1],
                        ext:      null
                    };
                } else {
                    return {
                        filename: "",
                        ext:      null
                    };
                }
            }
        }
        
        
        var uploadVersionForm = new Ext.Window({
            title: wndTitle,
            closeAction: 'close',
            width: 700,
            height: 650,
            
            plain:false,
            modal: true,
            resizable: false,

            layout:'border',
            border: true,
            defaults: {
                // applied to each contained panel
                bodyStyle:'vertical-align: top;'
            },
            
            listeners: {
                'show': function(){
                    // Load information  from server about selected pdf form
                    if(action == 'edit' || action == 'new-version') {
                        // Load info about selected
                        uploadVersionForm.body.mask('Loading...');
                        
                        var booLatest = (action == 'new-version');
                        
                        var arrSelected = getSelectedFormIds();
                        // Get selected form
                        Ext.Ajax.request({
                                url: baseUrl + "/forms/load-info",
                                params:
                                {
                                    pdf_version_id: Ext.encode(arrSelected[0]),
                                    latest: booLatest
                                },
                                
                                success:function(f, o)
                                {
                                    var resultData = Ext.decode(f.responseText);
                                    
                                    if(resultData.success) {
                                        // Show confirmation
                                        uploadVersionForm.body.mask('Done !');
                                        
                                        // Update values
                                        Ext.getCmp('superadmin-form-field-note1').setValue(resultData.arrResult.note1);
                                        Ext.getCmp('superadmin-form-field-note2').setValue(resultData.arrResult.note2);
                                        Ext.getCmp('superadmin-form-field-filename').setValue(resultData.arrResult.file_name);
                                        Ext.getCmp('superadmin-form-field-version-id').setValue(resultData.arrResult.form_version_id);
                                        Ext.getCmp('superadmin-form-field-formtype').setValue(resultData.arrResult.form_type);

                                        
                                        if(!booLatest) {
                                            Ext.getCmp('superadmin-form-field-uploaded-date').setValue(Date.parseDate(resultData.arrResult.uploaded_date, incomingDateFormat));
                                            Ext.getCmp('superadmin-form-field-version-date').setValue(Date.parseDate(resultData.arrResult.version_date, incomingDateFormat));
                                        }
                                            
                                        Ext.getCmp('superadmin-form-field-label-uploadto'). setVisible(false);
                                        
                                        
                                        setTimeout(function(){
                                            uploadVersionForm.body.unmask();
                                        }, 750);
                                    } else {
                                        // Show error message
                                        uploadVersionForm.body.unmask();
                                        uploadVersionForm.close();
                                        Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' +resultData.message + '</span>');
                                    }
                                },
                                
                                failure: function(){
                                    // Some issues with network?
                                    uploadVersionForm.body.unmask();
                                    uploadVersionForm.close();
                                    Ext.Msg.alert('Status', failureMsg);
                                }
                        });
                        
                    }
                }
            },
            
            items: [

                {
                    id: 'superadmin-version-form',
                    region: 'center',
                    xtype: 'form',
                    fileUpload: true,
                    bodyStyle: 'padding: 5px;',
                    defaults: {
                        msgTarget: 'side'
                    },
                    items:[
                        {
                            id: 'superadmin-form-field-version-id',
                            xtype: 'hidden',
                            value: 0
                        },
                        {
                            xtype:'label',
                            text: 'Select Form To Upload:'
                        },
                        new Ext.form.FileUploadField({
                            id: 'superadmin-form-field-file',
                            name: 'superadmin-form-field-file',
                            width: 310,
                            height: 38,
                            emptyText: 'Select a pdf form',
                            allowBlank: booAllowSkipFileUpload,
                            hideLabel: true,
                            
                            listeners: {
                                'fileselected': function(fb, v){
                                    var fName = '';
                                    var oFileDetails = extractFile(v);
                                    // Check if this file is pdf
                                    var xodCheckbox = Ext.getCmp('convert-to-xod');
                                    if(oFileDetails.ext.toLowerCase() === 'pdf') {
                                        fName = oFileDetails.filename;

                                        if (empty(Ext.getCmp('superadmin-xod-form-field-file').getValue())) {
                                            xodCheckbox.setDisabled(false);
                                            xodCheckbox.setValue(true);
                                        } else {
                                            xodCheckbox.setValue(false);
                                            xodCheckbox.setDisabled(true);
                                        }
                                    } else {
                                        this.reset();
                                        if (xodCheckbox) {
                                            xodCheckbox.setValue(false);
                                            xodCheckbox.setDisabled(true);
                                        }
                                        Ext.simpleConfirmation.msg('Info', 'Only pdf forms are supported');
                                    }

                                    // Use only filename without extension
                                    var formName = Ext.getCmp('superadmin-form-field-filename');
                                    if(formName) {
                                        formName.setValue(fName);
                                    }
                                }
                            }
                        }),
                        {// Spacer
                            xtype:'label',
                            text: '',
                            html: '<div style="margin-bottom: 15px;"></div>'
                        },
                        {
                            xtype:'label',
                            text: 'Select XOD File To Upload:'
                        },
                        new Ext.form.FileUploadField({
                            id: 'superadmin-xod-form-field-file',
                            name: 'superadmin-xod-form-field-file',
                            width: 310,
                            height: 38,
                            emptyText: 'Select a xod file',
                            allowBlank: true,
                            hideLabel: true,

                            listeners: {
                                'fileselected': function(fb, v){
                                    var oFileDetails = extractFile(v);

                                    // Check if this file is xod
                                    var xodCheckbox = Ext.getCmp('convert-to-xod');
                                    if(oFileDetails.ext.toLowerCase() === 'xod') {
                                        if (xodCheckbox) {
                                            xodCheckbox.setValue(false);
                                            xodCheckbox.setDisabled(true);
                                        }
                                    } else {
                                        this.reset();
                                        if (xodCheckbox) {
                                            xodCheckbox.setValue(true);
                                            xodCheckbox.setDisabled(false);
                                        }
                                        Ext.simpleConfirmation.msg('Info', 'Only xod files are supported');
                                    }
                                }
                            }
                        }),
                        {// Spacer
                            xtype:'label',
                            text: '',
                            html: '<div style="margin-bottom: 15px;"></div>'
                        },
                        {
                            xtype:'label',
                            text: 'Form Name:'
                        },
                        {
                            id: 'superadmin-form-field-filename',
                            xtype: 'textfield',
                            hideLabel: true,
                            allowBlank: false,
                            width: 330,
                            style: 'margin-bottom: 15px;'
                        },
                        {
                            xtype:'label',
                            text: 'Form Type:'
                        },
                        {
                            id: 'superadmin-form-field-formtype',
                            xtype: 'combo',
                            store: new Ext.data.ArrayStore({
                                fields: ['type_id', 'type_name'],
                                data: [['', 'General'], ['bar', 'Barcode']]
                            }),
                            hideLabel: true,
                            allowBlank: false,
                            width: 110,
                            
                            displayField: 'type_name',
                            valueField:   'type_id',
                            typeAhead: false,
                            mode: 'local',
                            forceSelection: true,
                            triggerAction: 'all',
                            value: '',
                            selectOnFocus: true
                        },
                        {
                            xtype:'label',
                            html: '<p>&nbsp;</p>'
                        },                        
                        {
                            xtype:'label',
                            text: 'Version Date:'
                        },
                        {
                            id: 'superadmin-form-field-version-date',
                            xtype: 'datefield',
                            format: 'M d, Y',
                            width: 150,
                            hideLabel: true,
                            allowBlank: false,
                            value: new Date(),
                            style: 'margin-bottom: 15px;'
                        },
                        {
                            xtype:'label',
                            text: 'Date Uploaded:'
                        },
                        {
                            id: 'superadmin-form-field-uploaded-date',
                            xtype: 'datefield',
                            format: 'M d, Y',
                            width: 150,
                            value: new Date(),
                            required: true,
                            disabled:true,
                            hideLabel: true,
                            style: 'margin-bottom: 15px;'
                        },
                        {
                            id: 'convert-to-xod',
                            xtype: 'checkbox',
                            hideLabel: true,
                            boxLabel: 'Convert to XOD',
                            checked: false,
                            disabled: true,
                            scope: this
                        },{
                            id: 'convert-to-html',
                            xtype: 'checkbox',
                            hideLabel: true,
                            boxLabel: 'Convert to HTML',
                            checked: false,
                            disabled: true,
                            scope: this
                        },
                        {// Spacer
                            xtype:'label',
                            text: '',
                            html: '<div style="margin-bottom: 15px;"></div>'
                        },
                        {
                            id: 'superadmin-form-field-label-uploadto',
                            xtype:'label',
                            text: 'Folder to upload into: '
                        },
                        {
                            xtype:'label',
                            text: txtFolder,
                            style: 'color: #0000cc;'
                        }
                    ]
                },
                
                {
                    id: 'superadmin-version-form2',
                    region: 'east',
                    height: 35,
                    width: 320,
                    xtype: 'form',
                    bodyStyle: 'padding: 5px;',
                    items:[
                        {
                            xtype:'label',
                            text: 'Note #1:'
                        },
                        {
                            id: 'superadmin-form-field-note1',
                            xtype: 'textarea',
                            hideLabel: true,
                            width: 300,
                            height: 120,
                            style: 'margin-bottom: 15px;'
                        },
                        
                        {
                            xtype:'label',
                            text: 'Note #2:'
                        },
                        {
                            id: 'superadmin-form-field-note2',
                            xtype: 'textarea',
                            hideLabel: true,
                            width: 300,
                            height: 120
                        }
                    ]
                }
                
            ],
            
            buttons: [{
                text: 'Cancel',
                animEl: toolbarBtn,
                handler: function(){
                    uploadVersionForm.close();
                }
            },{
                id: 'form-submit-btn',
                animEl: toolbarBtn,
                text: btnSubmitTitle,
                cls: 'orange-btn',
                handler: function(){
                    var fp = Ext.getCmp('superadmin-version-form');
                    var fp2 = Ext.getCmp('superadmin-version-form2');
                    if(fp.getForm().isValid() && fp2.getForm().isValid()){
                        var body = Ext.getBody();
                        
                        fp.getForm().submit({
                            url: requestUrl,
                            waitMsg: 'Uploading information...',
                            params: {
                                doaction: Ext.encode(action),
                                
                                version_id: Ext.encode(Ext.getCmp('superadmin-form-field-version-id').getValue()),
                                
                                version_date: Ext.encode(Ext.getCmp('superadmin-form-field-version-date').getValue()),
                                
                                folder_id: folderId,
                                file_name: Ext.encode(Ext.getCmp('superadmin-form-field-filename').getValue()),
                                form_type: Ext.encode(Ext.getCmp('superadmin-form-field-formtype').getValue()),

                                note1: Ext.encode(Ext.getCmp('superadmin-form-field-note1').getValue()),
                                note2: Ext.encode(Ext.getCmp('superadmin-form-field-note2').getValue())
                            },
                            
                            success: function(form, action){
                                if(!empty(action.result.message)) {
                                    Ext.simpleConfirmation.warning(action.result.message);
                                }
                            
                                formsGrid.store.reload();
                                uploadVersionForm.close();
                            },
                            
                            failure: function(f, action) {
                                Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' + action.result.message + '</span>');
                            }
                        });
                    }
                }
            }]

        });

        uploadVersionForm.show();
    };
    var FormsGrid = function (config) {
        var thisGrid = this;
        Ext.apply(this, config);

        FormsGrid.superclass.constructor.call(this, {
            id: 'forms-main-grid',
            height: getSuperadminPanelHeight() - 50,
            width: 690,
            region: 'center',
            autoWidth: true,
            store: filesStore,
            bodyStyle: 'padding: 0px; background-color:#fff;',
            style: 'padding: 0 5px;',

            loadMask: true,

            cm: new Ext.grid.ColumnModel([
                sm2,
                {
                    id: 'forms-grid-column-filename',
                    header: "File Name",
                    sortable: true,
                    dataIndex: 'file_name',
                    renderer: customFileName
                },
                {
                    header: "Date Uploaded",
                    width: 80,
                    sortable: true,
                    renderer: dateRenderer,
                    dataIndex: 'date_uploaded'
                },
                {header: "Version Date", width: 80, sortable: true, renderer: dateRenderer, dataIndex: 'date_version'},
                {header: "Size", width: 110, sortable: true, dataIndex: 'size'},
                {
                    header: "View Form",
                    width: 80,
                    sortable: false,
                    dataIndex: 'size',
                    align: 'center',
                    renderer: viewFormIcon
                }
            ]),
            sm: sm2,
            viewConfig: {emptyText: 'No forms found.'},
            autoExpandColumn: 'forms-grid-column-filename',

            tbar: {
                xtype: 'panel',
                items: [
                    {
                        xtype: 'toolbar',
                        style: 'margin-bottom: 0',
                        items: [
                            {
                                id: 'forms-btn-add',
                                text: '<i class="las la-file-upload"></i>' + _('Upload New Form'),
                                tooltip: 'Upload new pdf form to server',
                                handler: function () {
                                    _showPdfFormVersion('add');
                                }

                            }, {
                                id: 'forms-btn-edit',
                                text: '<i class="las la-edit"></i>' + _('Edit Form'),
                                tooltip: 'Edit form details',
                                handler: function () {
                                    // Load info about selected form and show it
                                    var arrSelected = getSelectedFormIds();
                                    if (arrSelected.length != 1) {
                                        Ext.simpleConfirmation.msg('Info', 'Please select one pdf form and try again');
                                    } else {
                                        _showPdfFormVersion('edit');
                                    }
                                }

                            }, {
                                id: 'forms-btn-new-version',
                                text: '<i class="las la-plus"></i>' + _('New Form Version'),
                                tooltip: 'Upload new version of pdf form to server',
                                handler: function () {
                                    // Load info about selected form and show it
                                    var arrSelected = getSelectedFormIds();
                                    if (arrSelected.length != 1) {
                                        Ext.simpleConfirmation.msg('Info', 'Please select one pdf form and try again');
                                    } else {
                                        _showPdfFormVersion('new-version');
                                    }
                                }

                            }, {
                                id: 'forms-btn-delete',
                                text: '<i class="las la-trash"></i>' + _('Delete Form'),
                                tooltip: 'Remove the selected form',
                                handler: function () {
                                    var arrSelected = getSelectedFormIds();
                                    if (arrSelected.length > 0) {
                                        // There are selected forms
                                        if (arrSelected.length == 1) {
                                            title = 'Delete selected form?';
                                            msg = 'Selected form will be deleted. Are you sure to delete it?';
                                        } else {
                                            title = 'Delete selected forms?';
                                            msg = 'Selected forms will be deleted. Are you sure to delete them?';
                                        }

                                        Ext.Msg.show({
                                            title: title,
                                            msg: msg,
                                            buttons: Ext.Msg.YESNO,
                                            fn: function (btn, text) {
                                                if (btn == 'yes') {
                                                    deletePdfForms();
                                                }
                                            },
                                            animEl: 'forms-btn-delete'
                                        });
                                    } else {
                                        Ext.simpleConfirmation.msg('Info', 'Please select one or several pdf forms and try again');
                                    }
                                }

                            }, {
                                id: 'forms-btn-print',
                                text: '<i class="las la-print"></i>' + _('Print'),
                                tooltip: 'Print selected form',
                                disabled: true
                            }, {
                                id: 'forms-btn-email',
                                text: '<i class="las la-envelope"></i>' + _('Email'),
                                tooltip: 'Email selected form',
                                disabled: true
                            }
                        ]
                    }, {
                        xtype: 'toolbar',
                        items: [
                            {
                                text: '<i class="las la-cogs"></i>' + _('Manage/convert forms'),
                                style: 'padding-left: 5px;',
                                menu: {
                                    cls: 'no-icon-menu',
                                    items: [
                                        {
                                            text: '<i class="las la-edit"></i>' + _('Manage XOD forms'),
                                            menu: {
                                                cls: 'no-icon-menu',
                                                items: [
                                                    {
                                                        text: '<i class="las la-edit"></i>' + _('Convert ALL PDF forms to XOD'),
                                                        tooltip: 'Convert all PDF forms to XOD',
                                                        handler: function () {
                                                            var sender = new Pdf2XodConverter([0], 'convert_all');
                                                            sender.show();
                                                            sender.center();
                                                        }
                                                    }, {
                                                        text: '<i class="las la-trash"></i>' + _('Revert ALL XOD forms to PDF'),
                                                        tooltip: 'Revert ALL XOD forms to PDF',
                                                        handler: function () {
                                                            var sender = new Pdf2XodConverter([0], 'revert_all');
                                                            sender.show();
                                                            sender.center();
                                                        }
                                                    }, '-', {
                                                        text: '<i class="las la-edit"></i>' + _('Convert selected PDF form(s) to XOD'),
                                                        tooltip: 'Convert selected PDF form(s) to XOD',
                                                        handler: function () {
                                                            var arrSelected = getSelectedFormIds();
                                                            if (arrSelected.length > 0) {
                                                                // There are selected forms
                                                                var sender = new Pdf2XodConverter(arrSelected, 'convert_selected');
                                                                sender.show();
                                                                sender.center();
                                                            } else {
                                                                Ext.simpleConfirmation.msg('Info', 'Please select one or several pdf forms and try again');
                                                            }
                                                        }
                                                    }, {
                                                        text: '<i class="las la-trash"></i>' + _('Revert selected XOD form(s) to PDF'),
                                                        tooltip: 'Revert selected XOD form(s) to PDF',
                                                        handler: function () {
                                                            var arrSelected = getSelectedFormIds();
                                                            if (arrSelected.length > 0) {
                                                                // There are selected forms
                                                                var sender = new Pdf2XodConverter(arrSelected, 'revert_selected');
                                                                sender.show();
                                                                sender.center();
                                                            } else {
                                                                Ext.simpleConfirmation.msg('Info', 'Please select one or several pdf forms and try again');
                                                            }
                                                        }
                                                    }
                                                ]
                                            }
                                        }, {
                                            text: '<i class="lab la-html5"></i>' + _('Manage HTML forms'),
                                            menu: {
                                                cls: 'no-icon-menu',
                                                items: [
                                                    {
                                                        text: '<i class="las la-edit"></i>' + _('Convert PDF to HTML'),
                                                        tooltip: 'Convert selected form to HTML',
                                                        disabled: true, // Temporary disable on production
                                                        handler: function () {
                                                            var arrSelected = getSelectedFormIds();
                                                            if (arrSelected.length > 0) {
                                                                // There are selected forms
                                                                var sender = new Pdf2HtmlConverter(arrSelected, false);
                                                                sender.show();
                                                                sender.center();
                                                            } else {
                                                                Ext.simpleConfirmation.msg('Info', 'Please select one or several pdf forms and try again');
                                                            }
                                                        }
                                                    }, {
                                                        text: '<i class="las la-trash"></i>' + _('Revert HTML to PDF'),
                                                        tooltip: 'Revert selected form to PDF',
                                                        disabled: true, // Temporary disable on production
                                                        handler: function () {
                                                            var arrSelected = getSelectedFormIds();
                                                            if (arrSelected.length > 0) {
                                                                // There are selected forms
                                                                var sender = new Pdf2HtmlConverter(arrSelected, true);
                                                                sender.show();
                                                                sender.center();
                                                            } else {
                                                                Ext.simpleConfirmation.msg('Info', 'Please select one or several pdf forms and try again');
                                                            }
                                                        }

                                                    }
                                                ]
                                            }
                                        }, '-', {
                                            text: '<i class="las la-cog"></i>' + _('Check Forms'),
                                            tooltip: 'Check if all forms have pdf files',
                                            handler: function () {
                                                checkForms();
                                            }
                                        }]
                                }
                            }, '->', {
                                xtype: 'label',
                                hidden: false,
                                html: 'Search: '
                            }, {
                                id: 'search-form',
                                xtype: 'textfield',
                                fieldLabel: 'Search',
                                style: 'margin-left:5px;',
                                emptyText: site_version == 'australia' ? 'eg. 457, 80, etc...' : 'eg. 5406, 1344, etc...',
                                width: 100,
                                enableKeyEvents: true,
                                listeners: {
                                    keyup: function (field, e) {
                                        if (e.getKey() == Ext.EventObject.ENTER) {
                                            runSearchForm();
                                        }
                                    }
                                }
                            }, {
                                id: 'forms-btn-search',
                                text: '<i class="las la-search"></i>',
                                style: 'padding-left: 5px;',
                                handler: function () {
                                    runSearchForm();
                                }
                            }
                        ]
                    }
                ]
            },

            bbar: pagingBar
        });
    };

    Ext.extend(FormsGrid, Ext.grid.GridPanel, {});
    var formsGrid = new FormsGrid();

    var tree = new Ext.tree.TreePanel({
        useArrows: true,
        animate: true,
        autoScroll: true,
        containerScroll: true,
        bodyStyle: 'background-color:#fff',

        root: new Ext.tree.AsyncTreeNode({
            text: 'Folders',
            singleClickExpand: true,
            id: 'source',
            cls: 'main-folder-icon'
        }),
        
        loader: new Ext.tree.TreeLoader({
            dataUrl: baseUrl + "/forms/list",
            baseParams: {
                with_files: Ext.encode(false),
                version: Ext.encode('latest')
            },

            preloadChildren: false
            
        }),
        
        tbar: [new Ext.Toolbar.Button({
                text: '<i class="las la-folder-plus"></i>' + _('Add Folder'),
                handler: function() {
                    var sel = tree.getSelectionModel().getSelectedNode();
                    if(sel) {
                        Ext.Msg.prompt('Add new Folder', 'Folder Name:', function(btn, text){
                            if(btn == 'ok') {
                                if(empty(text)) {
                                    Ext.Msg.alert('Status', 'Please enter folder name');
                                    return false;
                                }
                                
                                Ext.getBody().mask('Adding...');
                                
                                Ext.Ajax.request({
                                    url: baseUrl + '/forms/folder-add',
                                    params: {
                                        parent_id: (sel.attributes.id == 'source' ? 0 : sel.attributes.folder_id),
                                        name: Ext.encode(text)
                                    },                
                                    success: function(result, request) 
                                    {
                                        tree.getRootNode().reload();
                                        
                                        Ext.getBody().mask('Done!');
                                        
                                        setTimeout(function(){
                                            Ext.getBody().unmask();
                                        }, 750);
                                    },                
                                    failure: function()
                                    {
                                        Ext.Msg.alert('Status', 'Folder can\'t be created. Please try again later.');
                                    }
                                });
                            }
                        });
                    } else {
                        Ext.simpleConfirmation.msg('Info', 'Please select parent folder where you want add new folder');
                    }
                }
            }),
            new Ext.Toolbar.Button({
                text: '<i class="las la-edit"></i>' + _('Rename'),
                handler: function()
                {
                    var sel = tree.getSelectionModel().getSelectedNode();
                    if(sel) {
                        Ext.Msg.prompt('Rename Folder', 'Folder Name:', function(btn, text){
                            if(btn == 'ok') {
                                if(empty(text)) {
                                    Ext.Msg.alert('Status', 'Please enter folder name');
                                    return false;
                                }
                                
                                Ext.getBody().mask('Saving...');
                                
                                Ext.Ajax.request({
                                    url: baseUrl + '/forms/folder-rename',
                                    params: {
                                        folder_id: (sel.attributes.id == 'source' ? 0 : sel.attributes.folder_id),
                                        name: Ext.encode(text)
                                    },                
                                    success: function(result, request) {
                                        tree.getRootNode().reload();
                                        
                                        Ext.getBody().mask('Done!');
                                        
                                        setTimeout(function(){
                                            Ext.getBody().unmask();
                                        }, 750);
                                    },                
                                    failure: function(form, action) {
                                        Ext.Msg.alert('Status', 'Folder can\'t be renamed. Please try again later.');
                                    }
                                });
                            }
                        }, null, false, sel.attributes.text);
                    } else {
                        Ext.simpleConfirmation.msg('Info', 'Please select a folder to rename');
                    }
                }
            }),
            new Ext.Toolbar.Button({
                text: '<i class="las la-trash"></i>' + _('Delete'),
                style: 'padding-left: 5px;',
                handler: function()
                {
                    var sel = tree.getSelectionModel().getSelectedNode();
                    if(sel) {
                        if(sel.attributes.id == 'source') {
                            Ext.simpleConfirmation.msg('Info', 'Sorry. You can\'t delete the Top Level Folder');
                            return false;
                        }
                        
                        Ext.Msg. confirm('Please confirm', 'Are you sure you want to delete folder "' + sel.attributes.text + '"?', function(btn, text){
                            if (btn == 'yes') {                                        
                                Ext.Ajax.request({
                                    url: baseUrl + '/forms/folder-delete',
                                    params: {
                                        folder_id: sel.attributes.folder_id
                                    },                                
                                    success: function(result, request) {
                                        tree.getRootNode().reload();
                                        
                                        Ext.getBody().mask('Done!');
                                        
                                        setTimeout(function(){
                                            Ext.getBody().unmask();
                                        }, 750);
                                    },                                
                                    failure: function(form, action) 
                                    {
                                        Ext.Msg.alert('Status', 'Folder can\'t be deleted. Please try again later.');
                                    }
                                });
                            }
                        });
                    } else {
                        Ext.simpleConfirmation.msg('Info', 'Please select a folder to delete');
                    }
                }
            })],
        
        width: 305,
        height: getSuperadminPanelHeight() - 85,
        style: "padding: 5px 0px;"
    });

    // add a tree sorter in folder mode
    new Ext.tree.TreeSorter(tree, {folderSort:true});
    
    tree.getRootNode().reload();
    
    var sm = tree.getSelectionModel();
    sm.on('beforeselect', function(sm, node){
        // Load description for selected pdf form
        if (!node.isLeaf()) {
            var folder_id = 0;
            if(node.attributes.id != 'source') {
                folder_id = node.attributes.folder_id;
            }
            
            // Change baseParams
            filesStore.baseParams = filesStore.baseParams || {};
            
            var params = {folder_id: Ext.encode(folder_id)};
            Ext.apply(filesStore.baseParams, params);
            
            // Reload tree list
            filesStore.load();
        }
        return true;
    });


    var tree_with_combo = new Ext.Panel({
        region: 'west',
        collapsible: true,
        split: true,
        collapseMode: 'mini',
        width: 310,
        bodyStyle: 'background-color:#fff',
        items: [
            {
                xtype: 'container',
                style: 'padding: 5px',
                items: {
                    id: 'forms-superadmin-versions-combo',
                    xtype: 'combo',
                    store: new Ext.data.SimpleStore({
                        fields: ['show_id', 'show_name'],
                        data: [['all', 'Show ALL version of forms'], ['latest', 'Show only LAST version of forms']]
                    }),

                    displayField: 'show_name',
                    valueField: 'show_id',

                    width: 310,
                    listWidth: 310,
                    mode: 'local',
                    typeAhead: false,
                    editable: false,
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    value: 'latest',

                    listeners: {
                        'beforeselect': function(combo, rec, index){
                            // Change baseParams
                            filesStore.baseParams = filesStore.baseParams || {};

                            var params = {version: Ext.encode(rec.data.show_id)};
                            Ext.apply(filesStore.baseParams, params);

                            // Reload tree list
                            filesStore.reload();
                        }
                    }

                }
            },
            tree
        ]
    });

    var runSearchForm = function() {

        var search_field = Ext.getCmp('search-form');

        if (!empty(search_field)) {
            var params;
            var search_form = search_field.getValue();
            var sel_version = Ext.getCmp('forms-superadmin-versions-combo').getValue();
            if (!empty(search_form)) {
                params = {
                    search_form: Ext.encode(search_form),
                    version:     Ext.encode(sel_version),
                    format:      Ext.encode('files')
                };
                Ext.apply(filesStore.baseParams, params);
                filesStore.proxy.setApi(Ext.data.Api.actions.read, topBaseUrl + '/forms/index/search');
                filesStore.load();
            } else {
                // Use selected folder id, if search text is empty
                var node = tree.getSelectionModel().getSelectedNode();
                var folder_id = !empty(node) && node.attributes.id != 'source' ? node.attributes.folder_id : 0;

                params = {
                    folder_id:   Ext.encode(folder_id),
                    version:     Ext.encode(sel_version)
                };
                Ext.apply(filesStore.baseParams, params);
                filesStore.proxy.setApi(Ext.data.Api.actions.read, topBaseUrl + '/forms/forms-folders/files');
                filesStore.load();
            }
        }
    };



      // return selected folder form landing_tree object
      var getLandingFolderAttributes = function() {
          var sel = landing_tree.getSelectionModel().getSelectedNode();
          if(sel) {
              if(sel.attributes.type == 'folder') {
                  return sel.attributes; //folder is selected
              } else if(sel.attributes.type == 'file') {
                  return sel.parentNode.attributes; //file is selected. return parent folder id
              } else {
                  return {folder_id: 0}; // root folder
              }
          }
          return false;
      };
      
    // return selected file form landing_tree object
      var getLandingFileAttributes = function() {
          var sel = landing_tree.getSelectionModel().getSelectedNode();
          if(sel && sel.attributes.type == 'file') {
              return sel.attributes;
          }
          return false;
      };

      var addLandingFolderBtn = new Ext.Toolbar.Button({
        text: '<i class="las la-folder-plus"></i>' + _('Add Folder'),
        handler: function() {
            var folder = getLandingFolderAttributes();
            if(folder) {
                Ext.Msg.prompt('Add new Folder', 'Folder Name:', function(btn, text){
                    if(btn == 'ok') {
                        if(empty(text)) {
                            Ext.Msg.alert('Status', 'Please enter folder name');
                            return false;
                        }
                        
                        Ext.getBody().mask('Adding...');
                        
                        Ext.Ajax.request({
                            url: baseUrl + '/forms/landing-add',
                            params: {
                                parent_id: (folder.id == 'source' ? 0 : folder.folder_id),
                                name: Ext.encode(text)
                            },                
                            success: function(result, request) 
                            {
                                landing_tree.getRootNode().reload();
                                
                                Ext.getBody().mask('Done!');
                                
                                setTimeout(function(){
                                    Ext.getBody().unmask();
                                }, 750);
                            },                
                            failure: function(form, action) 
                            {
                                Ext.Msg.alert('Status', 'Folder can\'t be created. Please try again later.');
                            }
                        });
                    }
                });
            } else {
                Ext.simpleConfirmation.msg('Info', 'Please select parent folder where you want add new folder');
            }
        }
    });
      
      var renameLandingFolderBtn = new Ext.Toolbar.Button({
        text: '<i class="las la-edit"></i>' + _('Rename Folder'),
        handler: function()
        {
            var folder = getLandingFolderAttributes();
            if(folder && folder.folder_id > 0) {
                Ext.Msg.prompt('Rename Folder', 'Folder Name:', function(btn, text){
                    if(btn == 'ok') {
                        if(empty(text)) {
                            Ext.Msg.alert('Status', 'Please enter folder name');
                            return false;
                        }
                        
                        Ext.getBody().mask('Saving...');
                        
                        Ext.Ajax.request({
                            url: baseUrl + '/forms/landing-rename',
                            params: {
                                folder_id: (folder.id == 'source' ? 0 : folder.folder_id),
                                name: Ext.encode(text)
                            },                
                            success: function(result, request) {
                                landing_tree.getRootNode().reload();
                                
                                Ext.getBody().mask('Done!');
                                
                                setTimeout(function(){
                                    Ext.getBody().unmask();
                                }, 750);
                            },                
                            failure: function(form, action) {
                                Ext.Msg.alert('Status', 'Folder can\'t be renamed. Please try again later.');
                            }
                        });
                    }
                }, null, false, folder.text);                    
            } else {
                Ext.simpleConfirmation.msg('Info', 'Please select a folder (not root) to rename');
            }
        }
    });
      
      var addTemplateBtn = new Ext.Toolbar.Button({
        text: '<i class="las la-plus"></i>' + _('Add Template'),
        disabled: false,
        handler: function() {
            var folder = getLandingFolderAttributes();
            if(folder) {
                showTemplate({action: 'add', folder_id: folder.folder_id});
            } else {
                Ext.simpleConfirmation.msg('Info', 'Please select parent folder where you want add new template');
            }
        }
    });
      
      var editTemplateBtn = new Ext.Toolbar.Button({
        text: '<i class="las la-edit"></i>' + _('Edit Template'),
        disabled: true,
        handler: function()
        {
            var file = getLandingFileAttributes();
              if(file) {
                  showTemplate({action: 'edit', template_id: file.file_id});
              }
        }
    });
      
      var deleteLandingBtn = new Ext.Toolbar.Button({
        text: '<i class="las la-trash"></i>' + _('Delete'),
        handler: function()
        {
            var sel = landing_tree.getSelectionModel().getSelectedNode();
            if(sel) {
                if(sel.attributes.id == 'source') {
                    Ext.simpleConfirmation.msg('Info', 'Sorry. You can\'t delete the Top Level Folder');
                    return false;
                }
                
                Ext.Msg. confirm('Please confirm', 'Are you sure you want to delete "' + sel.text + '"?', function(btn, text){
                    if (btn == 'yes') {                                        
                        Ext.Ajax.request({
                            url: baseUrl + '/forms/' + (sel.attributes.type == 'folder' ? 'landing' : 'template') + '-delete',
                            params: {
                                id: (sel.attributes.type == 'folder' ? sel.attributes.folder_id : sel.attributes.file_id)
                            },
                            success: function (result, request) {
                                landing_tree.getRootNode().reload();

                                Ext.getBody().mask('Done!');

                                setTimeout(function () {
                                    Ext.getBody().unmask();
                                }, 750);
                            },

                            failure: function (form, action) {
                                var msg = _('Internal error.');
                                try {
                                    var resultData = Ext.decode(form.responseText);
                                    msg = resultData.message;
                                } catch (e) {
                                    msg = _('Internal error.');
                                }

                                Ext.simpleConfirmation.error(msg);
                            }
                        });
                    }
                });
            } else {
                Ext.simpleConfirmation.msg('Info', 'Please select a folder to delete');
            }
        }
    });
      
      var preview1Btn = new Ext.Toolbar.Button({
        text: '<i class="las la-search"></i>' + _('Preview with All Form Versions'),
        disabled: true,
        handler: function()
        {
              preview('FULL');
        }
    });
      
      var preview2Btn = new Ext.Toolbar.Button({
        text: '<i class="las la-search"></i>' + _('Preview with Latest forms'),
        style: 'padding-left: 5px;',
        disabled: true,
        handler: function()
        {
              preview('LAST');
        }
    });
      
      var preview = function(version)    {
          var file = getLandingFileAttributes();
          if(file) {
              var closeBtn = new Ext.Button({
                  text: 'Cancel',
                  handler: function(){ win.close(); }
              });
              
              var win = new Ext.Window({
                initHidden : false,
                  title: 'Preview',
                  modal: true,
                  resizable: false,
                  width: 600,
                  autoHeight: true,
                  autoLoad: baseUrl + "/forms/get-preview?id=" + file.file_id + '&version=' + version,
                  layout: 'form',
                  cls: 'preview-window',
                  buttons: [closeBtn]
              });
              
              win.show();
              win.center();
          }
      };

    //landing_tree object (Main tree on Landing Page Tab)
    var landing_tree = new Ext.tree.TreePanel({
        useArrows: true,
        animate: true,
        autoScroll: true,
        containerScroll: true,
        bodyStyle: 'background-color:#fff',
        enableDD: true,

        root: new Ext.tree.AsyncTreeNode({
            text: 'Folders',
            expanded: true,
            id: 'source',
            cls: 'main-folder-icon'
        }),

        loader: new Ext.tree.TreeLoader({
            dataUrl: baseUrl + "/forms/get-landing-view",
            listeners: {
                load: function (obj, node, options) {
                    if (node.firstChild) {
                        node.firstChild.select();
                    }
                }
            }
        }),

        tbar: [
            addLandingFolderBtn,
            renameLandingFolderBtn,
            '-',
            deleteLandingBtn,
            '-',
            addTemplateBtn,
            editTemplateBtn,
            '-',
            preview1Btn,
            preview2Btn
        ],

        height: getSuperadminPanelHeight() - 50
    });
      
      //double click on tree node
      landing_tree.on('dblclick', function(node){
          var file = getLandingFileAttributes();
          if(file) {
              showTemplate({action: 'edit', template_id: file.file_id});
          }
      });
      
      //Drag & Drop
      landing_tree.on('dragdrop', function(panel, node, dd, e){
          Ext.Ajax.request({
            url: baseUrl + '/forms/dd',
            params:
            {
                template_id: node.attributes.file_id,
                folder_id: node.parentNode.attributes.folder_id
            },
            failure: function(form, action) {
              Ext.Msg.alert('Status', 'Can\'t drop this Document');
            }
          });
      });
      
      //single expand
      landing_tree.on('click', function(node){
          node.expand();
          
          //set allowed items
          var disabled = (node.attributes.type != 'file');
          editTemplateBtn.setDisabled(disabled);
          preview1Btn.setDisabled(disabled);
          preview2Btn.setDisabled(disabled);
      });
    
      //expand all
      landing_tree.loader.on('load', function(){
          landing_tree.expandAll();
      });      
      
    var getSelectedFormIds = function() {
        var arrSelectedFormIds = [];

        if (formsGrid) {
            var s = formsGrid.getSelectionModel().getSelections();
            if (s.length > 0) {
                for (var i = 0; i < s.length; i++) {
                    arrSelectedFormIds[arrSelectedFormIds.length] = s[i].data.form_version_id;
                }
            }
        }

        return arrSelectedFormIds;
    };
    
    var deletePdfForms = function() {
    
        var arrSelectedFormIds = getSelectedFormIds();
        if (arrSelectedFormIds.length === 0) {
            // Do nothing because no any forms are selected
            return;
        }
    
        var requestUrl = baseUrl + "/forms/delete/";
        var confirmationTimeout = 750;
        
        var strForm = (arrSelectedFormIds.length > 1) ? 'Forms' : 'Form';
        var loadingMsg = 'Deleting...';
        var failureMsg = 'Selected ' + strForm + ' cannot be deleted. Please try again later.';
        
    
        // Send ajax request to make some action with selected forms
        formsGrid.body.mask(loadingMsg);
        
        Ext.Ajax.request({
                url: requestUrl,
                params:
                {
                    arr_form_id: Ext.encode(arrSelectedFormIds)
                },
                success:function(f, o)
                {
                    var resultData = Ext.decode(f.responseText);
                    
                    if(resultData.success) {
                        // Refresh forms list
                        formsGrid.store.reload();
                        
                        // Refresh maps list
                        var mappingGrid = Ext.getCmp('mapping-main-grid');
                        if(mappingGrid) {
                            mappingGrid.store.reload();
                        }
                        
                        // Show confirmation
                        var msg = 'Done !';
                        formsGrid.body.mask(msg);
                        
                        // Hide a confirmation for a second
                        setTimeout(function(){
                            formsGrid.body.unmask();
                        }, confirmationTimeout);
                    } else {
                        // Show error message
                        formsGrid.body.unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },
                
                failure: function(){
                    Ext.simpleConfirmation.error(failureMsg);
                    formsGrid.body.unmask();
                }
        });
    };

    var forms_tab = new Ext.Panel({
        title: 'Forms',
        layout: 'border',
        style: 'padding: 6px;',
        cls: 'panel-no-scrolling',
        height: getSuperadminPanelHeight() - 35,
        items: [tree_with_combo, formsGrid]
    });

    var landing_tab = new Ext.Panel({
        title: 'Landing pages',
        autoWidth: true,
        height: getSuperadminPanelHeight() - 35,
        items: landing_tree
    });

    var fields_mapping_tab = new Ext.Panel({
        title: 'Fields Mapping (Between Family Members)',
        height: getSuperadminPanelHeight() - 35,
        items: [mappingGrid]
    });

    var arrDfTabs = [forms_tab, landing_tab, fields_mapping_tab];
    if (!booShowLandingTab) {
        arrDfTabs.splice(1, 1);
    }

    var dfTabs = new Ext.TabPanel({
        renderTo:       'forms_superadmin_container',
        activeTab:      0,
        deferredRender: false,
        frame:          false,
        plain:          true,
        cls:            'main-tabs',

        defaults: {
            autoWidth: true,
            autoScroll: true
        },

        items: arrDfTabs
    });
    
    dfTabs.on('tabchange', function(){
        var mappingGrid = Ext.getCmp('mapping-main-grid');
        if(mappingGrid) {
            mappingGrid.getView().fitColumns();
        }
    });

    $('#forms_superadmin_container').css('min-height', getSuperadminPanelHeight() + 'px');

    //show window with dialog to add/edit landing template
    function showTemplate(options)
    {
        function insertField() {
            var row = landing_forms.getSelectionModel().getSelectedNode();
            if(row && row.attributes.type == 'file') {
                           htmleditor.activated = true;
                           htmleditor.focus.defer(2, htmleditor);
                htmleditor.insertAtCursor(row.attributes.unique_form_id);
            }
        }

        var runLandingSearchForm = function() {
            var params;
            var search_field = Ext.getCmp('landing-search-form');
            if (!empty(search_field)) {
                var search_form = search_field.getValue();
                if (!empty(search_form)) {
                    params = {
                        search_form: Ext.encode(search_form)
                    };
                    Ext.apply(landing_forms.loader.baseParams, params);
                    landing_forms.getRootNode().reload();

                    landing_forms.expandAll();
                } else {
                    params = {
                        search_form: ''
                    };
                    Ext.apply(landing_forms.loader.baseParams, params);
                    landing_forms.getRootNode().reload();
                }
            }
        };
        
        var htmleditor = new Ext.ux.form.FroalaEditor({
            hideLabel: true,
            height: 600,
            width: 550,
            heightDifference: 175,
            initialWidthDifference: 40,
            widthDifference: 10,
            booAllowImagesUploading: true,
            region: 'center'
        });

        var landing_forms = new Ext.tree.TreePanel({
            useArrows: true,
            animate: true,
            autoScroll: true,
            containerScroll: true,
            collapsible: true,
            region: 'east',
            cls: 'extjs-grid',
            title: 'Forms',
            split: true,
            
            root: new Ext.tree.AsyncTreeNode({
                text: 'Folders',
                singleClickExpand: true,
                id: 'source',
                cls: 'main-folder-icon'
            }),
            
            loader: new Ext.tree.TreeLoader({
                dataUrl: baseUrl + "/forms/list",
                preloadChildren: false,

                listeners: {
                    'load': function () {
                        fields_fs.syncSize();
                    }
                }
            }),
            
            width: 310,
            height: 600,

            bbar: [new Ext.Panel({
                id: 'landing-forms-toolbar',
                layout: 'form',
                width: 300,
                height: 50,
                layoutConfig: {
                    columns: 2
                },
                style: 'background-color: #d9e5f4; vertical-align: middle;',
                ctCls: 'x-toolbar-cell-no-right-padding',
                items: [
                    {
                        layout: 'table',
                        width: 300,
                        layoutConfig: {
                            columns: 3
                        },
                        items: [
                            {
                                xtype: 'label',
                                html: 'Search:',
                                style: 'margin-top: 5px;'
                            },
                            {
                                id: 'landing-search-form',
                                xtype: 'textfield',
                                fieldLabel: 'Search',
                                style: 'margin-left:5px;',
                                emptyText: site_version == 'australia' ? 'eg. 457, 80, etc...' : 'eg. 5406, 1344, etc...',
                                width: 160,
                                enableKeyEvents: true,
                                listeners: {
                                    keyup: function(field, e) {
                                        if (e.getKey() == Ext.EventObject.ENTER) {
                                            runLandingSearchForm();
                                        }
                                    }
                                }
                            }, {
                                xtype: 'button',
                                id: 'landing-btn-search',
                                iconCls: 'forms-btn-icon-search',
                                cls: 'x-btn-text-icon',
                                style: 'margin-left:3px;',
                                handler: function() {
                                    runLandingSearchForm();

                                }
                            }
                        ]
                    }, {
                        layout: 'table',
                        width: 300,
                        layoutConfig: {
                            columns: 2
                        },
                        items: [
                            {
                                xtype: 'button',
                                text: '&nbsp;&nbsp;<<&nbsp;&nbsp;',
                                pressed: true,
                                style: 'padding-bottom:4px;',
                                handler: function() {
                                    insertField(id);
                                }
                            }, {
                                xtype: 'label',
                                style: 'margin: 5px 0px 0px 5px;',
                                html: 'Double click on a form name to insert'
                            }
                        ]
                    }
                ]
            })]
        });
          
          landing_forms.on('dblclick', function() {
            insertField();
        });

          landing_forms.on('show', function() {
            // fields_fs.syncSize();
        });

        landing_forms.on('expandnode', function(node) {
            var arrChildren = node.childNodes;
            for (var i=0; i<arrChildren.length; i++) {
                if (arrChildren[i].attributes.type == 'file') {
                    var qtip = 'Version Date: ' + arrChildren[i].attributes.version_date + '; Uploaded Date: ' + arrChildren[i].attributes.uploaded_date;
                    arrChildren[i].ui.textNode.setAttribute('ext:qtip', qtip);
                }
            }
        });

        var name = new Ext.form.TextField({
            fieldLabel: 'Template Name',
            anchor: '100%'
        });

        var fields_fs = new Ext.form.FieldSet({
            layout: 'border',
            autoHeight: true,
            cls: 'templates-fieldset',
            items: [htmleditor, landing_forms]
        });

        var pan = new Ext.FormPanel({
            itemCls: 'templates-sub-tab-items',
            cls: 'templates-sub-tab',
            bodyStyle: 'padding:0px',
            labelWidth: 120,
            autoHeight: true,
            items: [name, fields_fs]
        });
        
        var saveBtn = new Ext.Button({
            text: 'Save Template',
            cls: 'orange-btn',
            handler: function()
            {
                var t_name = name.getValue();
                var t_body = htmleditor.getValue();
                
                if(empty(t_name)) {
                    name.markInvalid('This field is required');
                    return false;
                }
                
                if(empty(t_body)) {
                    htmleditor.markInvalid('THis field is required');
                    return false;
                }
                
                Ext.Ajax.request({
                    url: baseUrl + "/forms/template-save",
                    params: 
                    {
                        act: options.action,
                        template_id: options.template_id,
                        folder_id: options.folder_id,
                        name: Ext.encode(t_name),
                        body: Ext.encode(t_body)
                    },
                    success: function(f, o)
                    {  
                        Ext.getBody().unmask();
                        win.close();
                        landing_tree.getRootNode().reload();
                        Ext.simpleConfirmation.msg('Info', 'Template ' + (options.action == 'add' ? 'added' : 'edited'));
                    },
                    failure: function(f, o)
                    { 
                        Ext.Msg.alert('Status', 'Can\'t load Template Content');
                        Ext.getBody().unmask();
                    }
                });
            }
        });
        
        var closeBtn = new Ext.Button({
            text: 'Cancel',
            handler: function(){ win.close(); }
        });
        
          var win = new Ext.Window({
            y: 10,
            title: (options.action == 'add' ? 'Add New' : 'Edit') + ' Template',
            modal: true,
            resizable: false,
            width: 900,
            autoHeight: true,
            layout: 'form',
            items: pan,
            buttons: [closeBtn, saveBtn]
        });

        win.show();

        //edit action
        if(options.action == 'edit')
        {
            win.getEl().mask('Loading...');
            
            //set default values
            Ext.Ajax.request({
                url: baseUrl + "/forms/get-template-info?id=" + options.template_id,
                success: function(f, o)
                {
                    //get values
                    var result = Ext.decode(f.responseText);

                    name.setValue(result.name);
                    htmleditor.setValue(result.body);

                    fields_fs.syncSize();
                  
                    win.getEl().unmask();
                },
                failure: function(f, o)
                { 
                  Ext.Msg.alert('Status', 'Can\'t load Template Content');
                  win.getEl().unmask();
                }
              });
        } else {
            fields_fs.syncSize();
        }
    }

    function checkForms()
    {
        var formRecord = Ext.data.Record.create([
            {name: 'id', type: 'int'},
            {name: 'name'}
        ]);

        var formsStore = new Ext.data.Store({
            // load using HTTP
            url: baseUrl + '/forms/check-forms',
            autoLoad: true,
            remoteSort: false,
            reader: new Ext.data.JsonReader({
                    id: 'id',
                    root:'rows',
                    totalProperty:'totalCount'
                }, formRecord)
        });

        var pagingBar = new Ext.PagingToolbar({
            pageSize: 25,
            store: formsStore,
            displayInfo: true,
            displayMsg: 'Displaying forms {0} - {1} of {2}',
            emptyMsg: "No forms to display"
        });

        var grid = new Ext.grid.GridPanel({
            height: getSuperadminPanelHeight() - 50,
            width: 600,
            region: 'center',
            autoWidth: true,
            store: formsStore,
            bodyStyle: 'padding: 0px; background-color:#fff;',
            style: 'padding: 0 5px;',
            loadMask: true,
            cm: new Ext.grid.ColumnModel([
                {header: "Form Version Id", sortable: true, dataIndex: 'id', width: 100},
                {header: "Form Name", sortable: true, dataIndex: 'name', width: 500}
            ]),
            viewConfig: { emptyText: 'No forms found.' },
            bbar: pagingBar
        });

        var pan = new Ext.FormPanel({
            itemCls: 'templates-sub-tab-items',
            cls: 'templates-sub-tab',
            bodyStyle: 'padding:0px',
            autoHeight: true,
            items: [grid]
        });

        var closeBtn = new Ext.Button({
            text: 'Close',
            handler: function(){ win.close(); }
        });

        var win = new Ext.Window({
            initHidden : false,
            title: 'Forms Without PDF Files',
            modal: true,
            resizable: false,
            autoWidth: true,
            autoHeight: true,
            layout: 'form',
            items: pan,
            buttons: [closeBtn]
        });

        win.show();
        win.center();


    }
});