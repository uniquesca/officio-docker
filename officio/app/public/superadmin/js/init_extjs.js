Ext.BLANK_IMAGE_URL = topBaseUrl + '/js/ext/resources/images/default/s.gif';


//FIX: Hide ExtJS element including label
Ext.override(Ext.layout.FormLayout, {
        renderItem : function(c, position, target){
                if(c && !c.rendered && (c.isFormField || c.fieldLabel) && c.inputType != 'hidden'){
                        var args = this.getTemplateArgs(c);
                        if(typeof position == 'number'){
                                position = target.dom.childNodes[position] || null;
                        }
                        if(position){
                                c.itemCt = this.fieldTpl.insertBefore(position, args, true);
                        }else{
                                c.itemCt = this.fieldTpl.append(target, args, true);
                        }
                        c.actionMode = 'itemCt';
                        c.render('x-form-el-'+c.id);
                        c.container = c.itemCt;
                        c.actionMode = 'container';
                }else {
                        Ext.layout.FormLayout.superclass.renderItem.apply(this, arguments);
                }
        }
});
Ext.override(Ext.form.Field, {
        getItemCt : function(){
                return this.itemCt;
        }
});

// method checks if its value differs from its private originalValue  property
Ext.override(Ext.form.Field, {
    isDirty : function() {
        if (this.disabled || !this.rendered) {
            return false;
        }
        return String(this.getValue()) !== String(this.originalValue);
    }
});

Ext.override(Ext.Window, {
    //constrain the window within its containing element
    constrain: true,
    //fix function center()
    center: function(){
        if (typeof $ != 'undefined') {
            var xy = this.el.getAlignToXY(this.container, 'c-c');
            if (this.container.id == 'framePage') {
                xy[1] = $(parent.document.documentElement).scrollTop() + 100;
            }

            xy[1] = xy[1] < 0 ? 0 : xy[1]; //NEW

            this.setPagePosition(xy[0], xy[1]);
        }
        return this;
    }
});

// Always show decimals, e.g. 1.00 -> 1.00
// Previously was showed 1.00 -> 1
Ext.override(Ext.form.NumberField, {
    setValue : function(v){
        v = typeof v == 'number' ? v : String(v).replace(this.decimalSeparator, ".");
        v = isNaN(v) ? '' : String(v).replace(".", this.decimalSeparator);
        return Ext.form.NumberField.superclass.setValue.call(this, v);
    },
    fixPrecision : function(value){
        var nan = isNaN(value);
        if(!this.allowDecimals || this.decimalPrecision == -1 || nan || !value){
           return nan ? '' : value;
        }
        return parseFloat(value).toFixed(this.decimalPrecision);
    }
});

// Implements the default empty TriggerField.onTriggerClick function to display the DatePicker
// Change: adds a 'defaultValue' option to be used if the picker is opened on an empty field
Ext.override(Ext.form.DateField, {
    onTriggerClick : function(){
        if(this.disabled){
            return;
        }
        if(this.menu == null){
            this.menu = new Ext.menu.DateMenu({
                hideOnClick: false
            });
        }
        this.onFocus();
        Ext.apply(this.menu.picker,  {
            minDate : this.minValue,
            maxDate : this.maxValue,
            disabledDatesRE : this.disabledDatesRE,
            disabledDatesText : this.disabledDatesText,
            disabledDays : this.disabledDays,
            disabledDaysText : this.disabledDaysText,
            format : this.format,
            showToday : this.showToday,
            minText : String.format(this.minText, this.formatDate(this.minValue)),
            maxText : String.format(this.maxText, this.formatDate(this.maxValue))
        });

        if( typeof this.defaultValue == 'string' ) {
            this.defaultValue = Date.parseDate( this.defaultValue, this.format );
        }

        this.menu.picker.setValue(this.getValue() || this.defaultValue || new Date());
        this.menu.show(this.el, "tl-bl?");
        this.menuEvents('on');
    }
});

