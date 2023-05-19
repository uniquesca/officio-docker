Ext.onReady(function() {
    // Determine installed PDF version
    var pluginStatus = PluginDetect.isMinVersion('AdobeReader', '0');

    var pdfVersion;
    if (pluginStatus >= 0) {
        pdfVersion = 'Installed &amp enabled. Version: ' + PluginDetect.getVersion('AdobeReader');
    } else if (pluginStatus == -0.2) {
        pdfVersion = 'Installed but not enabled. Version: ' + PluginDetect.getVersion('AdobeReader');
    } else if (pluginStatus == -2) {
        pdfVersion = '<span style="color: #ff0000">Please enable ActiveX in Internet Explorer so that we can detect your plugins.</span>';
    } else {
        pdfVersion = '<span style="color: #ff0000">Not installed, not enabled</span>';
    }

    var divPdfVersion = Ext.select('#version_acrobat');
    divPdfVersion.update(pdfVersion);


    // Determine Browser
    var divBrowserVersion = Ext.select('#version_browser');
    var browserVersion = BrowserDetect.browser + ' v' + BrowserDetect.version;
    divBrowserVersion.update(browserVersion);

    // Determine OS
    var divOSVersion = Ext.select('#version_os');
    var osVersion = BrowserDetect.OS;
    divOSVersion.update(osVersion);

    var comments = new Ext.form.TextArea({
        width: 400,
        renderTo: 'version_comments'
    });

    new Ext.Button({
        id: 'button_submit',
        text: 'Submit',
        width: 100,
        renderTo: 'submit_btn_container',
        handler: function choose() {
            Ext.MessageBox.show({
               msg: 'Sending...',
               progressText: 'Sending...',
               width: 300,
               wait: true,
               waitConfig: {interval: 200},
               icon: 'ext-mb-download',
               animEl: 'button_submit'
            });

            Ext.Ajax.request({
                url: baseUrl + '/system/index/check-version',
                params: {
                    version_os: Ext.encode(osVersion),
                    version_browser: Ext.encode(browserVersion),
                    version_pdf: Ext.encode(pdfVersion),
                    version_additional_info: Ext.encode(Ext.get('version_other').dom.innerHTML),
                    version_comments: Ext.encode(comments.getValue())
                },

                success: function(result){
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        // Show confirmation message
                        Ext.MessageBox.hide();

                        var body = Ext.getBody();
                        body.mask('Done!');
                        setTimeout(function() {
                            body.unmask();
                        }, 750);
                    } else {
                        Ext.MessageBox.hide();
                        Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' + resultData.message + '</span>');
                    }
                },

                failure: function(){
                    Ext.MessageBox.hide();
                    Ext.Msg.alert('Status', 'Cannot send information. Please try again later.');
                }
            });


        }
    });
});
