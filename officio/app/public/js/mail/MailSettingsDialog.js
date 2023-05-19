var MailSettingsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisDialog = this;

    thisDialog.email = new Ext.form.TextField({
        vtype: 'email',
        fieldLabel: _('Email Address'),
        width: 436,
        allowBlank: false,
        value: thisDialog.oData.email
    });

    thisDialog.auto_check = new Ext.form.Checkbox({
        boxLabel: _('Check mail automatically when mailbox opens'),
        itemCls: 'no-bottom-padding',
        hideLabel: true,
        checked: thisDialog.oData.auto_check == 'Y'
    });

    thisDialog.auto_check_every = new Ext.form.Checkbox({
        boxLabel: _('Check mail automatically when mailbox is open every (minutes)'),
        itemCls: 'no-bottom-padding',
        hideLabel: true,
        checked: thisDialog.oData.auto_check_every > 0
    });

    thisDialog.auto_check_every.on('check', function (o, ch) {
        thisDialog.auto_check_every_combo.setDisabled(!ch);
    });

    thisDialog.auto_check_every_combo = new Ext.form.ComboBox({
        itemCls: 'no-bottom-padding',
        store: new Ext.data.ArrayStore({
            fields: ['minutes', 'minutes_name'],
            data: [['5', '5'], ['10', '10'], ['15', '15'], ['30', '30'], ['60', '60']]
        }),
        hideLabel: true,
        valueField: 'minutes',
        displayField: 'minutes_name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 80,
        value: thisDialog.booAdd ? '5' : (thisDialog.oData.auto_check_every > 0 ? thisDialog.oData.auto_check_every : '5'),
        disabled: thisDialog.oData.auto_check_every === 0
    });

    thisDialog.per_page_combo = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['pages', 'pages_name'],
            data: [
                ['25', '25'],
                ['50', '50'],
                ['75', '75'],
                ['100', '100']
            ]
        }),
        fieldLabel: _('Number of emails per page'),
        valueField: 'pages',
        displayField: 'pages_name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 90,
        value: thisDialog.booAdd ? '25' : thisDialog.oData.per_page
    });

    thisDialog.timezone_combo = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['timezones', 'timezones_name'],
            data: [
                ["Kwajalein", "(GMT-12:00) International Date Line West"],
                ["Pacific/Midway", "(GMT-11:00) Midway Island"],
                ["Pacific/Samoa", "(GMT-11:00) Samoa"],
                ["Pacific/Honolulu", "(GMT-10:00) Hawaii"],
                ["America/Anchorage", "(GMT-09:00) Alaska"],
                ["America/Los_Angeles", "(GMT-08:00) Pacific Time (US & Canada)"],
                ["America/Tijuana", "(GMT-08:00) Tijuana, Baja California"],
                ["America/Denver", "(GMT-07:00) Mountain Time (US & Canada)"],
                ["America/Chihuahua", "(GMT-07:00) Chihuahua"],
                ["America/Mazatlan", "(GMT-07:00) Mazatlan"],
                ["America/Phoenix", "(GMT-07:00) Arizona"],
                ["America/Regina", "(GMT-06:00) Saskatchewan"],
                ["America/Tegucigalpa", "(GMT-06:00) Central America"],
                ["America/Chicago", "(GMT-06:00) Central Time (US & Canada)"],
                ["America/Mexico_City", "(GMT-06:00) Mexico City"],
                ["America/Monterrey", "(GMT-06:00) Monterrey"],
                ["America/New_York", "(GMT-05:00) Eastern Time (US & Canada)"],
                ["America/Bogota", "(GMT-05:00) Bogota"],
                ["America/Lima", "(GMT-05:00) Lima"],
                ["America/Rio_Branco", "(GMT-05:00) Rio Branco"],
                ["America/Indiana/Indianapolis", "(GMT-05:00) Indiana (East)"],
                ["America/Caracas", "(GMT-04:30) Caracas"],
                ["America/Halifax", "(GMT-04:00) Atlantic Time (Canada)"],
                ["America/Manaus", "(GMT-04:00) Manaus"],
                ["America/Santiago", "(GMT-04:00) Santiago"],
                ["America/La_Paz", "(GMT-04:00) La Paz"],
                ["America/St_Johns", "(GMT-03:30) Newfoundland"],
                ["America/Argentina/Buenos_Aires", "(GMT-03:00) Georgetown"],
                ["America/Sao_Paulo", "(GMT-03:00) Brasilia"],
                ["America/Godthab", "(GMT-03:00) Greenland"],
                ["America/Montevideo", "(GMT-03:00) Montevideo"],
                ["Atlantic/South_Georgia", "(GMT-02:00) Mid-Atlantic"],
                ["Atlantic/Azores", "(GMT-01:00) Azores"],
                ["Atlantic/Cape_Verde", "(GMT-01:00) Cape Verde Is."],
                ["Europe/Dublin", "(GMT) Dublin"],
                ["Europe/Lisbon", "(GMT) Lisbon"],
                ["Europe/London", "(GMT) London"],
                ["Africa/Monrovia", "(GMT) Monrovia"],
                ["Atlantic/Reykjavik", "(GMT) Reykjavik"],
                ["Africa/Casablanca", "(GMT) Casablanca"],
                ["Europe/Belgrade", "(GMT+01:00) Belgrade"],
                ["Europe/Bratislava", "(GMT+01:00) Bratislava"],
                ["Europe/Budapest", "(GMT+01:00) Budapest"],
                ["Europe/Ljubljana", "(GMT+01:00) Ljubljana"],
                ["Europe/Prague", "(GMT+01:00) Prague"],
                ["Europe/Sarajevo", "(GMT+01:00) Sarajevo"],
                ["Europe/Skopje", "(GMT+01:00) Skopje"],
                ["Europe/Warsaw", "(GMT+01:00) Warsaw"],
                ["Europe/Zagreb", "(GMT+01:00) Zagreb"],
                ["Europe/Brussels", "(GMT+01:00) Brussels"],
                ["Europe/Copenhagen", "(GMT+01:00) Copenhagen"],
                ["Europe/Madrid", "(GMT+01:00) Madrid"],
                ["Europe/Paris", "(GMT+01:00) Paris"],
                ["Africa/Algiers", "(GMT+01:00) West Central Africa"],
                ["Europe/Amsterdam", "(GMT+01:00) Amsterdam"],
                ["Europe/Berlin", "(GMT+01:00) Berlin"],
                ["Europe/Rome", "(GMT+01:00) Rome"],
                ["Europe/Stockholm", "(GMT+01:00) Stockholm"],
                ["Europe/Vienna", "(GMT+01:00) Vienna"],
                ["Europe/Minsk", "(GMT+02:00) Minsk"],
                ["Africa/Cairo", "(GMT+02:00) Cairo"],
                ["Europe/Helsinki", "(GMT+02:00) Helsinki"],
                ["Europe/Riga", "(GMT+02:00) Riga"],
                ["Europe/Sofia", "(GMT+02:00) Sofia"],
                ["Europe/Tallinn", "(GMT+02:00) Tallinn"],
                ["Europe/Vilnius", "(GMT+02:00) Vilnius"],
                ["Europe/Athens", "(GMT+02:00) Athens"],
                ["Europe/Bucharest", "(GMT+02:00) Bucharest"],
                ["Europe/Istanbul", "(GMT+02:00) Istanbul"],
                ["Asia/Jerusalem", "(GMT+02:00) Jerusalem"],
                ["Asia/Amman", "(GMT+02:00) Amman"],
                ["Asia/Beirut", "(GMT+02:00) Beirut"],
                ["Africa/Windhoek", "(GMT+02:00) Windhoek"],
                ["Africa/Harare", "(GMT+02:00) Harare"],
                ["Asia/Kuwait", "(GMT+03:00) Kuwait"],
                ["Asia/Riyadh", "(GMT+03:00) Riyadh"],
                ["Asia/Baghdad", "(GMT+03:00) Baghdad"],
                ["Africa/Nairobi", "(GMT+03:00) Nairobi"],
                ["Asia/Tbilisi", "(GMT+03:00) Tbilisi"],
                ["Europe/Moscow", "(GMT+03:00) Moscow"],
                ["Europe/Volgograd", "(GMT+03:00) Volgograd"],
                ["Asia/Tehran", "(GMT+03:30) Tehran"],
                ["Asia/Muscat", "(GMT+04:00) Muscat"],
                ["Asia/Baku", "(GMT+04:00) Baku"],
                ["Asia/Yerevan", "(GMT+04:00) Yerevan"],
                ["Asia/Yekaterinburg", "(GMT+05:00) Ekaterinburg"],
                ["Asia/Karachi", "(GMT+05:00) Karachi"],
                ["Asia/Tashkent", "(GMT+05:00) Tashkent"],
                ["Asia/Kolkata", "(GMT+05:30) Calcutta"],
                ["Asia/Colombo", "(GMT+05:30) Sri Jayawardenepura"],
                ["Asia/Katmandu", "(GMT+05:45) Kathmandu"],
                ["Asia/Dhaka", "(GMT+06:00) Dhaka"],
                ["Asia/Almaty", "(GMT+06:00) Almaty"],
                ["Asia/Novosibirsk", "(GMT+06:00) Novosibirsk"],
                ["Asia/Rangoon", "(GMT+06:30) Yangon (Rangoon)"],
                ["Asia/Krasnoyarsk", "(GMT+07:00) Krasnoyarsk"],
                ["Asia/Bangkok", "(GMT+07:00) Bangkok"],
                ["Asia/Jakarta", "(GMT+07:00) Jakarta"],
                ["Asia/Brunei", "(GMT+08:00) Beijing"],
                ["Asia/Chongqing", "(GMT+08:00) Chongqing"],
                ["Asia/Hong_Kong", "(GMT+08:00) Hong Kong"],
                ["Asia/Urumqi", "(GMT+08:00) Urumqi"],
                ["Asia/Irkutsk", "(GMT+08:00) Irkutsk"],
                ["Asia/Ulaanbaatar", "(GMT+08:00) Ulaan Bataar"],
                ["Asia/Kuala_Lumpur", "(GMT+08:00) Kuala Lumpur"],
                ["Asia/Singapore", "(GMT+08:00) Singapore"],
                ["Asia/Taipei", "(GMT+08:00) Taipei"],
                ["Australia/Perth", "(GMT+08:00) Perth"],
                ["Asia/Seoul", "(GMT+09:00) Seoul"],
                ["Asia/Tokyo", "(GMT+09:00) Tokyo"],
                ["Asia/Yakutsk", "(GMT+09:00) Yakutsk"],
                ["Australia/Darwin", "(GMT+09:30) Darwin"],
                ["Australia/Adelaide", "(GMT+09:30) Adelaide"],
                ["Australia/Canberra", "(GMT+10:00) Canberra"],
                ["Australia/Melbourne", "(GMT+10:00) Melbourne"],
                ["Australia/Sydney", "(GMT+10:00) Sydney"],
                ["Australia/Brisbane", "(GMT+10:00) Brisbane"],
                ["Australia/Hobart", "(GMT+10:00) Hobart"],
                ["Asia/Vladivostok", "(GMT+10:00) Vladivostok"],
                ["Pacific/Guam", "(GMT+10:00) Guam"],
                ["Pacific/Port_Moresby", "(GMT+10:00) Port Moresby"],
                ["Asia/Magadan", "(GMT+11:00) Magadan"],
                ["Pacific/Fiji", "(GMT+12:00) Fiji"],
                ["Asia/Kamchatka", "(GMT+12:00) Kamchatka"],
                ["Pacific/Auckland", "(GMT+12:00) Auckland"],
                ["Pacific/Tongatapu", "(GMT+13:00) Nukualofa"]
            ]
        }),
        fieldLabel: _('Time Zone'),
        valueField: 'timezones',
        displayField: 'timezones_name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 370,
        value: thisDialog.booAdd ? company_timezone : thisDialog.oData.timezone
    });

    thisDialog.account_name = new Ext.form.TextField({
        fieldLabel: _('Name to appear on sent emails'),
        width: 436,
        allowBlank: true,
        value: thisDialog.oData.friendly_name
    });

    thisDialog.signature = new Ext.ux.form.FroalaEditor({
        fieldLabel: _('Email Signature'),
        hideLabel: false,
        height: 150,
        width: 800,
        allowBlank: true,
        value: '',
        booAllowImagesUploading: true
    });


    // *************************** Outgoing mail server ************************ //
    thisDialog.out_use_officio = new Ext.form.Radio({
        checked: thisDialog.oData.out_use_own != 'Y',
        hideLabel: true,
        itemCls: 'radio-no-bottom-padding',
        boxLabel: _("Use Officio's SMTP server"),
        inputValue: '0',
        name: 'radio-use-officio-smtp'
    });

    thisDialog.out_use_officio.on('check', function (o, ch) {
        thisDialog.disableEnableOwnSMTP(ch);
        if (!ch) {
            thisDialog.disableEnableSMTPAuthRequired(!thisDialog.out_auth_required.checked);
        }
    });

    thisDialog.out_use_own = new Ext.form.Radio({
        checked: thisDialog.oData.out_use_own == 'Y',
        hideLabel: true,
        boxLabel: _('Use your own SMTP server (highly recommended for best routing of your outgoing emails)'),
        inputValue: '1',
        name: 'radio-use-officio-smtp'
    });


    var suggestionsReader = new Ext.data.JsonReader({
        root: 'rows',
        totalProperty: 'totalCount',
        id: 'id'
    }, [
        {name: 'show_name', convert: thisDialog.generateName},
        {name: 'name'},
        {name: 'type'},
        {name: 'host'},
        {name: 'port'},
        {name: 'ssl'}
    ]);

    // Custom rendering Template
    var outSuggestionsTpl = new Ext.XTemplate(
        '<tpl for=".">',
        '<div class="x-combo-list-item search-item">' +
        '{show_name:this.highlightSearch}' +
        '</div>',
        '</tpl>', {
            highlightSearch: function (highlightedRow) {
                var data = outSuggestionsStore.reader.jsonData;
                for (var i = 0, len = data.search.length; i < len; i++) {
                    var val = data.search[i];
                    highlightedRow = highlightedRow.replace(
                        new RegExp('(' + preg_quote(val) + ')', 'gi'),
                        "<b style='background-color: #FFFF99;'>$1</b>"
                    );
                }
                return highlightedRow;
            }
        }
    );

    var outSuggestionsStore = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/mailer/settings/get-mail-server-suggestions',
            method: 'post'
        }),

        baseParams: {
            type: 'smtp'
        },

        reader: suggestionsReader
    });

    thisDialog.out_host = new Ext.form.ComboBox({
        fieldLabel: _('Outgoing mail server (SMTP)'),
        store: outSuggestionsStore,
        displayField: 'title',
        typeAhead: false,
        emptyText: _('Type to search for suggestions...'),
        loadingText: _('Searching...'),
        width: 300,
        listWidth: 300,
        listClass: 'no-pointer',
        cls: 'with-right-border',
        pageSize: 10,
        minChars: 2,
        hideTrigger: true,
        tpl: outSuggestionsTpl,
        itemSelector: 'div.x-combo-list-item',
        allowBlank: false,
        value: thisDialog.oData.out_host,
        onSelect: function (record) {
            thisDialog.out_host.setValue(record.data.host);
            thisDialog.out_port.setValue(record.data.port);
            thisDialog.out_ssl.setValue(record.data.ssl);

            // Hide the search list
            this.collapse();
        },
        listeners: {
            blur: {
                fn: function (e) {
                    e.setValue(e.getValue().trim());
                },
                element: 'inputEl'
            }
        }
    });

    thisDialog.out_port = new Ext.form.NumberField({
        fieldLabel: _('Port'),
        width: 80,
        value: thisDialog.booAdd || empty(thisDialog.oData.out_port) ? '25' : thisDialog.oData.out_port,
        allowDecimals: false,
        allowNegative: false,
        allowBlank: false
    });

    thisDialog.out_auth_required = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        name: 'out_auth_required',
        boxLabel: _('Authentification required'),
        checked: thisDialog.booAdd ? true : thisDialog.oData.out_auth_required == 'Y'
    });

    thisDialog.out_auth_required.on('check', function (o, ch) {
        thisDialog.disableEnableSMTPAuthRequired(!ch);
    });

    thisDialog.out_login = new Ext.form.TextField({
        fieldLabel: _('Username'),
        width: 300,
        allowBlank: false,
        value: thisDialog.oData.out_login
    });

    thisDialog.out_password = new Ext.form.TextField({
        fieldLabel: _('Password'),
        width: 300,
        allowBlank: false,
        inputType: 'password',
        value: thisDialog.oData.out_password
    });

    thisDialog.out_ssl = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['ssl_type', 'ssl_name'],
            data: [['', 'None'], ['ssl', 'SSL'], ['tls', 'TLS']]
        }),
        fieldLabel: _('Encryption'),
        valueField: 'ssl_type',
        displayField: 'ssl_name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 90,
        value: thisDialog.booAdd ? '' : thisDialog.oData.out_ssl
    });

    thisDialog.out_login_type = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['login_type', 'login_type_label'],
            data: [['', 'Normal Password'], ['oauth2', 'OAuth2']]
        }),
        fieldLabel: _('Authentication'),
        valueField: 'login_type',
        displayField: 'login_type_label',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: false,
        width: 170,
        value: thisDialog.booAdd ? '' : empty(thisDialog.oData.out_login_type) ? '' : thisDialog.oData.out_login_type
    });

    thisDialog.out_save_sent = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        name: 'out_save_sent',
        boxLabel: _('Save sent items to Sent folder on mail server'),
        checked: thisDialog.booAdd ? true : thisDialog.oData.out_save_sent == 'Y'
    });

    // *************************** Incoming mail server ************************ //
    thisDialog.enableIncoming = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        boxLabel: _('Enable Incoming Email Account'),
        checked: thisDialog.oData.inc_enabled == 'Y'
    });

    thisDialog.enableIncoming.on('check', function (o, ch) {
        thisDialog.disableEnableIncomingMailAccount(!ch);
    });

    thisDialog.inc_type = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['srv_type_id', 'srv_type_name'],
            data: [['pop3', 'POP3'], ['imap', 'IMAP']]
        }),
        fieldLabel: _('Server Type'),
        valueField: 'srv_type_id',
        displayField: 'srv_type_name',
        typeAhead: true,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 110,
        editable: false,
        value: thisDialog.booAdd ? 'pop3' : thisDialog.oData.inc_type,
        listeners: {
            select: function () {
                thisDialog.disableEnableIMAPOnlySettings(this.getValue() != 'imap');
            }
        }
    });

    // Custom rendering Template
    var incSuggestionsTpl = new Ext.XTemplate(
        '<tpl for=".">',
        '<div class="x-combo-list-item search-item">' +
        '{show_name:this.highlightSearch}' +
        '</div>',
        '</tpl>', {
            highlightSearch: function (highlightedRow) {
                var data = incSuggestionsStore.reader.jsonData;
                for (var i = 0, len = data.search.length; i < len; i++) {
                    var val = data.search[i];
                    highlightedRow = highlightedRow.replace(
                        new RegExp('(' + preg_quote(val) + ')', 'gi'),
                        "<b style='background-color: #FFFF99;'>$1</b>"
                    );
                }
                return highlightedRow;
            }
        }
    );

    var incSuggestionsStore = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/mailer/settings/get-mail-server-suggestions',
            method: 'post'
        }),

        listeners: {
            'beforeload': function (store, options) {
                options.params = options.params || {};

                var params = {
                    type: thisDialog.inc_type.getValue()
                };

                Ext.apply(options.params, params);
            }
        },

        reader: suggestionsReader
    });

    thisDialog.inc_host = new Ext.form.ComboBox({
        fieldLabel: _('Mail server'),
        store: incSuggestionsStore,
        displayField: 'title',
        typeAhead: false,
        emptyText: _('Type to search for suggestions...'),
        loadingText: _('Searching...'),
        width: 300,
        listWidth: 300,
        listClass: 'no-pointer',
        cls: 'with-right-border',
        pageSize: 10,
        minChars: 2,
        hideTrigger: true,
        tpl: incSuggestionsTpl,
        itemSelector: 'div.x-combo-list-item',
        allowBlank: false,
        value: thisDialog.oData.inc_host,

        onSelect: function (record) {
            thisDialog.inc_host.setValue(record.data.host);
            thisDialog.inc_port.setValue(record.data.port);
            thisDialog.inc_ssl.setValue(record.data.ssl);

            // Hide the search list
            this.collapse();
        },

        listeners: {
            // delete the previous query in the beforequery event or set
            // combo.lastQuery = null (this will reload the store the next time it expands)
            beforequery: function (qe) {
                delete qe.combo.lastQuery;
            },

            blur: {
                fn: function (e) {
                    e.setValue(e.getValue().trim());
                },
                element: 'inputEl'
            }
        }
    });

    thisDialog.inc_port = new Ext.form.NumberField({
        fieldLabel: _('Port'),
        width: 80,
        value: thisDialog.booAdd ? '110' : thisDialog.oData.inc_port,
        allowDecimals: false,
        allowNegative: false,
        allowBlank: false
    });

    thisDialog.inc_login_type = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['login_type', 'login_type_label'],
            data: [['', 'Normal Password'], ['oauth2', 'OAuth2']]
        }),
        fieldLabel: _('Authentication'),
        valueField: 'login_type',
        displayField: 'login_type_label',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: false,
        width: 170,
        value: thisDialog.booAdd ? '' : empty(thisDialog.oData.inc_login_type) ? '' : thisDialog.oData.inc_login_type
    });

    thisDialog.inc_login = new Ext.form.TextField({
        fieldLabel: _('Username'),
        itemCls: 'no-bottom-padding',
        width: 300,
        allowBlank: false,
        value: thisDialog.oData.inc_login
    });

    thisDialog.inc_login.on('blur', function () {
        if (thisDialog.out_login.getValue() === '') {
            thisDialog.out_login.setValue(thisDialog.inc_login.getValue());
        }
    });


    thisDialog.inc_password = new Ext.form.TextField({
        fieldLabel: _('Password'),
        itemCls: 'no-bottom-padding',
        width: 300,
        inputType: 'password',
        allowBlank: false,
        value: thisDialog.oData.inc_password
    });

    thisDialog.inc_login_notice = new Ext.form.Label({
        style: 'color:#777; font-size: 14px; line-height: 18px',
        html: _('Often your username is your email address (e.g. john@domain.com) or your email address<br>without the domain name (e.g. john)')
    });

    thisDialog.inc_ssl = new Ext.form.ComboBox({
        store: new Ext.data.ArrayStore({
            fields: ['ssl_type', 'ssl_name'],
            data: [['', 'None'], ['ssl', 'SSL'], ['tls', 'TLS']]
        }),
        fieldLabel: _('Encryption'),
        valueField: 'ssl_type',
        displayField: 'ssl_name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 90,
        value: thisDialog.booAdd ? '' : thisDialog.oData.inc_ssl
    });

    thisDialog.inc_leave_messages = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        boxLabel: _('Leave a copy of retrieved message on the server'),
        itemCls: 'no-bottom-padding',
        checked: thisDialog.booAdd ? true : thisDialog.oData.inc_leave_messages == 'Y'
    });

    thisDialog.inc_headers_only = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        boxLabel: _('Fetch only headers'),
        itemCls: 'no-bottom-padding small-top-padding',
        checked: thisDialog.booAdd ? true : thisDialog.oData.inc_only_headers == 'Y'
    });

    thisDialog.inc_fetch_from_date_checkbox = new Ext.form.Checkbox({
        hideLabel: true,
        labelSeparator: '',
        boxLabel: _('Fetch starting from:'),
        checked: thisDialog.booAdd ? true : !empty(thisDialog.oData.inc_fetch_from_date),
        listeners: {
            check: function () {
                thisDialog.inc_fetch_from_date.setDisabled(!this.checked);
            }
        }
    });

    var newDate;
    if (thisDialog.booAdd) {
        newDate = new Date();
    } else if (!empty(thisDialog.oData.inc_fetch_from_date)) {
        var dateValues = thisDialog.oData.inc_fetch_from_date.split('-');
        var dt = new Date(dateValues[0], dateValues[1] - 1, dateValues[2]);
        newDate = dt.format(dateFormatFull);
    }

    thisDialog.inc_fetch_from_date = new Ext.form.DateField({
        width: 150,
        hideLabel: true,
        allowBlank: false,
        labelSeparator: '',
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort,
        value: newDate,
        maxLength: 12 // Fix issue with date entering in 'full' format
    });

    thisDialog.ImapFoldersButton = new Ext.Button({
        text: '<i class="las la-folder"></i>' + _('IMAP Folders'),
        tooltip: thisDialog.booAdd ? _('Please save the settings first. After that it will be possible to manage IMAP folders and subscriptions.') : _('Click to manage IMAP folders and subscriptions.'),
        handler: function () {
            var dialog = new MailImapFoldersDialog({
                accountId: thisDialog.oData.id
            });
            dialog.show();
            dialog.center();
        }
    });

    // *************************** Panel and buttons ************************ //
    thisDialog.tab_1 = new Ext.FormPanel({
        title: ('General Settings'),
        autoWidth: true,
        autoHeight: true,
        labelAlign: 'top',
        bodyStyle: 'padding: 10px 18px',
        items: [
            {
                layout: 'form',
                rowspan: 2,
                items: [
                    thisDialog.email,
                    thisDialog.account_name,
                    thisDialog.timezone_combo,
                    thisDialog.per_page_combo
                ]
            },
            {
                layout: 'form',
                items: [thisDialog.signature]
            }
        ]
    });

    thisDialog.tab_2 = new Ext.FormPanel({
        title: _('Incoming Mail Server'),
        autoWidth: true,
        autoHeight: true,
        labelAlign: 'top',
        bodyStyle: 'padding: 10px 18px',
        items: [
            thisDialog.enableIncoming,

            {
                layout: 'table',
                cls: 'cell-align-middle',
                layoutConfig: {columns: 3},
                items: [
                    {
                        layout: 'form',
                        labelWidth: 50,
                        items: thisDialog.inc_type
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.ImapFoldersButton
                    }
                ]
            },
            {
                layout: 'table',
                layoutConfig: {columns: 4},
                items: [
                    {
                        layout: 'form',
                        items: thisDialog.inc_host
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.inc_port
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.inc_ssl
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.inc_login_type
                    }
                ]
            },
            {
                layout: 'table',
                layoutConfig: {columns: 3},
                cls: 'cell-align-middle',

                items: [
                    {
                        layout: 'form',
                        labelWidth: 170,
                        items: thisDialog.inc_login
                    }, {
                        layout: 'form',
                        labelWidth: 170,
                        style: 'padding-left: 10px',
                        items: thisDialog.inc_password
                    },
                    {
                        layout: 'form',
                        items: {
                            xtype: 'button',
                            fieldLabel: '&nbsp;',
                            labelSeparator: '',
                            iconCls: 'password-visible-icon',
                            itemCls: 'no-bottom-padding',
                            hidden: !thisDialog.booAdd,
                            handler: function () {
                                var booShowPassword = this.iconCls === 'password-visible-icon';
                                this.setIconClass(booShowPassword ? 'password-invisible-icon' : 'password-visible-icon');
                                thisDialog.inc_password.getEl().set({type: booShowPassword ? 'text' : 'password'});
                            }
                        }
                    }
                ]
            },
            {
                layout: 'form',
                style: 'padding: 9px 0 20px',
                items: thisDialog.inc_login_notice
            },
            thisDialog.auto_check,
            {
                layout: 'table',
                cls: 'cell-align-middle',
                layoutConfig: {columns: 2},
                items: [{
                    layout: 'form',
                    labelWidth: 170,
                    items: thisDialog.auto_check_every
                },
                    {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        labelWidth: 25,
                        items: thisDialog.auto_check_every_combo
                    }]
            },
            thisDialog.inc_leave_messages,
            thisDialog.inc_headers_only,
            {
                layout: 'table',
                cls: 'cell-align-middle',
                layoutConfig: {columns: 2},
                items: [
                    {
                        layout: 'form',
                        items: thisDialog.inc_fetch_from_date_checkbox
                    },
                    {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.inc_fetch_from_date
                    }
                ]
            }
        ]
    });

    thisDialog.tab_3 = new Ext.FormPanel({
        title: _('Outgoing Mail Server (SMTP Mail Server)'),
        autoWidth: true,
        autoHeight: true,
        labelAlign: 'top',
        bodyStyle: 'padding: 10px 18px',
        items: [
            thisDialog.out_use_officio,
            thisDialog.out_use_own,
            {
                layout: 'table',
                layoutConfig: {columns: 4},
                items: [
                    {
                        layout: 'form',
                        items: thisDialog.out_host
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.out_port
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.out_ssl
                    }, {
                        layout: 'form',
                        style: 'padding-left: 10px',
                        items: thisDialog.out_login_type
                    }
                ]
            },
            thisDialog.out_auth_required,
            {
                layout: 'table',
                layoutConfig: {columns: 3},
                cls: 'cell-align-middle',

                items: [
                    {
                        layout: 'form',
                        labelWidth: 170,
                        items: thisDialog.out_login
                    },
                    {
                        layout: 'form',
                        labelWidth: 170,
                        style: 'padding-left: 10px',
                        items: thisDialog.out_password
                    },
                    {
                        layout: 'form',
                        items: {
                            xtype: 'button',
                            fieldLabel: '&nbsp;',
                            labelSeparator: '',
                            iconCls: 'password-visible-icon',
                            hidden: !thisDialog.booAdd,
                            handler: function () {
                                var booShowPassword = this.iconCls === 'password-visible-icon';
                                this.setIconClass(booShowPassword ? 'password-invisible-icon' : 'password-visible-icon');
                                thisDialog.out_password.getEl().set({type: booShowPassword ? 'text' : 'password'});
                            }
                        }
                    }
                ]
            },
            thisDialog.out_save_sent
        ]
    });

    thisDialog.edit_pan = new Ext.TabPanel({
        items: [
            thisDialog.tab_1,
            thisDialog.tab_2,
            thisDialog.tab_3
        ],

        activeTab: 0,
        frame: false,
        plain: true,
        cls: 'tabs-second-level',
        listeners: {
            tabchange: function () {
                thisDialog.syncShadow();
            }
        }
    });

    MailSettingsDialog.superclass.constructor.call(this, {
        id: 'mail-settings-dialog',
        title: thisDialog.booAdd ? '<i class="las la-plus"></i>' + _('Add Email Account') : '<i class="las la-edit"></i>' + _('Edit Email Account'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        y: 10,
        layout: 'form',
        items: thisDialog.edit_pan,
        buttons: [
            {
                style: 'margin-bottom: 8px',
                text: '<i class="las la-envelope"></i>' + _('Test Account Settings'),
                handler: thisDialog.testMailAccountSettings.createDelegate(thisDialog)
            },
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
            },
            {
                cls: 'orange-btn',
                text: _('Save'),
                handler: thisDialog.saveMailAccountSettings.createDelegate(thisDialog)
            }
        ]
    });

    this.on('show', this.applyDefaultDialogSettings.createDelegate(this, []), this);
};

