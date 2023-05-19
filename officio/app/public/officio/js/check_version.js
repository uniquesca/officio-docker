/**
* Determines version of the Acrobat Reader plugin for FireFox and Netscape
* @returns the major version of the plugin and returns null if PDF is not supported
**/
function OtherPDFVersion() {
  var version = null;
  var plugin = navigator.plugins["Adobe Acrobat"];
  if (plugin == null) return null;
  if (plugin.description == "Adobe PDF Plug-In For Firefox and Netscape") {
    version = '8.0 or high';
  } else {
    version = plugin.description.split('Version ')[1] + '.0';
  }
  return version;
}

/**
* Determines version of the Acrobat Reader plugin for InternetExplorer
* @returns the major version of the plugin and returns null if PDF is not supported
**/
function checkIEPDFVersion() {
    var isInstalled = false;
    var version = null;
    if (window.ActiveXObject) {
        var control = null;
        try {
            // AcroPDF.PDF is used by version 7 and later
            control = new ActiveXObject('AcroPDF.PDF');
        } catch (e) {
            // Do nothing
        }
        if (!control) {
            try {
                // PDF.PdfCtrl is used by version 6 and earlier
                control = new ActiveXObject('PDF.PdfCtrl');
            } catch (e) {
                return null;
            }
        }
        if (control) {
            isInstalled = true;
            version = control.GetVersions().split(',');
            version = version[0].split('=');
            version = parseFloat(version[1]);
        }
    } else {
        // Check navigator.plugins for "Adobe Acrobat" or "Adobe PDF Plug-in"*
    }
    
    return version;
}

var BrowserDetect = {
    init: function () {
        this.browser = this.searchString(this.dataBrowser) || "An unknown browser";
        this.version = this.searchVersion(navigator.userAgent)
            || this.searchVersion(navigator.appVersion)
            || "an unknown version";
        this.OS = this.searchString(this.dataOS) || "an unknown OS";
    },
    searchString: function (data) {
        for (var i=0;i<data.length;i++)    {
            var dataString = data[i].string;
            var dataProp = data[i].prop;
            this.versionSearchString = data[i].versionSearch || data[i].identity;
            if (dataString) {
                if (dataString.indexOf(data[i].subString) != -1)
                    return data[i].identity;
            }
            else if (dataProp)
                return data[i].identity;
        }
    },
    searchVersion: function (dataString) {
        var index = dataString.indexOf(this.versionSearchString);
        if (index == -1) return;
        return parseFloat(dataString.substring(index+this.versionSearchString.length+1));
    },
    dataBrowser: [
        {
            string: navigator.userAgent,
            subString: "Chrome",
            identity: "Chrome"
        },
        {     string: navigator.userAgent,
            subString: "OmniWeb",
            versionSearch: "OmniWeb/",
            identity: "OmniWeb"
        },
        {
            string: navigator.vendor,
            subString: "Apple",
            identity: "Safari",
            versionSearch: "Version"
        },
        {
            prop: window.opera,
            identity: "Opera"
        },
        {
            string: navigator.vendor,
            subString: "iCab",
            identity: "iCab"
        },
        {
            string: navigator.vendor,
            subString: "KDE",
            identity: "Konqueror"
        },
        {
            string: navigator.userAgent,
            subString: "Firefox",
            identity: "Firefox"
        },
        {
            string: navigator.vendor,
            subString: "Camino",
            identity: "Camino"
        },
        {        // for newer Netscapes (6+)
            string: navigator.userAgent,
            subString: "Netscape",
            identity: "Netscape"
        },
        {
            string: navigator.userAgent,
            subString: "MSIE",
            identity: "Explorer",
            versionSearch: "MSIE"
        },
        {
            string: navigator.userAgent,
            subString: "Gecko",
            identity: "Mozilla",
            versionSearch: "rv"
        },
        {         // for older Netscapes (4-)
            string: navigator.userAgent,
            subString: "Mozilla",
            identity: "Netscape",
            versionSearch: "Mozilla"
        }
    ],
    dataOS : [
        {
            string: navigator.platform,
            subString: "Win",
            identity: "Windows"
        },
        {
            string: navigator.platform,
            subString: "Mac",
            identity: "Mac"
        },
        {
               string: navigator.userAgent,
               subString: "iPhone",
               identity: "iPhone/iPod"
        },
        {
            string: navigator.platform,
            subString: "Linux",
            identity: "Linux"
        }
    ]

};
BrowserDetect.init();



Ext.onReady(function() {
    // Determine installed PDF version
    var pdfVersion = (Ext.isIE) ? checkIEPDFVersion() : OtherPDFVersion();
    pdfVersion = pdfVersion || 'Unknown';
    
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
        width: 380,
        renderTo: 'version_comments'
    });
    
    var btn = new Ext.Button({
        id: 'button_submit',
        text: "Send to support team",
        width: 140,
        handler: function choose(btn){
            var body = Ext.getBody();
            body.mask('Sending...');
           
            Ext.Ajax.request({
                url: "/system/index.php",
                params: {
                    version_os:              Ext.encode(osVersion),
                    version_browser:         Ext.encode(browserVersion),
                    version_pdf:             Ext.encode(pdfVersion),
                    version_additional_info: Ext.encode(Ext.get('version_other').dom.innerHTML),
                    version_comments:        Ext.encode(comments.getValue())
                },

                success: function(result, request){
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        // Show confirmation message
                        Ext.MessageBox.hide();
                        
                        body.mask('Done!');
                        setTimeout(function(){
                            body.unmask();
                        }, 750);
                    } else {
                        Ext.MessageBox.hide();
                        Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' +resultData.message + '</span>');
                    }
                },

                failure: function(form, action){
                    Ext.MessageBox.hide();
                    Ext.Msg.alert('Status', 'Cannot send information. Please try again later.');
                }
            });
           

        },
        renderTo: 'submit_btn_container'
    });
    
});
