var openEditPage = function(companyId) {
    window.open(String.format(baseUrl + '/manage-company/edit?company_id={0}', companyId));
};

var openLoginPage = function(companyId) {
    window.location = String.format(baseUrl + '/manage-company-as-admin/{0}', companyId);
};

var initCompanyQuickSearch = function() {
    var ds = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/manage-company/company-search',
            method: 'post'
        }),

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            id: 'companyId'
        }, [
            {name: 'companyId', mapping: 'company_id'},
            {name: 'companyName', mapping: 'company_name'},
            {name: 'companyEmail', mapping: 'company_email'},
            {name: 'companyStatus', mapping: 'company_status'},
            {name: 'adminName', mapping: 'admin_name'},
            {name: 'trial', mapping: 'company_trial'}
        ])
    });

    // Custom rendering Template
    var resultTpl =  new Ext.XTemplate(
        '<tpl for="."><div class="x-combo-list-item" style="padding: 7px;">',
            '<h3>{companyName}</h3>',
            '<div style="float: right;">Id: {companyId}</div>',
            '<p style="padding-top: 3px;">{adminName}</p>',
            '<p style="padding-top: 3px;">{companyEmail}</p>',
            '<p style="padding-top: 3px;">{companyStatus:this.showStatus} Trial: {trial}</p>',

            '<p style="padding-top: 3px; min-width: 200px;">' +
                ( !arrAccessRights['edit'] ? '' : '<a href="#" onclick="showCompanyPage({companyId}, \'{companyName:this.generateName}\');">Edit</a>') +
                ( arrAccessRights['edit'] && arrAccessRights['login'] ? ' or ' : '') +
                ( !arrAccessRights['login'] ? '' : '<a href="#" onclick="openLoginPage({companyId});">Login as Admin</a>') +
            '</p>',
        '</div></tpl>',
        {
            generateName: function(companyName) {
                var filteredVal = companyName.replace(/"/g, "''");
                    filteredVal = filteredVal.replace(/'/g, "\\'");
                return filteredVal;
            },

            showStatus: function(companyStatus) {
                var strStatus = companyStatus, color = '';
                switch (companyStatus) {
                    case 'Inactive':
                        color = 'red';
                        break;

                    case 'Active':
                        color = 'green';
                        break;

                    case 'Suspended':
                        color = '#F07100';
                        break;

                    default:
                        strStatus = '';
                        break;
                }

                if(!empty(strStatus)) {
                    strStatus = String.format(
                        'Status: <span style="color: {0};">{1}</span>',
                        color,
                        strStatus
                    );
                }

                return strStatus;
            }
        }
    );

    var search = new Ext.form.ComboBox({
        store: ds,
        displayField: 'companyName',
        typeAhead: false,
        id: 'superadmin_companies_search',
        loadingText: 'Searching...',
        emptyText: 'Search company...',
        width: 350,
        listWidth: 335,
        listClass: 'no-pointer',
        pageSize: 25,
        minChars: 1,
        hideTrigger: true,
        tpl: resultTpl,
        applyTo: 'company_search',
        cls: 'with-right-border',
        itemSelector: 'div.x-combo-list-item',
        onSelect: function(record){
            // Do nothing, just override default onSelect to do redirect
        }
    });

    search.focus();
};