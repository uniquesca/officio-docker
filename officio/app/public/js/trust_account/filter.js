function FtaSetFilter(filter, ta_id)
{
    //set defaults
    filter = (empty(filter) ? {} : filter);
    Ext.getDom('client_code'+ta_id).style.display = "none";
    Ext.getDom('period'+ta_id).style.display = "none";
    Ext.getDom('unassigned'+ta_id).style.display = "none";

    
    var dt = new Date();
    dt = Date.parseDate(todayDate, dateFormatShort);
    var parsedTodayDate = dt.format(dateFormatFull);
    
    if(!empty(filter.start_date)) {
        var dt2 = new Date();
        dt2 = Date.parseDate(filter.start_date, dateFormatShort);
        var parsedStartDate = dt2.format(dateFormatFull);
    }
    
    if(!empty(filter.end_date)) {
        var dt3 = new Date();
        dt3 = Date.parseDate(filter.end_date, dateFormatShort);
        var parsedEndDate = dt3.format(dateFormatFull);
    }
    

    var fid = 'filter-client-name' + ta_id;
    if (Ext.getCmp(fid))
    {
        Ext.getCmp(fid).hide();
    }
    
    //use params
    switch(filter.filter)
    {
        case 'client_name' :
                Ext.getCmp(fid).show();
                var client_name = (empty(filter.client_name) ? '' : filter.client_name);
                Ext.getCmp(fid).setValue(client_name);
                Ext.getDom('client_name'+ta_id).value = client_name;
                break;

        case 'period' :
                var start_date = (empty(filter.start_date) ? parsedTodayDate : parsedStartDate);
                var end_date = (empty(filter.end_date) ? parsedTodayDate : parsedEndDate);
                Ext.getDom('start-date'+ta_id).value = start_date;
                Ext.getDom('end-date'+ta_id).value = end_date;
                break;

        case 'client_code' :
                var client_code = (empty(filter.client_code) ? '' : filter.client_code);
                Ext.getDom('client_code'+ta_id).value = client_code;
                break;

        case 'unassigned' :
                var end_date2 = (empty(filter.end_date) ? parsedTodayDate : parsedEndDate);
                Ext.getDom('unassigned-end-date'+ta_id).value = end_date2;
                break;
        default:
                break;
    }

    //display
    if(Ext.getDom(filter.filter + ta_id))
    {
            Ext.getDom(filter.filter+ ta_id).style.display = "";
    }
    Ext.getCmp('filter_type_t' + ta_id).setValue(filter.filter);

    return true;
}

function FtaGetFilter(ta_id)
{
    var thisId = 'filter_type'+ta_id;

    var filter = Ext.get(thisId).getValue();
    var params = new Array();
    params = {};

    switch(filter)
    {
        case 'client_name' : params = { client_name: Ext.get('client_name'+ta_id).getValue() };
            break;

        case 'client_code' : params = { client_code: Ext.get('client_code'+ta_id).getValue() };
            break;

        case 'period' : 
            var dt = new Date();
            dt = Date.parseDate(Ext.get('start-date'+ta_id).getValue(), dateFormatFull);

            var dt2 = new Date();
            dt2 = Date.parseDate(Ext.get('end-date'+ta_id).getValue(), dateFormatFull);

            params = { 
                start_date: Ext.encode(dt.format(dateFormatShort)),
                end_date:   Ext.encode(dt2.format(dateFormatShort))
            };
            break;

        case 'unassigned' : 
            var dt3 = new Date();
            dt3 = Date.parseDate(Ext.get('unassigned-end-date'+ta_id).getValue(), dateFormatFull);
            params = { end_date:   Ext.encode(dt3.format(dateFormatShort)) };
            break;
            
        default:
            break;
    }

    Ext.apply(params, {filter: filter});
    return params;
}

function FtaSetFilterAndApply(filter, ta_id)
{
    if(FtaSetFilter(filter, ta_id))
    {
        Ext.getCmp('editor-grid'+ta_id).store.load({params:filter});
    }
    return true;
}

function FtaApplyFilter(ta_id)
{
    Ext.getCmp('editor-grid'+ta_id).store.load({params:FtaGetFilter(ta_id)});
    return true;
}