// Allow to filter the dropdown list with "contains" instead of "startswith"
Ext.override(Ext.form.ComboBox, {
        doQuery: function(q, forceAll) {
                q = Ext.isEmpty(q) ? '' : q;
                var qe = {
                        query: q,
                        forceAll: forceAll,
                        combo: this,
                        cancel: false
                };
                if (this.fireEvent('beforequery', qe) === false || qe.cancel) {
                        return false;
                }
                q = qe.query;
                forceAll = qe.forceAll;
                if (forceAll === true || (q.length >= this.minChars)) {
                        if (this.lastQuery !== q) {
                                this.lastQuery = q;
                                if (this.mode == 'local') {
                                        this.selectedIndex = -1;
                                        if (forceAll) {
                                                this.store.clearFilter();
                                        }else {
                                                // @NOTE: added new parameter
                                                var booSearchContains = this.searchContains || false;
                                                this.store.filter(this.displayField, q, booSearchContains);
                                        }
                                        this.onLoad();
                                }else {
                                        this.store.baseParams[this.queryParam] = q;
                                        this.store.load({
                                                params: this.getParams(q)
                                        });
                                        this.expand();
                                }
                        }else {
                                this.selectedIndex = -1;
                                this.onLoad();
                        }
                }
        }
});

// For display field make possible to format the value
Ext.override(Ext.form.DisplayField, {
    getValue : function(){
        return this.value;
    },
    setValue : function(v){
        this.value = v;
        this.setRawValue(this.formatValue(v));
        return this;
    },
    formatValue : function(v){
            if(this.dateFormat && Ext.isDate(v)){
                    return v.dateFormat(this.dateFormat);
            }
            if(this.numberFormat && typeof v == 'number'){
                    return Ext.util.Format.number(v, this.numberFormat);
            }
            return v;
    }
});


// Override the default width of the checkbox column
// So we'll have a bigger left padding
Ext.override(Ext.grid.CheckboxSelectionModel, {
    width: 30
});


// Fix issue with scroll in FF
Ext.override(Ext.form.HtmlEditor, {
    // Update: allow to have more than 2 font sizes when using Chrome or Safari
    adjustFont: function(btn){
        var adjust = btn.itemId == 'increasefontsize' ? 1 : -1;

        var v = parseInt(this.doc.queryCommandValue('FontSize') || 2, 10);
        if(Ext.isSafari){ // safari
            adjust *= 2;
        }
        v = Math.max(1, v+adjust) + (Ext.isSafari ? 'px' : 0);
        this.execCmd('FontSize', v);
    },

    // Fix: when adding a form on the landing page, if using chrome, some strange characters appear.
    insertAtCursor : function(text){
        if(!this.activated){
            return;
        }
        if(Ext.isIE){
            this.win.focus();
            var r = this.doc.selection.createRange();
            if(r){
                r.collapse(true);
                r.pasteHTML(text);
                this.syncValue();
                this.deferFocus();
            }
        }else if(Ext.isGecko || Ext.isOpera || Ext.isWebKit){
            this.win.focus();
            this.execCmd('InsertHTML', text);
            this.deferFocus();
        }
    },

    // Fix issue with scroll in FF
    getDocMarkup : function(){
        return '<html><head><style type="text/css">body{border:0;margin:0;padding:3px;cursor:text;}</style></head><body></body></html>';
    },

    setReadOnly: function(readOnly){
            if(readOnly){
                this.syncValue();
                var roMask = this.wrap.mask();
                roMask.dom.style.filter = "alpha(opacity=0.5);"; //IE
                roMask.dom.style.opacity = "0.5"; //Mozilla
                roMask.dom.style.background = "white";
                roMask.dom.style.overflow = "auto";
                roMask.dom.style.zIndex = 10;
                this.el.dom.readOnly = true;
            } else {
                if(this.rendered){
                    this.wrap.unmask();
                }
                this.el.dom.readOnly = false;
            }
    },

   // private
    onRender : function(ct, position){
        Ext.form.HtmlEditor.superclass.onRender.call(this, ct, position);
        this.el.dom.style.border = '0 none';
        this.el.dom.setAttribute('tabIndex', -1);
        this.el.addClass('x-hidden');
        if(Ext.isIE){ // fix IE 1px bogus margin
            this.el.applyStyles('margin-top:-1px;margin-bottom:-1px;');
        }
        this.wrap = this.el.wrap({
            cls:'x-html-editor-wrap', cn:{cls:'x-html-editor-tb'}
        });

        this.createToolbar(this);

        this.tb.items.each(function(item){
           if(item.itemId != 'sourceedit'){
                item.disable();
            }
        });

        var iframe = document.createElement('iframe');
        iframe.name = Ext.id();
        iframe.frameBorder = 'no';

        iframe.src=(Ext.SSL_SECURE_URL || "javascript:false");

        this.wrap.dom.appendChild(iframe);

        this.iframe = iframe;

        if(Ext.isIE){
            iframe.contentWindow.document.designMode = 'on';
            this.doc = iframe.contentWindow.document;
            this.win = iframe.contentWindow;
        } else {
            this.doc = (iframe.contentDocument || window.frames[iframe.name].document);
            this.win = window.frames[iframe.name];
            this.doc.designMode = 'on';
        }
        this.doc.open();
        this.doc.write(this.getDocMarkup());
        this.doc.close();

        var task = { // must defer to wait for browser to be ready
            run : function(){
                if(this.doc.body || this.doc.readyState == 'complete'){
                    Ext.TaskMgr.stop(task);
                    this.doc.designMode="on";
                    this.initEditor.defer(10, this);
                }
            },
            interval : 10,
            duration:10000,
            scope: this
        };
        Ext.TaskMgr.start(task);

        if (!this.width) {
            this.setSize(this.el.getSize());
        }

        this.setReadOnly(this.readOnly);

    }
});

