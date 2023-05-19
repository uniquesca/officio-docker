    var gt = new Gettext();
    // create a shortcut for gettext
    function _ (msgid) { return gt.gettext(msgid); }

    function HtmlTagSearch(text_for_search) {
        return (text_for_search.match(/<\/?[^<>]*>/i));
    }

    function preg_quote(str) {
        // *     example 1: preg_quote("$40");
        // *     returns 1: '\$40'
        // *     example 2: preg_quote("*RRRING* Hello?");
        // *     returns 2: '\*RRRING\* Hello\?'
        // *     example 3: preg_quote("\\.+*?[^]$(){}=!<>|:");
        // *     returns 3: '\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:'
        var regexp = /([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g;
        return (str + '').replace(regexp, '\\$1');
    }

    // Fix: IE9 issue with combobox list height
    if (typeof Range !== "undefined" && typeof Range.prototype.createContextualFragment == "undefined") {
        Range.prototype.createContextualFragment = function(html) {
            var startNode = this.startContainer;
            var doc = startNode.nodeType == 9 ? startNode : startNode.ownerDocument;
            var container = doc.createElement("div");
            container.innerHTML = html;
            var frag = doc.createDocumentFragment(), n;
            while ( (n = container.firstChild) ) {
                frag.appendChild(n);
            }
            return frag;
        };
    }

    // JavaScript Document
    function getXMLHttp() {
        var xmlHttp = null;
        if(window.XMLHttpRequest) { // Mozilla, Safari, ...
            xmlHttp = new XMLHttpRequest();
            if(xmlHttp.overrideMimeType) {
                xmlHttp.overrideMimeType('text/xml');
            }
        }
        else if(window.ActiveXObject) { // IE
            try{
                xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");
            }
            catch(e) {
                try {
                    xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
                }
                catch (e) {
                    alert("Error: ActiveXObject is not loaded...");
                }
            }
        }
        if(!xmlHttp) {
            alert("Error occurred!! :( Cannot create an xHTTP instance...");
            return false;
        }
        return xmlHttp;
    }
    
    function setSession() {
        /*
        var xmlHttp = new getXMLHttp();
        xmlHttp.open('GET', 'setSession.php', true);
        xmlHttp.send(null);
        */
    }
    function hideleft(w) {
        if(w===true) {
            setSession();
        }
        document.getElementById('pleft').style.display='none';
        document.getElementById('pspacing').style.display='none';
        document.getElementById('sign').innerHTML = "<a href='#' onClick='showleft()'><img src='"+baseUrl+"/images/right.jpg' border='0'></a>";
    }
    function showleft() {
        setSession();
        document.getElementById('pleft').style.display='block';
        document.getElementById('pspacing').style.display='block';
        document.getElementById('sign').innerHTML = "<a href='#' onClick='hideleft()'><img src='"+baseUrl+"/images/left.jpg' border='0'></a>";
    }
    
    //Determine whether a variable is empty
    function empty(mixed_var) {
        return (typeof(mixed_var) === 'undefined' || mixed_var === '' ||
                mixed_var === 0 || mixed_var === '0' || mixed_var === null ||
                mixed_var === false);
    }
    
    // Used to play with float numbers
    function toFixed(value, precision) {
        var power = Math.pow(10, precision || 0);
        return Math.round(value * power) / power;
    }


    //JavaScript function, Equal PHP function in_array()
    Array.prototype.has = function (v, i) {
        for (var j = 0; j < this.length; j++) {
            if (this[j] == v) return (!i ? true : j);
        }
        return false;
    };

    function ucfirst(str)
    {
        var f=str.charAt(0).toUpperCase();
        return f+str.substr(1, str.length-1);
    }


    function trim(string) {
        return string.replace(/(^\s+)|(\s+$)/g, "");
    }

    // Generate panel size in relation to the user's screen resolution
    function getSuperadminPanelHeight(booUseMaxForLeftSection) {
        var iframeHeight = $(window.parent).height() -
            $('.content_container_header_left').outerHeight() -
            $('.content_container_body').outerHeight() +
            $('.content_container_body').height() -
            25 -
            $('.superadmin-iframe-header').outerHeight();

        var height = iframeHeight -
            $('.head-td', parent.document).outerHeight() -
            $('.main-tab-header', parent.document).outerHeight() -
            $('#main-tab-panel', parent.document).find('.x-tab-panel-header').outerHeight() -
            $('#main-tab-panel', parent.document).find('.x-tab-panel-bwrap').outerHeight() +
            $('#main-tab-panel', parent.document).find('.x-tab-panel-bwrap').find('#admin-sub-tab').outerHeight();

        if (isNaN(height)) {
            // If we opened not in the iframe - use simpler calculations
            height = iframeHeight;
        }

        if (booUseMaxForLeftSection) {
            // Use the max available height - check the left menu height too
            var leftNavigationPanel = $('#admin-left-panel');
            if (leftNavigationPanel.length) {
                height = Math.max(leftNavigationPanel.height() - 60, height);
            }
        }

        return height;
    }

    function updateSuperadminIFrameHeight(containerId, booProspectsTab) {
        var container = $(containerId);
        var new_height;
        if (container.height() <= $('#admin-left-panel').outerHeight()) {
            new_height = $('#admin-left-panel').outerHeight() + $('.superadmin-iframe-header').outerHeight();
        } else {
            if (booProspectsTab) {
                new_height = container.outerHeight() + 120 + $('.superadmin-iframe-header').outerHeight();
            } else {
                new_height = container.height() + 82 + $('.superadmin-iframe-header').outerHeight();
            }
        }
        $("#admin_section_frame", top.document).height(new_height + 'px');
    }

    /**
     *  Joins array elements placing glue string between items and return one string
     *
     *  example 1: implode(' ', ['Kevin', 'van', 'Zonneveld']);
     *  returns 1: 'Kevin van Zonneveld'
     *  example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'});
     *  returns 2: 'Kevin van Zonneveld'
    */
    function implode(glue, pieces) {
        var i = '', retVal = '', tGlue = '';
        if (arguments.length === 1) {
            pieces = glue;
            glue = '';
        }
        if (typeof(pieces) === 'object') {
            if (pieces instanceof Array) {
                return pieces.join(glue);
            }
            else {
                for (i in pieces) {
                    if (pieces.hasOwnProperty(i)) {
                        retVal += tGlue + pieces[i];
                        tGlue = glue;
                    }
                }
                return retVal;
            }
        }
        else {
            return pieces;
        }
    }

    function generatePassword() {
        function checkPunc(num) {
            var s = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'z', 'x', 'c', 'v', 'b', 'n', 'm', 'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'C', 'V', 'B', 'N', 'M', '2', '3', '4', '5', '6', '7', '8', '9', '_'];
            return s.has(String.fromCharCode(num));
        }

        function getRandomNum() {
            var rndNum = Math.random();
            rndNum = parseInt(rndNum * 1000, 10);
            return (rndNum % 94) + 33;
        }

        var sPassword = '';

        var length = Math.random();
        length = parseInt(length * 100, 10);
        length = (length % 5) + 6;

        for (var i = 0; i < length; i++) {
            var numI = getRandomNum();
            while (!checkPunc(numI)) {
                numI = getRandomNum();
            }

            sPassword = sPassword + String.fromCharCode(numI);
        }

        return sPassword;
    }

    function validateEmailField(field) {
        var booValid = false,
            value = field.getValue();

        if (value === '') {
            booValid = true;
        } else if (Ext.form.VTypes.email(value)) {
            booValid = true;
        } else {
            var match = value.match(/^(.*)"(.*)"(.*)$/);
            if (match != null) {
                if (Ext.form.VTypes.email(match[2])) {
                    match[1] = empty(match[1]) ? '' : trim(match[1]);
                    match[3] = empty(match[3]) ? '' : trim(match[3]);

                    if (!empty(match[1]) || !empty(match[3])) {
                        booValid = true;
                    }
                }
            }
        }

        if (!booValid) {
            field.markInvalid(
                'Valid values are:<br\>' +
                '1. Empty value (email address will be used from user\'s default email account)<br\>' +
                '2. <b>email@address.com</b> <br\>' +
                '3. <b>Some Name "email@address.com"</b> OR <br\>' +
                   '<b>"email@address.com" Some Name</b> OR <br\>' +
                   '<b>Some "email@address.com" Name</b>'
            );
        } else {
            field.getEl().removeClass('x-form-invalid');
        }
    }

function submit_hidden_form(url, params) {
    $.fileDownload(url, {
        httpMethod: 'POST',
        modal:      false,
        data:       params,

        failCallback: function () {
            var msg = _('There was a problem, please try again.');
            if (typeof(Ext) !== 'undefined') {
                Ext.simpleConfirmation.error(msg);
            } else {
                alert(msg);
            }
        }
    });
}

    $(document).ready(function () {
        $('.changeMyPasswordLink').click(function (e) {
            e.preventDefault();

            if (parent.userProfileSettings) {
                var wnd = new parent.UserProfileDialog({});
                wnd.show();
                wnd.center();
                wnd.syncShadow();
            } else {
                var msg = _('To use this functionality please open this page from the parent Officio.');
                if (typeof(Ext) !== 'undefined') {
                    Ext.simpleConfirmation.warning(msg);
                } else {
                    alert(msg);
                }
            }
        });
    });