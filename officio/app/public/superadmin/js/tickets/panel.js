var TicketsPanel = function (config) {
    Ext.apply(this, config);

    var ticketsGrid = new TicketsGrid({
        useTopUrl: true,
        region: 'center',
        company_id: 'all',
        ticketsOnPage: 20
    });
    ticketsGrid.store.load();

    var filterForm = new TicketsFilterPanel({
        title: 'Extended Filter',
        region: 'east'
    });

    TicketsPanel.superclass.constructor.call(this, {
        id: 'tickets-panel',
        autoWidth: true,
        layout: 'border',
        style: 'padding: 10px 20px 0;',
        items: [
            ticketsGrid, filterForm
        ]
    });
};

Ext.extend(TicketsPanel, Ext.Panel, {
});