// multi-drag-and-drop-fix start (dance with buben required)
// also, automatically uncheck the "check all" checkbox if not all rows are selected
Ext.grid.CheckboxSelectionModel.override({
    initEvents: function () {
        var gridObject = this;
        Ext.grid.CheckboxSelectionModel.superclass.initEvents.call(gridObject);
        if (gridObject.grid.enableDragDrop || gridObject.grid.enableDrag) {
            gridObject.grid.events['rowclick'].clearListeners();
        }

        gridObject.grid.on('render', function () {
            var view = gridObject.grid.getView();

            view.mainBody.on('mousedown', function (e, t) {
                this.onMouseDown(e, t);

                if (gridObject.grid) {
                    // Use a delay to get the count of currently checked items
                    setTimeout(function () {
                        var mappingChecker = $('#' + gridObject.grid.id + ' .x-grid3-header .x-grid3-hd-checker')[0];
                        if (gridObject.grid.getSelectionModel().getCount() == gridObject.grid.getStore().getCount()) {
                            $(mappingChecker).addClass('x-grid3-hd-checker-on');
                        } else {
                            $(mappingChecker).removeClass('x-grid3-hd-checker-on');
                        }
                    }, 50);
                }
            }, this);
            Ext.fly(view.innerHd).on('mousedown', this.onHdMouseDown, this);
        }, gridObject);
    }
});

Ext.grid.GridDragZone.override({
    handleMouseDown: function (e, t) {
        if (t.className == 'x-grid3-row-checker')
            return false;
        Ext.grid.GridDragZone.superclass.handleMouseDown.apply(this, arguments);
    }
});
// multi-drag-and-drop-fix end

