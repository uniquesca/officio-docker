Ext.onReady(function(){
    var recordsOnPage = 10;
    $('#admin-manage-news-feed').css('min-height', getSuperadminPanelHeight() + 'px');

    var sm = new Ext.grid.CheckboxSelectionModel({
        listeners: {
            selectionchange: function(){
                var sel=grid.getSelectionModel().getSelections();

                var booSelected = sel.length>0;
                var booSelectedOne = sel.length==1;

                Ext.getCmp('black-list-button-edit').setDisabled(!booSelectedOne);
                Ext.getCmp('black-list-button-delete').setDisabled(!booSelected);
            }
        }
    });
    
    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm,
            {
                header: 'ID',
                dataIndex: 'id',
                width: 35,
                fixed: true
            }, {
                header: 'Domain',
                dataIndex: 'domain'
            }
        ],
        defaultSortable: true
    });
    
    var store = new Ext.data.Store({
        url: baseUrl + '/manage-rss-feed/get-list/',
        baseParams: { 
            start: 0,
            limit: recordsOnPage
        },
        autoLoad: true,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'results',
            totalProperty: 'count'
        }, Ext.data.Record.create([{name: 'id'},
                                   {name: 'domain'}]))
    });
    
    var pagingBar = new Ext.PagingToolbar({
        pageSize: recordsOnPage,
        store: store
    });
    
    var grid = new Ext.grid.GridPanel({
        renderTo: 'admin-manage-news-feed',
        id: 'admin-manage-news-feed-grid',
        store: store,
        cm: cm, 
        sm: sm,
        autoHeight: true,
        split: true,
        stripeRows: true,
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        viewConfig: { emptyText: 'No domains found.', forceFit: true },
        bbar: pagingBar,
        tbar: [{
            text: '<i class="las la-plus"></i>' + _('Add'),
            handler: function() {
                editBlackListItem();
            }
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit'),
            id: 'black-list-button-edit',
            disabled: true,
            handler: function() {
                var sel=grid.getSelectionModel().getSelections();

                if (sel.length!==1)
                    return;

                editBlackListItem(sel[0].data);
            }
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete'),
            id: 'black-list-button-delete',
            disabled: true,
            handler: function() {
                var sel=grid.getSelectionModel().getSelections();

                if (sel.length<1)
                    return;

                deleteBlackListItem();
            }
        } ]
    });
});

function editBlackListItem(data)
{
    var domain_id = new Ext.form.Hidden({
        id: 'domain_id',
        value: data ? data.id : ''
    });
    var domain = new Ext.form.TextField({
        id: 'domain-name',
        value: data ? data.domain : '',
        width: 400,
        allowBlank: false
    });

    var sendBtn = new Ext.Button({
        text: 'Save',
        cls: 'orange-btn',
        handler: function () {
            if (domain.isValid())
            {
                win.getEl().mask('Saving...');

                Ext.Ajax.request({
                url: baseUrl + '/manage-rss-feed/edit/',
                params: {
                    id: domain_id.getValue(),
                    domain: domain.getValue()
                },
                success: function (result) {
                    var resultData = Ext.decode(result.responseText);

                    if (resultData.success)
                    {
                        win.close();
                        Ext.simpleConfirmation.msg('Info', "Domain successfully added");

                        Ext.getCmp('admin-manage-news-feed-grid').store.reload();
                    }
                    else
                    {
                        win.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error('Can\'t delete these domains');
                }
            });
            }
        }
    });

    var cancelBtn = new Ext.Button({
        text: 'Cancel',
        handler: function () {
            win.close();
        }
    });

    var win = new Ext.Window({
        title: empty(domain_id.getValue()) ? '<i class="las la-plus"></i>' + _('Add domain to black list') : '<i class="las la-edit"></i>' + _('Edit domain'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        items: [domain_id, domain],
        buttons: [cancelBtn, sendBtn],
        listeners: {
            render: function(){
                setTimeout(function(){ domain.focus(); }, 200);
            }
        }
    });

    win.show();
}

function deleteBlackListItem()
{
    var grid=Ext.getCmp('admin-manage-news-feed-grid');

    Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete these domains?', function (btn, text) {
        if (btn == 'yes')
        {
            var sel=grid.getSelectionModel().getSelections();

            if(empty(sel.length))
                return false;

            Ext.getBody().mask('Deleting...');

            var ids=[];
            for(var i=0; i<sel.length; i++)
                ids.push(sel[i].data.id);

            Ext.Ajax.request({
                url: baseUrl + '/manage-rss-feed/delete/',
                params: {
                    ids: Ext.encode(ids)
                },
                success: function (result) {
                    Ext.getBody().unmask();

                    var resultData = Ext.decode(result.responseText);

                    if (resultData.success)
                    {
                        grid.store.reload();
                        Ext.simpleConfirmation.msg('Info', "Domains successfully deleted");
                    }
                    else
                        Ext.simpleConfirmation.error(resultData.message);
                },
                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error('Can\'t delete these domains');
                }
            });
        }
    });
}