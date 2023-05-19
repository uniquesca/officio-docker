function refreshList() {
    Ext.getCmp('letterheads-grid').store.reload();
}

function openLetterheadFile(letterheadId, fileNumber) {
    window.open(baseUrl + '/letterheads/get-letterhead-file?letterhead=' + letterheadId + '&file=' + fileNumber + '&small=0');
}

function letterhead(params) {
    var same_subsequent = false;
    $('.form-image a[data-rel=change]').on('click', function(){
        var parent = $(this).closest('.file-fieldset');

        parent.find('.form-image-view').hide();
        parent.find('.form-image-view').removeClass('visible');
        parent.find('.form-image-edit').show();

        // cancel button
        $('.form-image a[data-rel=cancel]').click(function() {
            var parent = $(this).closest('.file-fieldset');
            parent.find('.form-image-input').val('');
            parent.find('.form-image-edit').hide();
            parent.find('.form-image-view').show();
            parent.find('.form-image-view').addClass('visible');
        });

        return false;
    });

    subsequentPages = function() {
        var $this = $('#same_as_first_page_checkbox');
        var secondImageView = $('#second_image_view');
        if ($this.is(':checked')) {
            $('#second_panel_image').hide();
            secondImageView.hide();
            secondMarginLeft.hide();
            secondMarginRight.hide();
            secondMarginTop.hide();
            secondMarginBottom.hide();
            win.syncShadow();
        } else {
            $('#second_panel_image').show();
            if (!same_subsequent && !$('#second-file-upload').is(':visible')) {
                secondImageView.show();
            }
            secondMarginLeft.show();
            secondMarginRight.show();
            secondMarginTop.show();
            secondMarginBottom.show();
        }
    };

    if (params.action == 'add' || params.action == 'edit') {
        var nameField = new Ext.form.TextField({
            name: 'name',
            fieldLabel: 'Name',
            width: 200
        });

        var typeCombo = new Ext.form.ComboBox({
            name: 'type',
            store: new Ext.data.SimpleStore({
                fields: ['type', 'typeName'],
                data: [
                    ['a4', 'A4'], ['letter', 'Letter']
                ]
            }),
            mode: 'local',
            displayField: 'typeName',
            valueField: 'type',
            typeAhead: false,
            allowBlank: false,
            triggerAction: 'all',
            selectOnFocus: false,
            editable: true,
            grow: true,
            fieldLabel: 'Type',
            width: 200,
            listWidth: 200
        });
        
        var firstFileHtml = '';
        if (params.action == 'add') {
            firstFileHtml = String.format(
                '<div class="form-image-edit" style="font-size: 12px; padding-bottom: 5px;">' +
                'File: ' +
                '<input type="file" id="first-file-upload" name="{0}" class="form-image-input" style="padding-left: 60px;"/>' +
                '</div>',
                'letterhead-upload-file-1'
            );
        } else {
            firstFileHtml = String.format(
                '<div class="form-image-view hidden">' +
                '<div style="float: left; font-size: 12px; padding-bottom: 5px;">' +
                '<div class="form-image-links">' +
                'File: ' +
                '<a href="#" class="blulinkunb" data-rel="change" onclick="return false" style="padding-left: 80px;">change </a>' +
                '</div>' +
                '</div>' +

            '</div>' +

                '<div class="form-image-edit" style="font-size: 12px; padding-bottom: 5px;">' +
                'File: ' +
                '<input type="file" id="first-file-upload" name="{0}" class="form-image-input" style="padding-left: 60px;"/>' +

                '<a href="#" class="blulinkunb" data-rel="cancel" onclick="return false">cancel</a>' +
                '</div>',
                'letterhead-upload-file-1'
            );
        }

        var firstFile = {
            id: 'first_panel_image',
            cls: 'form-image',
            xtype: 'panel',
            html: firstFileHtml
        };

        var firstMarginLeft = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-left-1',
            fieldLabel: 'Left Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var firstMarginRight = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-right-1',
            fieldLabel: 'Right Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var firstMarginTop = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-top-1',
            fieldLabel: 'Top Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var firstMarginBottom = new Ext.form.NumberField({
            name: 'margin-bottom-1',
            xtype: 'numberfield',
            fieldLabel: 'Bottom Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var secondFileHtml = '';
        if (params.action == 'add') {
            secondFileHtml = String.format(
                '<div class="form-image-edit" style="font-size: 12px; padding-bottom: 5px;">' +
                'File: ' +
                '<input type="file" id="second-file-upload" name="{0}" class="form-image-input" style="padding-left: 60px;"/>' +
                '</div>',
                'letterhead-upload-file-2'
            );
        } else {
            secondFileHtml = String.format(
                '<div class="form-image-view hidden">' +
                '<div style="float: left; font-size: 12px; padding-bottom: 5px;">' +
                '<div class="form-image-links">' +
                'File: ' +
                '<a href="#" class="blulinkunb" data-rel="change" onclick="return false" style="padding-left: 80px;">change </a>' +
                '</div>' +
                '</div>' +
                '</div>' +

                '<div class="form-image-edit" style="font-size: 12px; padding-bottom: 5px;">' +
                'File: ' +
                '<input type="file" id="second-file-upload" name="{1}" class="form-image-input" style="padding-left: 60px;"/>' +

                '<a href="#" class="blulinkunb" data-rel="cancel" onclick="return false">cancel</a>' +
                '</div>',
                params.letterhead_id, 'letterhead-upload-file-2'
            );
        }

        var secondCheckbox = {
            xtype: 'panel',
            html: '<div style="float: left; font-size: 12px; padding-bottom: 5px;">' +
                '<input id="same_as_first_page_checkbox" type="checkbox" value="a2" onclick="subsequentPages();">&nbsp;<label for="same_as_first_page_checkbox">Same as first page</label>' +
                '</div>'
        };

        var secondFile = {
            id: 'second_panel_image',
            cls: 'form-image',
            xtype: 'panel',
            html: secondFileHtml
        };

        var secondMarginLeft = new Ext.form.NumberField({
            xtype: 'numberfield',
            id: 'margin-left-2',
            name: 'margin-left-2',
            fieldLabel: 'Left Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var secondMarginRight = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-right-2',
            fieldLabel: 'Right Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var secondMarginTop = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-top-2',
            fieldLabel: 'Top Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });

        var secondMarginBottom = new Ext.form.NumberField({
            xtype: 'numberfield',
            name: 'margin-bottom-2',
            fieldLabel: 'Bottom Margin',
            width: 75,
            maxLength: 16,
            minValue: 1,
            allowDecimals: false,
            allowNegative: false
        });
        var firstImageView = '';
        if (params.action == 'edit') {
            firstImageView = String.format(
                '<div id="first_image_view" class="form-image-view visible" style="float: left; position: absolute; top: 25px; left: 230px;"><img src="{0}" data-path="" hspace="2" vspace="2" border="0" align="bottom" alt="" style=" box-shadow: 0 0 5px;"/></div>',
                baseUrl + '/letterheads/get-letterhead-file?letterhead=' + params.letterhead_id + '&file=1&small=1&' + Date.now()
            );
        }

        var firstMarginsFieldSet = new Ext.form.FieldSet({
            title: 'First Page',
            cls: 'file-fieldset',
            columnWidth: 0.5,
            bodyStyle: 'padding: 5px',
            style: 'padding: 5px;',
            html: firstImageView,
            items: [
                firstFile,
                firstMarginLeft,
                firstMarginRight,
                firstMarginTop,
                firstMarginBottom
            ]
        });

        var secondImageView = '';
        if (params.action == 'edit') {
            secondImageView = String.format(
                '<div id="second_image_view" class="form-image-view visible" style="float: left; position: absolute; top: 25px; left: 585px;"><img src="{0}" data-path="" hspace="2" vspace="2" border="0" align="bottom" alt="" style=" box-shadow: 0 0 5px;"/></div>',
                baseUrl + '/letterheads/get-letterhead-file?letterhead=' + params.letterhead_id + '&file=2&small=1&' + Date.now() + 1
            );
        }

        var secondMarginsFieldSet = new Ext.form.FieldSet({
            title: 'Subsequent Pages',
            cls: 'file-fieldset',
            columnWidth: 0.5,
            bodyStyle: 'padding: 5px',
            style: 'padding: 5px; margin-left: 5px;',
            html: secondImageView,
            items: [
                secondCheckbox,
                secondFile,
                secondMarginLeft,
                secondMarginRight,
                secondMarginTop,
                secondMarginBottom
            ]
        });

        var pan = new Ext.FormPanel({
            bodyStyle: 'padding:5px',
            fileUpload: true,
            id: 'letterhead-panel',
            layout: 'form',
            items: [
                nameField,
                typeCombo,
                {
                    layout: 'column',
                    items: [
                        firstMarginsFieldSet,
                        secondMarginsFieldSet
                    ]
                }
            ]
        });

        var addSaveBtn = new Ext.Button({
            text: 'Save',
            cls: 'orange-btn',
            handler: function () {
                var booSubsequentSameAsFirst = $('#same_as_first_page_checkbox').is(':checked');
                var obj = Ext.getCmp('letterhead-panel');
                if (empty(nameField.getValue())) {
                    Ext.simpleConfirmation.warning('Please enter name.');
                    return;
                }
                if (empty(typeCombo.getValue())) {
                    Ext.simpleConfirmation.warning('Please select type.');
                    return;
                }
                if (params.action == 'add' && empty($('#first-file-upload').val())) {
                    Ext.simpleConfirmation.warning('Please select file for a first page.');
                    return;
                }
                if (empty(firstMarginLeft.getValue())) {
                    Ext.simpleConfirmation.warning('Please enter left margin for a first page.');
                    return;
                }
                if (empty(firstMarginRight.getValue())) {
                    Ext.simpleConfirmation.warning('Please enter right margin for a first page.');
                    return;
                }
                if (empty(firstMarginTop.getValue())) {
                    Ext.simpleConfirmation.warning('Please enter top margin for a first page.');
                    return;
                }
                if (empty(firstMarginBottom.getValue())) {
                    Ext.simpleConfirmation.warning('Please enter bottom margin for a first page.');
                    return;
                }
                if (!booSubsequentSameAsFirst) {
                    var secondFileUpload = $('#second-file-upload');
                    if ((params.action == 'add' && empty(secondFileUpload.val())) || (empty(secondFileUpload.val()) && same_subsequent)) {
                        Ext.simpleConfirmation.warning('Please select file for a second page.');
                        return;
                    }
                    if (empty(secondMarginLeft.getValue())) {
                        Ext.simpleConfirmation.warning('Please enter left margin for a second page.');
                        return;
                    }
                    if (empty(secondMarginRight.getValue())) {
                        Ext.simpleConfirmation.warning('Please enter right margin for a second page.');
                        return;
                    }
                    if (empty(secondMarginTop.getValue())) {
                        Ext.simpleConfirmation.warning('Please enter top margin for a second page.');
                        return;
                    }
                    if (empty(secondMarginBottom.getValue())) {
                        Ext.simpleConfirmation.warning('Please enter bottom margin for a second page.');
                        return;
                    }
                }
                win.getEl().mask('Saving...');
                obj.getForm().submit({
                    url: baseUrl + '/letterheads/save-letterhead',
                    params: {
                        same_subsequent: booSubsequentSameAsFirst ? 1 : 0,
                        type_action: params.action,
                        letterhead_id: params.action == 'add' ? '' : params.letterhead_id
                    },
                    success: function (form, action) {
                        var result = action.result;
                        if (!empty(result.error)) {
                            win.getEl().unmask();
                            Ext.simpleConfirmation.error(result.error);
                        } else {
                            Ext.simpleConfirmation.success('Letterhead was successfully saved.');
                            win.close();
                            refreshList();
                        }
                    },

                    failure: function(form, action) {
                        var msg = action && action.result && action.result.error ? action.result.error : 'Error happened during file(s) uploading. Please try again later.';

                        win.getEl().unmask();
                        Ext.simpleConfirmation.error(msg);
                    }

                });
            }
        });

        var closeBtn = new Ext.Button({
            text: 'Cancel',
            handler: function () {
                win.close();
            }
        });

         var win = new Ext.Window({
             title: params.action == 'add' ? _('New Letterhead') : _('Edit Letterhead'),
             layout: 'form',
             modal: true,
             width: 730,
             autoHeight: true,
             resizable: false,
             items: pan,
             buttons: [closeBtn, addSaveBtn]
         });
        win.show();
        win.center();
        win.syncShadow();

        if (params.action == 'edit') {
            $('.form-image-edit').hide();
            win.syncShadow();
            win.getEl().mask('Loading...');
            //get note detail info
            Ext.Ajax.request({
                url: baseUrl + '/letterheads/get-letterhead',
                params: {
                    letterhead_id: params.letterhead_id
                },
                success: function (result) {
                    win.getEl().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        var letterhead = resultDecoded.letterhead;
                        if (letterhead.same_subsequent) {
                            same_subsequent = true;
                            secondImageView = $('#second_image_view');
                            var parent = secondImageView.closest('.file-fieldset');
                            parent.find('.form-image-view').hide();
                            parent.find('.form-image-view').removeClass('visible');
                            parent.find('a[data-rel=cancel]').remove();
                            parent.find('.form-image-edit').show();
                            $('#same_as_first_page_checkbox').prop('checked', true);
                            $('#second_panel_image').hide();
                            secondImageView.hide();
                            secondMarginLeft.hide();
                            secondMarginRight.hide();
                            secondMarginTop.hide();
                            secondMarginBottom.hide();
                        }
                        nameField.setValue(letterhead.name);
                        typeCombo.setValue(letterhead.type);
                        firstMarginLeft.setValue(letterhead.first_margin_left);
                        firstMarginRight.setValue(letterhead.first_margin_right);
                        firstMarginTop.setValue(letterhead.first_margin_top);
                        firstMarginBottom.setValue(letterhead.first_margin_bottom);
                        if (!letterhead.same_subsequent) {
                            secondMarginLeft.setValue(letterhead.second_margin_left);
                            secondMarginRight.setValue(letterhead.second_margin_right);
                            secondMarginTop.setValue(letterhead.second_margin_top);
                            secondMarginBottom.setValue(letterhead.second_margin_bottom);
                        }
                    } else {
                        win.close();
                        Ext.simpleConfirmation.error('This letterhead has invalid information and cannot be opened.<br/>Please notify Officio support with the details.');
                    }
                    win.syncShadow();
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.Msg.alert('Status', 'Can\'t load letterhead information');
                }
            });
        }
    } else {
        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this letterhead?', function (btn) {
            if (btn == 'yes') {
                Ext.Ajax.request({
                    url: baseUrl + '/letterheads/delete',
                    params: {
                        letterhead_id: params.letterhead_id
                    },
                    success: function (result) {
                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            refreshList();
                        } else {
                            Ext.simpleConfirmation.error('This letterhead can\'t be deleted. Please try again later.');
                        }
                    },
                    failure: function () {
                        Ext.Msg.alert('Status', 'This letterhead can\'t be deleted. Please try again later.');
                    }
                });
            }
        });
    }


    return false;
}