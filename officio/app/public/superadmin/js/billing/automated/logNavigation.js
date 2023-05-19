AutomatedLogNavigation = function() {
    this.tree = new Ext.tree.TreePanel({
        header: false,
        rootVisible:false,
        lines: true,
        autoScroll:true,

        root: new Ext.tree.AsyncTreeNode(),
        loader: new Ext.tree.TreeLoader({
            clearOnLoad: true,
            preloadChildren: false,
            dataUrl: baseUrl + '/automated-billing-log/get-sessions'
        }),

        listeners: {
            beforeload: function() {
                this.body.mask('Loading', 'x-mask-loading');
            },

            load: function() {
                this.body.unmask();
                this.getSelectionModel().clearSelections();
            },

            'contextmenu': this.onContextMenu.createDelegate(this),
            
            'click': this.openLog.createDelegate(this)
        }
    });

    this.tree.getSelectionModel().on({
        'beforeselect' : function(sm, node){
            return node.isLeaf();
        },

        'selectionchange': function(sm, node) {
            var booDisabled = node ? false : true;
            this.deleteLogBtn.setDisabled(booDisabled);
        },

        scope:this
    });

    
    this.tbar = [
        {
            tooltip: 'Delete selected log',
            text: '<i class="las la-trash"></i>' + _('Delete'),
            ref: '../deleteLogBtn',
            disabled: true,
            handler: this.deleteLog.createDelegate(this)
        }, '->',{
            tooltip: 'Refresh the tree',
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            handler: this.reloadTree.createDelegate(this)
        }
    ];

    AutomatedLogNavigation.superclass.constructor.call(this, {
        region: 'west',
        title: 'Saved log sessions',
        split:true,
        width: 200,
        minSize: 175,
        maxSize: 400,
        collapsible: true,
        layout:'accordion',
        cls: 'with-border',
        layoutConfig:{
            animate: true
        },

        items: this.tree
    });

};

Ext.extend(AutomatedLogNavigation, Ext.Panel, {
    reloadTree: function() {
        this.tree.root.reload();
    },

    deleteLog: function() {
        var tree = this.tree;
        var node = tree.getSelectionModel().getSelectedNode();

        if(node && node.leaf) {
            var msgQuestion = String.format(
                'Are you sure you want to delete session for <i>"{0}"</i>?',
                node.attributes.text
            );


            Ext.Msg.confirm('Please confirm', msgQuestion, function(btn) {
                if (btn == 'yes') {
                    Ext.getBody().mask('Processing...');

                    Ext.Ajax.request({
                        url: baseUrl + '/automated-billing-log/delete-session',
                        params: {
                            session_id: node.attributes.session_id
                        },

                        success: function(result) {
                            var resultData = Ext.decode(result.responseText);
                            if(resultData.success) {
                                // Show confirmation success message
                                Ext.simpleConfirmation.success(resultData.message);

                                // Reload sessions tree
                                var navPanel = tree.ownerCt;
                                navPanel.reloadTree();

                                // Close tab if it is opened
                                var tabPanel = navPanel.ownerCt.tabPanel;
                                tabPanel.closeTab(node.attributes);
                            } else {
                                // Show confirmation failure message
                                Ext.simpleConfirmation.error(resultData.message);
                            }

                            Ext.getBody().unmask();
                        },

                        failure: function() {
                            Ext.simpleConfirmation.error('Cannot delete session. Please try again later.');
                            Ext.getBody().unmask();
                        }
                    });
                }
            });
        }
    },

    openLog: function(node) {
        if(node.leaf) {
            var tabPanel = this.ownerCt.tabPanel;
            tabPanel.openTab(node);
        }
    },

    onContextMenu: function(node, e) {
        if (!this.menu) { // create context menu on first right click
            this.menu = new Ext.menu.Menu({
                items: [
                    {
                        text: '<i class="las la-search"></i>' + _('Open log'),
                        scope: this,
                        handler: function() {
                            this.openLog(this.ctxNode);
                        }
                    },
                    {
                        text: '<i class="las la-trash"></i>' + _('Delete log'),
                        scope: this,
                        handler: this.deleteLog

                    }
                ]
            });
            this.menu.on('hide', this.onContextHide, this);
        }

        if(this.ctxNode){
            this.ctxNode.ui.removeClass('x-node-ctx');
            this.ctxNode = null;
        }
        
        if(node.isLeaf()){
            this.ctxNode = node;
            this.ctxNode.ui.addClass('x-node-ctx');
            this.ctxNode.select();

            this.menu.showAt(e.getXY());
        }
    },

    onContextHide: function() {
        if (this.ctxNode) {
            this.ctxNode.ui.removeClass('x-node-ctx');
            this.ctxNode = null;
        }
    },

    // prevent the default context menu when you miss the node
    afterRender : function(){
        AutomatedLogNavigation.superclass.afterRender.call(this);
        this.el.on('contextmenu', function(e){
            e.preventDefault();
        });
    }
});
