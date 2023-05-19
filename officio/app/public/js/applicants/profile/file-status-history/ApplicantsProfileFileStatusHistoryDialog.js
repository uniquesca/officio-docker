var ApplicantsProfileFileStatusHistoryDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisDialog = this;
    var genId = Ext.id();
    var fileStatusColId = Ext.id();
    this.fileStatusHistoryGrid = new Ext.grid.GridPanel({
        id: genId,
        width: 800,
        height: 300,
        autoScroll: true,
        stripeRows: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: fileStatusColId,

        viewConfig: {
            deferEmptyText: _('Case File Status in legacy cases is not tracked.'),
            emptyText: _('Case File Status in legacy cases is not tracked.'),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 20,
            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth) {
                    // no scroller
                    this.scrollOffset = 2;
                } else {
                    // there is a scroller
                    this.scrollOffset = this.orgScrollOffset;
                }
                this.fitColumns(false);
            }
        },

        store: new Ext.data.Store({
            autoLoad: true,
            remoteSort: false,

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/applicants/profile/get-case-file-status-history',
            }),

            sortInfo: {
                field: 'history_changed_on_date',
                direction: 'DESC'
            },

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'history_id',

                fields: [
                    'history_id',
                    'history_client_status_name',
                    'history_checked_user_name',
                    'history_unchecked_user_name',
                    'history_days',
                    {name: 'history_changed_on_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
                    {name: 'history_checked_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
                    {name: 'history_unchecked_date', type: 'date', dateFormat: Date.patterns.ISO8601Long}
                ]
            }),

            listeners: {
                'beforeload': function (store, options) {
                    options.params = options.params || {};

                    var params = {
                        caseId: Ext.encode(config.caseId)
                    };

                    Ext.apply(options.params, params);
                }
            }
        }),

        columns: [
            {
                id: fileStatusColId,
                header: _('Case File Status'),
                dataIndex: 'history_client_status_name',
                sortable: true,
                renderer: function (val, metadata, record) {
                    metadata.attr = thisDialog.generateTooltipAttr(record);

                    return val;
                }
            }, {
                header: _('Changed on'),
                dataIndex: 'history_changed_on_date',
                width: 200,
                fixed: true,
                sortable: true,
                renderer: function (val, metadata, record) {
                    metadata.attr = thisDialog.generateTooltipAttr(record);

                    return Ext.util.Format.date(record.data.history_changed_on_date, dateFormatFull);
                }
            }, {
                header: _('Days'),
                dataIndex: 'history_days',
                align: 'right',
                width: 100,
                sortable: true,
                renderer: function (val, metadata, record) {
                    metadata.attr = thisDialog.generateTooltipAttr(record);

                    return val;
                }
            }, {
                header: _('Changed by'),
                dataIndex: 'history_checked_user_name',
                width: 200,
                sortable: true,
                renderer: function (val, metadata, record) {
                    metadata.attr = thisDialog.generateTooltipAttr(record);

                    var who = val;
                    if (!empty(record.data.history_unchecked_date)) {
                        who = record.data.history_unchecked_user_name;
                    }

                    return who;
                }
            }
        ]
    });

    ApplicantsProfileFileStatusHistoryDialog.superclass.constructor.call(this, {
        title: '<i class="las la-history"></i>' + _('Case File Status History'),
        labelWidth: 50,
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        modal: true,

        items: this.fileStatusHistoryGrid,

        buttons: [
            {
                text: _('Close'),
                cls: 'orange-btn',
                scope: this,
                handler: function () {
                    this.close();
                }
            }
        ]
    });
};

Ext.extend(ApplicantsProfileFileStatusHistoryDialog, Ext.Window, {
    generateTooltipAttr: function (record) {
        var tooltip = String.format(
            'selected on {0} by {1}',
            Ext.util.Format.date(record.data.history_checked_date, Date.patterns.UniquesLong),
            record.data.history_checked_user_name
        );

        if (!empty(record.data.history_unchecked_date)) {
            tooltip += '<br>' + String.format(
                'un-selected on {0} by {1}',
                Ext.util.Format.date(record.data.history_unchecked_date, Date.patterns.UniquesLong),
                record.data.history_unchecked_user_name
            );
        }

        return 'ext:qtip="' + tooltip.replaceAll('"', "&Prime;") + '"';
    }
});