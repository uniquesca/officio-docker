var note_file_attachments = [];

function removeNoteAttachment(el) {
    var id = $(el).parent().attr('id');
    $(el).parent().remove();

    for (var i = 0; i < note_file_attachments.length; i++)
    {
        if (note_file_attachments[i]['attach_id'] == id)
        {
            note_file_attachments.splice(i, 1);
            break;
        }
    }

}

function reloadNotesForm(memberId, type, tabType) {
    var gridId = type == 'prospect' ? 'prospect-notes-grid-' + memberId : tabType + '-notes-grid-' + memberId;
    var grid = Ext.getCmp(gridId);
    if (grid) {
        grid.getStore().reload();
    }
}

function note(params) {
    note_file_attachments = [];
    if (params.action != 'delete') //add or edit action
    {
        var notesTextarea = new Ext.form.TextArea({
            fieldLabel: 'Note',
            width: 720,
            height: 350
        });

        var attachmentsPanel = new Ext.Panel({
            id: 'attachments-panel',
            hidden: params.type == 'homepage',
            fieldLabel: 'Attachments',
            style: {
                width: '100%',
                'padding-top': '10px'
            },
            authHeight: true,
        });

        var hideVisibleToClients = (['client', 'homepage', 'prospect', 'superadmin'].has(params.type));

        var visibleToClients = new Ext.form.Checkbox({
            fieldLabel: 'Visible to Client',
            labelStyle: 'width: 110px; padding-top: 3px',
            hidden: hideVisibleToClients,
            checked: (params.type == 'client')
        });


        var rtlText = _('Right-to-left') + '<i class="las la-align-right"></i>';
        var ltrText = _('Left-to-right') + '<i class="las la-align-left"></i>';

        var rtl = new Ext.Button({
            text: ltrText,
            tooltip: _('Switch direction of the text.'),
            enableToggle: true,
            hidden: params.type == 'prospect',
            toggleHandler: function (obj, checked) {
                if (checked) {
                    notesTextarea.addClass('rtl');
                    this.setText(rtlText);
                } else {
                    notesTextarea.removeClass('rtl');
                    this.setText(ltrText);
                }
            }
        });

        var pan = new Ext.FormPanel({
            bodyStyle: 'padding:5px',
            labelWidth: 110,
            items: [
                notesTextarea,
                attachmentsPanel,
                {
                    layout: 'column',
                    items: [
                        {
                            width: 140,
                            layout: 'form',
                            labelWidth: 110,
                            items: visibleToClients
                        },
                        {
                            html: '&nbsp;',
                            width: hideVisibleToClients ? 705 : 560
                        },
                        {
                            layout: 'form',
                            items: rtl,
                            align: 'right'
                        }
                    ]
                }
            ]
        });

        var note_color = params.action == 'add' ? '0' : '';

        var attachBtn = new Ext.Button({
            text: 'Attach Files',
            cls: 'secondary-btn',
            hidden: params.type == 'homepage',
            handler: function () {
                var dialog = new UploadNotesAttachmentsDialog({
                    settings: {
                        note_id: params.note_id,
                        act: params.action,
                        type: params.type,
                        tabType: Ext.encode(params.tabType),
                        company_id: params.company_id,
                        member_id: params.member_id
                    }
                });

                dialog.show();
            }
        });

        var addSaveBtn = new Ext.Button({
            text: 'Save',
            cls:  'orange-btn',

            handler: function () {
                var text = notesTextarea.getValue();
                if (empty(text)) {
                    notesTextarea.markInvalid();
                    return;
                }

                if (HtmlTagSearch(text)) {
                    notesTextarea.markInvalid('Html tags are not allowed');
                    return;
                }

                //save msg
                win.getEl().mask('Saving...');

                var url = '';
                if (params.type == 'prospect') {
                    if (params.action == 'add') {
                        url = topBaseUrl + '/prospects/index/notes-add'
                    } else {
                        url = topBaseUrl + '/prospects/index/notes-edit'
                    }

                } else {
                    url = topBaseUrl + '/notes/index/' + params.action;
                }

                //save
                Ext.Ajax.request({
                    url: url,
                    params: {
                        note_id: params.note_id,
                        member_id: params.member_id,
                        company_id: params.company_id,
                        note: Ext.encode(text),
                        visible_to_clients: visibleToClients.getValue(),
                        rtl: rtl.pressed,
                        note_color: note_color,
                        type: Ext.encode(params.tabType),
                        note_file_attachments: Ext.encode(note_file_attachments),
                    },
                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);
                        if (resultData.success) {
                            if (params.type == 'homepage') {
                                showHomepageNotes();
                            } else {
                                reloadNotesForm(params.member_id, params.type, params.tabType);
                            }

                            win.getEl().mask('Done!');

                            setTimeout(function () {
                                win.getEl().unmask();
                                win.close();
                            }, 750);
                        } else {
                            win.getEl().unmask();
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },
                    failure: function () {
                        Ext.Msg.alert('Status', 'Saving Error');
                        win.getEl().unmask();
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
            title: params.action == 'add' ? '<i class="las la-plus"></i>' + _('New Note') : '<i class="lar la-edit"></i>' + _('Edit Note'),
            layout: 'form',
            modal: true,
            width: 850,
            y: 10,
            autoHeight: true,
            resizable: false,
            items: pan,
            buttons: [closeBtn, attachBtn, addSaveBtn],
            listeners: {
                beforeshow: function () {
                    notesTextarea.focus.defer(100, notesTextarea);
                }
            }
        });

        win.show();
        notesTextarea.focus.defer(100, notesTextarea);

        //if edit action set default values
        if (params.action == 'edit') {
            //save msg
            win.getEl().mask(_('Loading...'));

            //get note detail info
            Ext.Ajax.request({
                url: params.type == 'prospect' ? topBaseUrl + '/prospects/index/get-note' : topBaseUrl + '/notes/index/get-note',
                params: {
                    note_id: params.note_id,
                    type: Ext.encode(params.tabType),
                    member_id: params.member_id,
                },
                success: function (result) {
                    win.getEl().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        var note = Ext.decode(result.responseText).note;
                        notesTextarea.setRawValue(Ext.util.Format.stripTags(note.note));
                        visibleToClients.setValue(note.visible_to_clients);
                        rtl.toggle(note.rtl);

                        if (note.rtl) {
                            notesTextarea.addClass('rtl');
                        } else {
                            notesTextarea.removeClass('rtl');
                        }

                        var booIsSystemNote = !empty(note.is_system);

                        Ext.each(resultDecoded.note.file_attachments, function (v) {
                            note_file_attachments.push({
                                attach_id: v['id'],
                                file_id: v['file_id'],
                                name: v['name'],
                                size: v['size']
                            });

                            var downloadUrl = params.type == 'prospect' ? topBaseUrl + '/prospects/index/download-attachment' : topBaseUrl + '/notes/index/download-attachment';

                            var downloadAttachmentLinkId = Ext.id();
                            var downloadAttachmentLink = String.format(
                                '<a id="{0}" class="bluelink" href="#">{1}</a>',
                                downloadAttachmentLinkId,
                                v['name']
                            );

                            var deleteAttachment = '';
                            if (!booIsSystemNote) {
                                deleteAttachment = ' <img src="' + topBaseUrl + '/images/deleteicon.gif" class="template-attachment-cancel" onclick="removeNoteAttachment(this); return false;" alt="Delete" />';
                            }

                            $('#attachments-panel').append('<div style="display: inline; padding-right: 7px;" id="' + v['id'] + '">' + downloadAttachmentLink + ' <span style="font-size: 11px;">(' + v['size'] + ')</span>' + deleteAttachment + '</div>');

                            $('#' + downloadAttachmentLinkId).on('click', function () {
                                var oParams = {
                                    note_id: params.note_id,
                                    member_id: params.member_id,
                                    type: 'note_file_attachment',
                                    attach_id: v['file_id'],
                                    name: v['name']
                                }

                                submit_hidden_form(downloadUrl, oParams);
                                return false;
                            });
                        });

                        notesTextarea.focus.defer(100, notesTextarea);

                        if (booIsSystemNote) {
                            closeBtn.setText(_('Close'));
                            rtl.setVisible(false);
                            attachBtn.setVisible(false);
                            addSaveBtn.setVisible(false);
                        }
                    } else {
                        win.close();
                        Ext.simpleConfirmation.error(resultDecoded.message);
                    }
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.Msg.alert('Status', 'Can\'t load note information');
                }
            });
        }
    } else //delete action
    {
        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this Note' + (params.notes ? 's' : '') + '?', function (btn) {
            if (btn == 'yes') {
                Ext.Ajax.request({
                    url: params.type == 'prospect' ? topBaseUrl + '/prospects/index/notes-delete' : topBaseUrl + '/notes/index/delete',
                    params: {
                        notes: Ext.encode(params.notes ? params.notes : params.note_id),
                        type:  Ext.encode(params.tabType)
                    },

                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);
                        if (resultData.success) {
                            if (params.type == 'homepage') {
                                $('#note_tr_' + params.note_id).remove();
                                if ($('#notesForm').find('tr').length === 0) {
                                    showHomepageNotes();
                                }
                            } else {
                                reloadNotesForm(params.member_id, params.type, params.tabType);
                            }
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function () {
                        Ext.Msg.alert('Status', 'This Note(s) cannot be deleted. Please try again later.');
                    }
                });
            }
        });
    }

    return false;
}
