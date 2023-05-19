var TimeLogSummaryPanel = function (config) {
    Ext.apply(this, config);

    this.TimeLogSummaryToolbar = new TimeLogSummaryToolbar({
        region: 'north',
        height: 50
    }, this);

    this.TimeLogSummaryFilter = new TimeLogSummaryFilter({
        forceLayout: true
    }, this);

    this.TimeLogSummaryFilterPanel = new Ext.Panel({
        title: _('Filter'),
        region: 'east',
        cls: 'time-log-summary-filter-panel',
        collapsible: true,
        split: true,
        collapsed: false,
        collapseMode: 'mini',
        width: 300,
        minSize: 300,
        maxSize: 300,
        forceLayout: true,
        items: this.TimeLogSummaryFilter
    });

    this.TimeLogSummaryGrid = new TimeLogSummaryGrid({
        region: 'center',
        split: true
    }, this);

    TimeLogSummaryPanel.superclass.constructor.call(this, {
        layout: 'border',
        items: [
            this.TimeLogSummaryToolbar,
            this.TimeLogSummaryFilterPanel,
            this.TimeLogSummaryGrid
        ]
    });
};

Ext.extend(TimeLogSummaryPanel, Ext.Panel, {});