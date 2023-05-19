function iprint(ta_id) {
    var params = Ext.getCmp('editor-grid' + ta_id).store.lastOptions.params;    
    var strWord = '';
    var params_list = [];
    var strTarget = '\/';
    var strNewSym = '_'; 
    var del_kav = false;
    for( var word in params) {
        strWord = ''+ params[word];
        var intIndexOfMatch = strWord.indexOf( '/' );
         
        // Keep looping while an instance of the target string
        // still exists in the string.
        while (intIndexOfMatch != -1){
        // Relace out the current instance.
            strWord = strWord.replace( strTarget, strNewSym );
         
        // Get the index of any next matching substring.
        intIndexOfMatch = strWord.indexOf( strTarget );
        del_kav = true;
        }

        if (del_kav){
            strWord = strWord.substring(1,strWord.length - 1);    
            del_kav = false;
        }

        params_list.push(encodeURIComponent(word) + '=' + encodeURIComponent(strWord));
    }

    var pan = new Ext.FormPanel({
        id: 'print-trust-account-form',
        items: [{
            xtype: 'label',
            html: '<iframe id="print_iframe" name="print_iframe" style="border:none;" width="100%" height="535px" src="' + baseUrl + '/trust-account/index/print?' + params_list.join('&') + '" scrolling="auto"></iframe>'
        }]
    });
    
    var win = new Ext.Window({
        id: 'print-trust-account',
        title :'Print preview',
        layout :'form',
        modal :true,
        width :1000,
        autoHeight: true,
        items: pan,
        buttons: [{
            text: 'Close',
            handler: function () {
                win.close();
            }
        },
            {
                text: 'Print',
                cls: 'orange-btn',
                handler: function () {
                    function PrintThisPage() {
                        if (Ext.isIE) {
                            document.print_iframe.focus();
                            document.print_iframe.print();
                        } else {
                            window.frames['print_iframe'].focus();
                            window.frames['print_iframe'].print();
                        }
                    }

                    PrintThisPage();
                }
            }]
    });
    
    win.show();
    win.center();
}