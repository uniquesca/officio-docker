var pathToExtjs = topBaseUrl + '/js/ext/resources';
Ext.chart.Chart.CHART_URL = pathToExtjs + '/charts.swf';

Ext.onReady(function(){
    var getRecords = function () {
        Ext.getBody().mask('Loading...');

        Ext.Ajax.request({
            url: baseUrl + '/statistics/get',

            params: {
                date : Ext.encode(Ext.getCmp('statistic_date').getValue()),
                type : Ext.encode(Ext.getCmp('statistic_type').getValue())
            },

            success: function (res) {
                var resultData = Ext.decode(res.responseText);
                var type=Ext.getCmp('statistic_type').getValue();

                Ext.getCmp('stats_panel').setTitle(type=='hits' ? 'Hits' : 'Users');

                var iframe = parent.document.getElementById('admin_section_frame');
                if (iframe) {
                    var iframeBodyHeight = iframe.contentWindow.document.body.offsetHeight;
                    iframe.style.height = iframeBodyHeight + 'px';
                }

                if (resultData instanceof Array) {
                    var labels = [];
                    var newDataset = [];
                    var backgroundColor = [];
                    var borderColor = [];
                    for (var i = 0; i < resultData.length; i++) {
                        newDataset.push({
                            hits: resultData[i].hits,
                            time: resultData[i].time
                        });
                        labels.push(resultData[i].time)
                        var color = getChartDefinedRandomColor(i);
                        backgroundColor.push(Chart.helpers.color(color).alpha(0.5).rgbString())
                        borderColor.push(Chart.helpers.color(color).alpha(1).rgbString())
                    }
                    myChart.data.datasets[0].data = newDataset;
                    myChart.data.labels = labels;
                    myChart.data.datasets[0].backgroundColor = backgroundColor;
                    myChart.data.datasets[0].borderColor = borderColor;
                    myChart.update();
                }
                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.getBody().unmask();
            }
        });
    }

    new Ext.FormPanel({
        labelWidth: 110,
        autoHeight: true,
        bodyStyle: 'padding: 5px; background-color: #EEEEEE;',
        renderTo: 'statistics_superadmin_date',
        layout: 'column',
        items: [
            {
                xtype: 'label',
                style: 'padding-top: 5px; padding-right: 5px;',
                text: 'Show statistic for:'
            },{
                id: 'statistic_date',
                xtype: 'datefield',
                format: dateFormatFull,
                width: 140,
                maxLength: 12, // Fix issue with date entering in 'full' format
                altFormats: dateFormatFull + '|' + dateFormatShort,
                value: new Date(),
                maxValue: new Date()
            }, {
                html: '&nbsp;&nbsp;'
            }, {
                id:'statistic_type',
                xtype:'combo',
                store:new Ext.data.ArrayStore({
                    fields:['type', 'text'],
                    data:[['hits', 'hits'], ['users', 'users']]
                }),
                width:90,
                mode:'local',
                displayField:'text',
                valueField:'type',
                allowBlank:false,
                typeAhead:true,
                forceSelection:true,
                triggerAction:'all',
                selectOnFocus:true,
                editable:false,
                value:'hits'
            }, {
                xtype: 'button',
                text: '<i class="las la-arrow-right"></i>' + _('Show'),
                style: 'padding-left: 5px;',
                handler: function() {
                    getRecords();
                }
            }
        ]
    });

    var d = new Date();

    new Ext.FormPanel({
        labelWidth: 110,
        autoHeight: true,
        bodyStyle: 'padding: 5px; background-color: #EEEEEE;',
        renderTo: 'statistics_superadmin_date',
        layout: 'column',
        items: [
            {
                xtype: 'label',
                style: 'padding-top: 5px; padding-right: 5px;',
                text: 'Delete statistic before:'
            },{
                id: 'statistic_delete_date',
                xtype: 'datefield',
                format: dateFormatFull,
                width: 140,
                maxLength: 12, // Fix issue with date entering in 'full' format
                altFormats: dateFormatFull + '|' + dateFormatShort,
                value: new Date(d.getFullYear(), d.getMonth(), d.getDate()-7), // week before
                maxValue: new Date()
            }, {
                xtype: 'button',
                text: '<i class="las la-trash"></i>' + _('Delete'),
                style: 'padding-left: 5px;',
                handler: function() {
                    Ext.Msg.show({
                       title: 'Please confirm',
                       msg: 'Are you sure?',
                       buttons: Ext.Msg.YESNO, fn:function (btn)
                       {
                           if (btn=='yes')
                           {
                               Ext.getBody().mask();

                               Ext.Ajax.request({
                                   url:baseUrl+'/statistics/delete',
                                   params:
                                   {
                                       date:Ext.encode(Ext.getCmp('statistic_delete_date').getValue())
                                   },
                                   success:function (f, o)
                                   {
                                       Ext.getBody().unmask();
                                   },
                                   failure:function (f, o)
                                   {
                                       Ext.getBody().unmask();
                                       Ext.simpleConfirmation.error('Can\'t delete data');
                                   }
                               });
                           }
                        }
                    });
                }
            }
        ]
    });

    new Ext.Panel({
        id:       'stats_panel',
        title:    '<i class="las la-chart-bar"></i>' + _('Hits'),
        renderTo: 'statistics_superadmin_container',
        width:    800,
        height:   300,
        layout:   'fit',
        items: {
            id:    'chartbox',
            xtype: 'box',

            autoEl: {
                tag: 'canvas'
            }
        }
    });

    var ctx = document.getElementById('chartbox').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    data: [],
                    backgroundColor: [],
                    borderColor: [],
                    borderWidth: 1
                }
            ]
        },
        options: {
            parsing: {
                xAxisKey: 'time',
                yAxisKey: 'hits'
            },

            animation: {
                duration: 1500
            },

            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: [{
                    type: 'time',
                    time: {
                        parser: 'hh:mm',
                        unit: 'hour'
                    }
                }]
            }
        },
        plugins: [{
            beforeInit: function(chart, args, options) {
                getRecords();
            }
        }]
    });

    // companies grid
    var companiesGrid = function() {
        var thisGrid = this;

        // Init basic properties
        this.store = new Ext.data.Store({
            url: baseUrl + '/manage-company/get-companies',
            method: 'POST',
            autoLoad: true,
            remoteSort: true,
            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                idProperty: 'company_id',
                fields: [
                    {name: 'company_id',                type: 'int'},
                    {name: 'company_name',              type: 'string'},
                    {name: 'admin_name',                type: 'string'},
                    {name: 'storage_diff',              type: 'int'},
                    {name: 'storage_today',             type: 'int'},
                    {name: 'company_trial',             type: 'string'},
                    {name: 'company_next_billing_date', type: 'date', dateFormat: 'Y-m-d'}
                ]
            }),

            sortInfo: {
                field: "storage_diff",
                direction: "DESC"
            },

            listeners: {
                load: function() {
                    var iframe = parent.document.getElementById('admin_section_frame');
                    if (iframe) {
                        var iframeBodyHeight = iframe.contentWindow.document.body.offsetHeight;
                        iframe.style.height = iframeBodyHeight + 'px';
                    }
                }
            }
        });

        this.columns = [
            {
                header: 'Id',
                sortable: true,
                dataIndex: 'company_id',
                width: 80
            }, {
                id: 'col_company_name',
                header: 'Company Name',
                sortable: true,
                dataIndex: 'company_name',
                renderer: this.renderCompanyName.createDelegate(this),
                width: 50
            }, {
                header:'Company Admin',
                sortable:false,
                dataIndex:'admin_name',
                width: 275
            }, {
                header: 'Next Billing Date',
                sortable: true,
                dataIndex: 'company_next_billing_date',
                align: 'center',
                renderer: function (val) {
                    return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
                },
                width: 160,
                fixed: true
            }, {
                id: 'col_company_storage',
                header: 'Storage',
                sortable: true,
                dataIndex: 'storage_today',
                renderer: function(val) {
                    return thisGrid.renderFileSize(val / 1024);
                },
                width: 90
            }, {
                id: 'col_company_storage_diff',
                header: 'Storage used since yesterday',
                sortable: true,
                dataIndex: 'storage_diff',
                renderer: this.renderFileSize.createDelegate(this),
                width: 260,
                fixed: true
            }
        ];

        this.bbar = new Ext.PagingToolbar({
            pageSize: 25,
            store: this.store,
            displayInfo: true,
            emptyMsg: 'No companies to display'
        });

        companiesGrid.superclass.constructor.call(this, {
            id: 'companies-grid',
            autoWidth: true,
            autoHeight: true,
            autoExpandColumn: 'col_company_name',
            stripeRows: true,
            cls: 'extjs-grid',
            renderTo: 'companies_list_container',
            loadMask: true,
            viewConfig:{
                getRowClass: function (record, index)
                {
                    return record.get('company_trial')=='Y' ? 'trial_company' : '';
                },
                emptyText: 'No companies were found.'
            }
        });
    };

    Ext.extend(companiesGrid, Ext.grid.GridPanel, {
        renderCompanyName: function(val, cell, rec) {
            strEdit = String.format(
                '<a href="{0}/manage-company/edit?company_id={1}" title="{2}">{2}</a>',
                baseUrl,
                rec.data.company_id,
                val
            );

            return strEdit;
        },

        renderFileSize: function(filesize) {
            sign = filesize<0 ? '-' : '';

            bytecount=Math.abs(filesize*1024); // make it bytes ;)

            var str=bytecount+' B';

            if (Number(bytecount)>=1024)
                str=(bytecount/1024).toFixed(0)+' KB';

            if (Number(bytecount)>=1024*1024)
                str=(bytecount/(1024*1024)).toFixed(0)+' MB';

            if (Number(bytecount)>=1024*1024*1024)
                str=(bytecount/(1024*1024*1024)).toFixed(2)+' GB';

            return sign+str;
        }
    });

    new companiesGrid();
});