Ext.extend(MailSettingsDialog, Ext.Window, {
    applyDefaultDialogSettings: function () {
        var thisDialog = this;

        if (thisDialog.booAdd && thisDialog.owner.store.getCount() > 0) {
            thisDialog.enableIncoming.setValue(1);
            thisDialog.enableIncoming.disable();
        }

        // Update (enable/disable) all fields in 2 fieldsets
        thisDialog.disableEnableIncomingMailAccount(!thisDialog.enableIncoming.checked);
        thisDialog.disableEnableOwnSMTP(!thisDialog.out_use_own.checked);

        if (!thisDialog.out_use_officio.checked) {
            thisDialog.disableEnableSMTPAuthRequired(!thisDialog.out_auth_required.checked);
        }

        thisDialog.signature.setValue(thisDialog.oData.signature);
    },

    disableEnableIncomingMailAccount: function (booDisable) {
        var thisDialog = this;

        // Update top section
        thisDialog.auto_check.setDisabled(booDisable);
        thisDialog.auto_check_every.setDisabled(booDisable);
        thisDialog.auto_check_every_combo.setDisabled(booDisable);
        thisDialog.auto_check_every_combo.clearInvalid();

        // Update middle section (Incoming Mail Server)
        thisDialog.inc_type.setDisabled(booDisable);
        thisDialog.inc_type.clearInvalid();
        thisDialog.inc_host.setDisabled(booDisable);
        thisDialog.inc_host.clearInvalid();
        thisDialog.inc_port.setDisabled(booDisable);
        thisDialog.inc_port.clearInvalid();
        thisDialog.inc_login_type.setDisabled(booDisable);
        thisDialog.inc_login_type.clearInvalid();
        thisDialog.inc_login.setDisabled(booDisable);
        thisDialog.inc_login.clearInvalid();
        thisDialog.inc_password.setDisabled(booDisable);
        thisDialog.inc_password.clearInvalid();
        thisDialog.inc_login_notice.setDisabled(booDisable);
        thisDialog.inc_ssl.setDisabled(booDisable);
        thisDialog.inc_ssl.clearInvalid();

        // Update bottom section
        thisDialog.inc_leave_messages.setDisabled(booDisable);

        var booIMAP = thisDialog.inc_type.getValue() == 'imap';
        thisDialog.disableEnableIMAPOnlySettings(booDisable || !booIMAP);
    },

    disableEnableIMAPOnlySettings: function (booDisable) {
        var thisDialog = this;

        // For IMAP account check and disable "leave messages" checkbox
        if (!booDisable) {
            thisDialog.inc_leave_messages.setValue(true);
        }
        thisDialog.inc_leave_messages.setDisabled(booDisable);
        thisDialog.inc_leave_messages.setVisible(!booDisable);

        thisDialog.inc_headers_only.setDisabled(booDisable);
        thisDialog.inc_headers_only.setVisible(!booDisable);
        thisDialog.inc_fetch_from_date_checkbox.setDisabled(booDisable);
        thisDialog.inc_fetch_from_date_checkbox.setVisible(!booDisable);
        thisDialog.inc_fetch_from_date.setDisabled(booDisable || !thisDialog.inc_fetch_from_date_checkbox.checked);
        thisDialog.inc_fetch_from_date.setVisible(!booDisable);
        thisDialog.ImapFoldersButton.setDisabled(booDisable || thisDialog.booAdd);
        thisDialog.ImapFoldersButton.setVisible(!booDisable);

        thisDialog.out_save_sent.setDisabled(booDisable);
    },

    disableEnableOwnSMTP: function (booDisable) {
        var thisDialog = this;

        thisDialog.out_host.setDisabled(booDisable);
        thisDialog.out_host.clearInvalid();
        thisDialog.out_port.setDisabled(booDisable);
        thisDialog.out_port.clearInvalid();
        thisDialog.out_auth_required.setDisabled(booDisable);
        thisDialog.out_login.setDisabled(booDisable);
        thisDialog.out_login.clearInvalid();
        thisDialog.out_password.setDisabled(booDisable);
        thisDialog.out_password.clearInvalid();
        thisDialog.out_login_type.setDisabled(booDisable);
        thisDialog.out_login_type.clearInvalid();
        thisDialog.out_ssl.setDisabled(booDisable);
    },

    disableEnableSMTPAuthRequired: function (booDisable) {
        var thisDialog = this;

        thisDialog.out_login.setDisabled(booDisable);
        thisDialog.out_login.clearInvalid();
        thisDialog.out_password.setDisabled(booDisable);
        thisDialog.out_password.clearInvalid();
    },

    generateName: function (v, record) {
        return record.name + ' - ' + record.host;
    },

    /**
     * Check if all tabs are valid
     * @returns {boolean} true if all tabs are valid, otherwise false
     */
    areAllSettingsValid: function () {
        var thisDialog = this;

        var activeTab = thisDialog.edit_pan.getActiveTab();
        thisDialog.edit_pan.setActiveTab(0);
        thisDialog.edit_pan.setActiveTab(1);
        thisDialog.edit_pan.setActiveTab(2);
        thisDialog.edit_pan.setActiveTab(activeTab);

        var booValid = false;
        if (!thisDialog.tab_1.getForm().isValid()) {
            thisDialog.edit_pan.setActiveTab(0);
        } else if (!thisDialog.tab_2.getForm().isValid()) {
            thisDialog.edit_pan.setActiveTab(1);
        } else if (!thisDialog.tab_3.getForm().isValid()) {
            thisDialog.edit_pan.setActiveTab(2);
        } else {
            booValid = true;
        }

        return booValid;
    },

    saveMailAccountSettings: function () {
        var thisDialog = this;

        if (thisDialog.areAllSettingsValid()) {
            var dateValue = Ext.util.Format.date(thisDialog.inc_fetch_from_date.getValue(), 'Y-m-d');

            thisDialog.getEl().mask('Saving...');
            Ext.Ajax.request({
                url: topBaseUrl + '/mailer/settings/save',
                params: {
                    email_account_id: thisDialog.booAdd ? 0 : thisDialog.oData.id,
                    member_id: Ext.encode(curr_member_id),

                    email: thisDialog.email.getValue().trim(),
                    auto_check: Ext.encode(thisDialog.auto_check.checked),
                    auto_check_every: Ext.encode(thisDialog.auto_check_every.checked ? thisDialog.auto_check_every_combo.getValue() : 0),
                    name: thisDialog.account_name.getValue(),

                    signature: thisDialog.signature.getValue(),

                    inc_enabled: thisDialog.enableIncoming.checked,
                    inc_type: thisDialog.inc_type.getValue(),
                    inc_host: thisDialog.inc_host.getValue() ? thisDialog.inc_host.getValue().trim() : '',
                    inc_port: thisDialog.inc_port.getValue(),
                    inc_login: thisDialog.inc_login.getValue() ? thisDialog.inc_login.getValue().trim() : '',
                    inc_password: thisDialog.inc_password.getValue(),
                    inc_login_type: thisDialog.inc_login_type.getValue(),
                    inc_ssl: thisDialog.inc_ssl.getValue(),
                    inc_leave_messages: Ext.encode(thisDialog.inc_leave_messages.checked),
                    inc_headers_only: Ext.encode(thisDialog.inc_headers_only.checked),
                    inc_fetch_from_date: thisDialog.inc_fetch_from_date_checkbox.checked ? dateValue : 0,

                    out_use_own: thisDialog.out_use_own.checked,
                    out_host: thisDialog.out_host.getValue() ? thisDialog.out_host.getValue().trim() : '',
                    out_port: thisDialog.out_port.getValue(),
                    out_auth_required: Ext.encode(thisDialog.out_auth_required.checked),
                    out_login: thisDialog.out_login.getValue() ? thisDialog.out_login.getValue().trim() : '',
                    out_password: thisDialog.out_password.getValue(),
                    out_login_type: thisDialog.out_login_type.getValue(),
                    out_ssl: thisDialog.out_ssl.getValue(),
                    out_save_sent: Ext.encode(thisDialog.out_save_sent.checked),

                    per_page: thisDialog.per_page_combo.getValue(),
                    timezone: thisDialog.timezone_combo.getValue(),

                    is_default: thisDialog.oData['is_default']
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        // Refresh accounts list
                        thisDialog.owner.store.reload();

                        thisDialog.getEl().mask('Done');

                        var mailGridWithPreview = Ext.getCmp('mail-grid-with-preview');
                        if (thisDialog.booAdd && mailGridWithPreview) {
                            var mailToolbar = Ext.getCmp('mail-main-toolbar');

                            if (mailToolbar) {
                                mailToolbar.previewMode = 'right';
                            }

                            mailGridWithPreview.movePreview('right', true);
                        }

                        setTimeout(function () {
                            thisDialog.getEl().unmask();
                            thisDialog.close();
                        }, 750);
                    } else {
                        // Show error message
                        Ext.simpleConfirmation.error(resultData.message);
                        thisDialog.getEl().unmask();
                    }
                },
                failure: function () {
                    Ext.simpleConfirmation.error(_('An error occurred during email account saving.<br/>Please try again later.'));
                    thisDialog.getEl().unmask();
                }
            });
        }
    },

    testMailAccountSettings: function () {
        var thisDialog = this;

        if (thisDialog.areAllSettingsValid()) {
            // Mark main window with shadow
            thisDialog.getEl().mask(_('Testing mail server settings.  Please wait a moment...'));

            // Show result message
            var showTestResult = function (type, message, booWarning) {
                var label = Ext.getCmp('test_result_' + type);
                if (!empty(label)) { // There is a label, lets upate status
                    var image, strResult;
                    if (empty(message)) {
                        image = 'tick';
                        if (type === 'smtp') {
                            strResult = _('Your Outgoing mail server settings are successfully verified. A test message is sent to your email.');
                        } else {
                            strResult = _('Your Incoming mail server settings are successfully verified.');
                        }
                    } else {
                        image = booWarning ? 'error' : 'exclamation';
                        strResult = message;
                    }

                    label.el.dom.innerHTML = '<img src=' + topBaseUrl + '/images/icons/' + image + '.png style="vertical-align: middle; padding-right: 5px;" />' + strResult;

                    if (empty(smtpRequest) && empty(pop3Request)) {
                        thisRefeshBtn.setDisabled(false);
                    }

                    // Fix shadow bug
                    testWindow.syncShadow();
                }
            };

            var updateTestStatus = function (status) {
                var strStatus = '';
                switch (status) {
                    case 'cancel' :
                        strStatus = '<img src="' + topBaseUrl + '/images/icons/cancel.png" style="vertical-align: middle; padding-right: 5px;" /> Canceled.';
                        break;

                    case 'wait' :
                        strStatus = '<img src="' + topBaseUrl + '/images/loading.gif" style="vertical-align: middle; padding-right: 5px;" /> Testing...';
                        break;

                    default:
                        break;
                }

                if (!empty(strStatus)) {
                    var label = Ext.getCmp('test_result_smtp');
                    if (!empty(label)) { // There is a label, lets update status
                        if (status !== 'cancel' || (status === 'cancel' && label.el.dom.innerHTML.indexOf(_('Testing...')) > 0)) {
                            label.el.dom.innerHTML = strStatus;
                        }
                    }

                    label = Ext.getCmp('test_result_pop3');
                    if (!empty(label)) { // There is a label, lets update status
                        if (status !== 'cancel' || (status === 'cancel' && label.el.dom.innerHTML.indexOf(_('Testing...')) > 0)) {
                            label.el.dom.innerHTML = strStatus;
                        }
                    }

                    // Fix shadow bug
                    testWindow.syncShadow();
                }
            };

            var smtpRequest;
            var pop3Request;

            var startTest = function () {
                updateTestStatus('wait');
                thisRefeshBtn.setDisabled(true);

                // Get timeout
                var timeoutVal = 60000;

                // Check smtp settings
                smtpRequest = Ext.Ajax.request({
                    url: topBaseUrl + '/mailer/settings/test-mail-settings',
                    timeout: timeoutVal,
                    params: {
                        email: thisDialog.email.getValue(),
                        test_action: 'smtp',

                        out_use_own: thisDialog.out_use_own.checked,
                        out_host: thisDialog.out_host.getValue() ? thisDialog.out_host.getValue().trim() : '',
                        out_port: thisDialog.out_port.getValue(),
                        out_login: thisDialog.out_login.getValue() ? thisDialog.out_login.getValue().trim() : '',
                        out_password: thisDialog.out_password.getValue(),
                        out_login_type: thisDialog.out_login_type.getValue(),
                        out_ssl: thisDialog.out_ssl.getValue()
                    },

                    success: function (result) {
                        smtpRequest = null;

                        // Show test result
                        var resultData = Ext.decode(result.responseText);
                        showTestResult('smtp', resultData.message);
                    },

                    failure: function () {
                        smtpRequest = null;
                        showTestResult('smtp', _('Time out.'));
                    }
                });

                // Check pop3 settings
                if (!thisDialog.enableIncoming.checked) {
                    showTestResult('pop3', _('Skipped.'), true);
                } else {
                    pop3Request = Ext.Ajax.request({
                        url: topBaseUrl + '/mailer/settings/test-mail-settings',
                        timeout: timeoutVal,
                        params: {
                            test_action: 'pop3',

                            email: thisDialog.email.getValue(),
                            inc_enabled: thisDialog.enableIncoming.checked,
                            inc_type: thisDialog.inc_type.getValue(),
                            inc_host: thisDialog.inc_host.getValue().trim(),
                            inc_port: thisDialog.inc_port.getValue(),
                            inc_login: thisDialog.inc_login.getValue().trim(),
                            inc_password: thisDialog.inc_password.getValue(),
                            inc_login_type: thisDialog.inc_login_type.getValue(),
                            inc_ssl: thisDialog.inc_ssl.getValue()
                        },

                        success: function (result) {
                            pop3Request = null;

                            // Show test result
                            var resultData = Ext.decode(result.responseText);
                            showTestResult('pop3', resultData.message);
                        },

                        failure: function () {
                            pop3Request = null;
                            showTestResult('pop3', _('Time out.'));
                        }
                    });
                }
            };

            var thisRefeshBtn = new Ext.Button({
                text: _('Refresh'),
                handler: function () {
                    // Start testing again
                    startTest();
                }
            });

            var thisCloseBtn = new Ext.Button({
                text: _('Cancel'),
                cls: 'orange-btn',
                handler: function () {
                    // Close the test window
                    testWindow.close();
                }
            });

            var testWindow = new Ext.Window({
                title: _('Testing mail server settings'),
                modal: true,
                autoHeight: true,
                width: 600,
                resizable: false,
                layout: 'form',
                items: [{
                    xtype: 'form',
                    bodyStyle: 'padding: 15px',
                    defaultType: 'label',
                    items: [
                        {
                            style: 'font-weight: bold; font-size: 14px; display: block;',
                            text: _('Outgoing mail server test result:')
                        }, {
                            id: 'test_result_smtp',
                            style: 'padding: 10px 0; display: block;',
                            html: _('Please start the test...')
                        }, {
                            style: 'font-weight: bold; font-size: 14px; display: block; padding-top: 20px;',
                            text: _('Incoming mail server test result:')
                        }, {
                            id: 'test_result_pop3',
                            style: 'padding: 10px 0; display: block;',
                            html: _('Please start the test...')
                        }
                    ]
                }],

                listeners:
                    {
                        close: function () {
                            // Abort all connections
                            if (!empty(smtpRequest) && !empty(smtpRequest.conn))
                                smtpRequest.conn.abort();

                            if (!empty(pop3Request) && !empty(pop3Request.conn))
                                pop3Request.conn.abort();

                            thisDialog.getEl().unmask();
                        },

                        show: function () {
                            startTest();
                        }
                    },


                buttons: [thisRefeshBtn, thisCloseBtn]
            });

            testWindow.show();
            testWindow.center();
        }
    }
});