// Automatically resize list width to the max item length in the combo
(function () {
    var originalOnLoad = Ext.form.ComboBox.prototype.onLoad;
    Ext.form.ComboBox.prototype.onLoad = function () {
        var padding = 25;
        var ret = originalOnLoad.apply(this, arguments);
        var max = Math.max(this.minListWidth || 0, this.el.getWidth());
        Ext.each(this.view.getNodes(), function (node) {
            if (node.scrollWidth) {
                max = Math.max(max, node.scrollWidth + padding);
            }
        });

        if (max > 0 && max - padding + 2 != this.list.getWidth(true)) {
            this.list.setWidth(max);
            this.innerList.setWidth(max - this.list.getFrameWidth('lr'));
        }
        return ret;
    };

    // Automatically show the list when combo gets a focus
    var originalOnFocus = Ext.form.ComboBox.prototype.initEvents;
    Ext.form.ComboBox.prototype.initEvents = function () {
        originalOnFocus.apply(this, arguments);

        var combo = this;
        if (combo.editable) {
            combo.on('focus', function () {
                if (!combo.isExpanded()) {
                    combo.restrictHeight();
                    combo.expand();
                }
            });
        }
    };
})();

// Show log out message
// Note the same method is in the /js/main.js file
var showLoggedOutMessage = function(msg) {
    var booShowMsg = false;
    var showMsg = 'You are logged out from this session.';

    try {
        if (msg == 'You have been logged out because you logged in on another computer or browser.' ||
           msg == 'You have been logged out because session has timed out.' ||
           msg == 'Access is denied during non-office hours.<br/>Please try again later.' ||
           msg == 'We are undergoing a regular system upgrade. The system will be available shortly.' ||
           msg == 'Insufficient access rights.' ||
           msg == 'You have been logged out from this session.') {
            booShowMsg = true;
            showMsg = msg;
        }
    } catch (e) {
        // Do nothing
    }

    if (booShowMsg) {
        Ext.Msg.show({
            title: 'Information',
            msg: showMsg,
            icon: Ext.MessageBox.WARNING,
            buttons: {yes: 'OK'},
            closable: false,
            fn: function(btn) {
                if (btn == 'yes') {
                    window.location = baseUrl;
                }
            }
        });
    }

    return booShowMsg;
};

// Listen for 'log out' message
// For ajax requests sent by extjs
Ext.util.Observable.observeClass(Ext.data.Connection);
Ext.data.Connection.on('requestexception', function(conn, response, options) {
    if (response.status === 401 && showLoggedOutMessage(response.responseText)) {
        // Don't process response in ajax's callback methods
        delete options.failure;
        delete options.callback;
    }
});

// For ajax requests sent by jquery
if (typeof $ != 'undefined') {
    $.ajaxSetup({
        timeout: 90000,
        error:   function (jqXHR) {
            if (jqXHR.status === 401) {
                showLoggedOutMessage(jqXHR.responseText);
            }
        }
    });
}

Ext.onReady(function () {
    Ext.Ajax.timeout = 90000;

    // Init tooltips
    Ext.QuickTips.init();
    // Don't hide tooltips automatically
    Ext.apply(Ext.QuickTips.getQuickTip(), {
        dismissDelay: 0
    });

    if (typeof(autoshow_error) !== 'undefined') {
        var strError = [];

        for (var i = 0; i < autoshow_error.length; i++) {
            if (!empty(autoshow_error[i])) {
                strError.push(autoshow_error[i]);
            }
        }

        if (strError.length) {
            Ext.simpleConfirmation.error(strError.join('<br />'));
        }
    }

    if (typeof(autoshow_info) !== 'undefined') {
        var strConfirmation = [];

        for (var j = 0; j < autoshow_info.length; j++) {
            if (!empty(autoshow_info[j])) {
                strConfirmation.push(autoshow_info[j]);
            }
        }

        if (strConfirmation.length) {
            Ext.simpleConfirmation.info(strConfirmation.join('<br />'));
        }
    }

    if (Ext.get('admin_help_icon') && booShowHelpIcon) {
        new Ext.Container({
            renderTo: 'admin_help_icon',
            items: [
                {
                    xtype: 'box',
                    autoEl: {
                        tag: 'a',
                        href: '#',
                        style: 'float: right; font-size: 24px',
                        html: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>'
                    },
                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                showHelpContextMenu(c.getEl(), 'admin');
                            }, this, {stopEvent: true});
                        }
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        style: 'clear: both'
                    }
                }
            ]
        });
    }
});