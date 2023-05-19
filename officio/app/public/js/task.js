function new_task()
{
    var win = new NewTaskDialog(null, {booShowClient: true, member_id: null});
    win.showTaskDialog();
}

function tooltipTaskInfo()
{
    var obj=Ext.query('.task-tip');

    for (var i=0; i<obj.length; i++)
    {
        new Ext.ToolTip({
            target: obj[i].id,
            autoWidth: true,
            autoLoad: {
                url: baseUrl+'/tasks/index/tooltip-task-info/',
                params: {id: obj[i].id},
                callback: function (tip) {
                    tip.setWidth(300);
                }
            },
            autoDestroy: true,
            trackMouse: true,
            dismissDelay: 0,
            width: 300,
            showDelay: 600
        });
    }
}