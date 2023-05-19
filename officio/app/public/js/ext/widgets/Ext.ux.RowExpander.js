Ext.ns('Ext.ux.grid');

/**
 * @class Ext.ux.grid.RowExpander
 * @extends Ext.util.Observable
 * Plugin (ptype = 'rowexpander') that adds the ability to have a Column in a grid which enables
 * a second row body which expands/contracts.  The expand/contract behavior is configurable to react
 * on clicking of the column, double click of the row, and/or hitting enter while a row is selected.
 *
 * @ptype rowexpander
 */
Ext.ux.grid.RowExpander = Ext.extend(Ext.util.Observable, {
    /**
     * @cfg {Boolean} expandOnEnter
     * <tt>true</tt> to toggle selected row(s) between expanded/collapsed when the enter
     * key is pressed (defaults to <tt>true</tt>).
     */
    expandOnEnter: true,
    /**
     * @cfg {Boolean} expandOnDblClick
     * <tt>true</tt> to toggle a row between expanded/collapsed when double-clicked
     * (defaults to <tt>true</tt>).
     */
    expandOnDblClick: true,

    header: '',
    width: 20,
    sortable: false,
    fixed: true,
    hideable: false,
    menuDisabled: true,
    dataIndex: '',
    id: 'expander',
    lazyRender: true,
    enableCaching: true,

    constructor: function (config) {
        Ext.apply(this, config);

        this.addEvents({
            /**
             * @event beforeexpand
             * Fires before the row expands. Have the listener return false to prevent the row from expanding.
             * @param {Object} this RowExpander object.
             * @param {Object} Ext.data.Record Record for the selected row.
             * @param {Object} body body element for the secondary row.
             * @param {Number} rowIndex The current row index.
             */
            beforeexpand: true,
            /**
             * @event expand
             * Fires after the row expands.
             * @param {Object} this RowExpander object.
             * @param {Object} Ext.data.Record Record for the selected row.
             * @param {Object} body body element for the secondary row.
             * @param {Number} rowIndex The current row index.
             */
            expand: true,
            /**
             * @event beforecollapse
             * Fires before the row collapses. Have the listener return false to prevent the row from collapsing.
             * @param {Object} this RowExpander object.
             * @param {Object} Ext.data.Record Record for the selected row.
             * @param {Object} body body element for the secondary row.
             * @param {Number} rowIndex The current row index.
             */
            beforecollapse: true,
            /**
             * @event collapse
             * Fires after the row collapses.
             * @param {Object} this RowExpander object.
             * @param {Object} Ext.data.Record Record for the selected row.
             * @param {Object} body body element for the secondary row.
             * @param {Number} rowIndex The current row index.
             */
            collapse: true
        });

        Ext.ux.grid.RowExpander.superclass.constructor.call(this);

        if (this.tplContent) {
            if (typeof this.tplContent === 'string') {
                this.tplContent = new Ext.Template(this.tplContent);
            }
            this.tplContent.compile();
        }

        if (this.tplAuthor) {
            if (typeof this.tplAuthor === 'string') {
                this.tplAuthor = new Ext.Template(this.tplAuthor);
            }
            this.tplAuthor.compile();
        }

        if (this.tplDate) {
            if (typeof this.tplDate === 'string') {
                this.tplDate = new Ext.Template(this.tplDate);
            }
            this.tplDate.compile();
        }

        this.state = {};
        this.bodyContent = {};
        this.bodyAuthor = {};
        this.bodyDate = {};
    },

    getRowClass: function (record, rowIndex, p) {
        p.cols = p.cols - 1;
        var content = this.bodyContent[record.id];
        if (!content && !this.lazyRender) {
            content = this.getBodyContent(record, rowIndex);
        }
        if (content) {
            p.body = content;
        }
        return this.state[record.id] ? 'x-grid3-row-expanded' : 'x-grid3-row-collapsed';
    },

    init: function (grid) {
        this.grid = grid;

        var view = grid.getView();
        view.getRowClass = this.getRowClass.createDelegate(this);

        view.enableRowBody = true;


        grid.on('render', this.onRender, this);
        grid.on('destroy', this.onDestroy, this);
    },

    // @private
    onRender: function () {
        var grid = this.grid;
        var mainBody = grid.getView().mainBody;
        mainBody.on('mousedown', this.onMouseDown, this, {delegate: '.x-grid3-row-expander'});
        if (this.expandOnEnter) {
            this.keyNav = new Ext.KeyNav(this.grid.getGridEl(), {
                'enter': this.onEnter,
                scope: this
            });
        }
        if (this.expandOnDblClick) {
            grid.on('rowdblclick', this.onRowDblClick, this);
        }
    },

    // @private    
    onDestroy: function () {
        if (this.keyNav) {
            this.keyNav.disable();
            delete this.keyNav;
        }
        /*
         * A majority of the time, the plugin will be destroyed along with the grid,
         * which means the mainBody won't be available. On the off chance that the plugin
         * isn't destroyed with the grid, take care of removing the listener.
         */
        var mainBody = this.grid.getView().mainBody;
        if (mainBody) {
            mainBody.un('mousedown', this.onMouseDown, this);
        }
    },
    // @private
    onRowDblClick: function (grid, rowIdx) {
        this.toggleRow(rowIdx);
    },

    onEnter: function () {
        var g = this.grid;
        var sm = g.getSelectionModel();
        var sels = sm.getSelections();
        for (var i = 0, len = sels.length; i < len; i++) {
            var rowIdx = g.getStore().indexOf(sels[i]);
            this.toggleRow(rowIdx);
        }
    },

    getBodyContent: function (record, type) {
        if (!this.enableCaching) {
            return this.tplContent.apply(record.data);
        }
        var content = this.bodyContent[record.id];
        if (!content) {
            content = this.tplContent.apply(record.data);
            this.bodyContent[record.id] = content;
        }
        return content;
    },

    getBodyAuthor: function (record, type) {
        if (!this.enableCaching) {
            return this.tplAuthor.apply(record.data);
        }
        var content = this.bodyAuthor[record.id];
        if (!content) {
            content = this.tplAuthor.apply(record.data);
            this.bodyAuthor[record.id] = content;
        }
        return content;
    },

    getBodyDate: function (record, type) {
        if (!this.enableCaching) {
            return this.tplDate.apply(record.data);
        }
        var content = this.bodyDate[record.id];
        if (!content) {
            content = this.tplDate.apply(record.data);
            this.bodyDate[record.id] = content;
        }
        return content;
    },

    onMouseDown: function (e) {
        e.stopEvent();
        var row = e.getTarget('.x-grid3-row');
        this.toggleRow(row);
    },

    renderer: function (v, p) {
        p.cellAttr = 'rowspan="2"';
        return '<div class="x-grid3-row-expander">&#160;</div>';
    },

    beforeExpand: function (record, body1, body2, body3, rowIndex) {
        if (this.fireEvent('beforeexpand', this, record, rowIndex) !== false) {
            if (this.tplContent && this.lazyRender) {
                body1.innerHTML = this.getBodyContent(record, rowIndex);
            }

            if (this.tplAuthor && this.lazyRender) {
                body2.innerHTML = this.getBodyAuthor(record, rowIndex);
            }

            if (this.tplDate && this.lazyRender) {
                body3.innerHTML = this.getBodyDate(record, rowIndex);
            }
            return true;
        } else {
            return false;
        }
    },

    toggleRow: function (row) {
        if (typeof row === 'number') {
            row = this.grid.view.getRow(row);
        }
        this[Ext.fly(row).hasClass('x-grid3-row-collapsed') ? 'expandRow' : 'collapseRow'](row);
    },

    expandRow: function (row) {
        if (typeof row === 'number') {
            row = this.grid.view.getRow(row);
        }
        var record = this.grid.store.getAt(row.rowIndex);
        var mainTr = Ext.DomQuery.selectNode('table tbody tr.x-grid3-row-body-tr', row);
        var body1 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(1) div', row);
        var body2 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(2) div', row);
        var body3 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(3) div', row);
        if (typeof mainTr !== 'undefined' || typeof body1 === 'undefined') {
            if (typeof mainTr !== 'undefined') {
                mainTr.remove();
            }

            Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody', row), {
                tag: "tr",
                'class': 'x-grid3-row-body-tr'
            });

            var colspan = 3;
            if (!this.tplAuthor) {
                colspan += 1;
            }

            if (!this.tplDate) {
                colspan += 2;
            }

            Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2)', row), {
                tag: "td",
                colspan: colspan
            });

            if (this.tplAuthor) {
                Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2)', row), {
                    tag: "td"
                });
            }

            if (this.tplDate) {
                Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2)', row), {
                    tag: "td",
                    colspan: "2"
                });
            }

            Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2) td:nth-child(1)', row), {
                tag: "div",
                'class': "x-grid3-row-body"
            });

            if (this.tplAuthor) {
                Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2) td:nth-child(2)', row), {
                    tag: "div",
                    'class': "x-grid3-row-body"
                });
            }

            if (this.tplDate) {
                Ext.DomHelper.append(Ext.DomQuery.selectNode('table tbody tr:nth-child(2) td:nth-child(3)', row), {
                    tag: "div",
                    'class': "x-grid3-row-body"
                });
            }

            body1 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(1) div', row);
            body2 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(2) div', row);
            body3 = Ext.DomQuery.selectNode('tr:nth-child(2) td:nth-child(3) div', row);
        }

        if (this.beforeExpand(record, body1, body2, body3, row.rowIndex)) {
            this.state[record.id] = true;
            Ext.fly(row).replaceClass('x-grid3-row-collapsed', 'x-grid3-row-expanded');
            this.fireEvent('expand', this, record, body1, row.rowIndex);
        }
    },

    collapseRow: function (row) {
        if (typeof row === 'number') {
            row = this.grid.view.getRow(row);
        }

        var record = this.grid.store.getAt(row.rowIndex);
        var body = Ext.fly(row).child('tr:nth(2)', true);
        if (this.fireEvent('beforecollapse', this, record, body, row.rowIndex) !== false) {
            this.state[record.id] = false;
            Ext.fly(row).replaceClass('x-grid3-row-expanded', 'x-grid3-row-collapsed');
            this.fireEvent('collapse', this, record, body, row.rowIndex);
        }
    }
});

Ext.preg('rowexpander', Ext.ux.grid.RowExpander);

//backwards compat
Ext.grid.RowExpander = Ext.ux.grid.RowExpander;
