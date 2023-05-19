var TimeLogSummaryToolbar = function (config, viewer) {
    this.viewer = viewer;
    Ext.apply(this, config);

    TimeLogSummaryToolbar.superclass.constructor.call(this, {
        enableOverflow: true,

        items: [
            {
                text: '<i class="las la-file-export"></i>' + _('Export'),
                cls: 'main-btn',
                ref: 'trackerCreateBtn',
                scope: this,
                handler: this.exportTimeLogSummary
            }, '->', {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                xtype: 'button',
                scope: this,
                handler: this.refreshTimeLogSummaryGrid.createDelegate(this)
            }, {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: typeof allowedPages === 'undefined' || !allowedPages.has('help'),
                handler: function () {
                    showHelpContextMenu(this.getEl(), 'clients-time-log-summary');
                }
            }
        ]
    });
};

Ext.extend(TimeLogSummaryToolbar, Ext.Toolbar, {
    refreshTimeLogSummaryGrid: function () {
        this.viewer.TimeLogSummaryGrid.store.reload();
    },

    exportTimeLogSummary: function () {
        this.viewer.TimeLogSummaryGrid.exportTimeLogSummary();
    }
});