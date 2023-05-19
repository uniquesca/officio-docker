"use strict";

var system = require('system');

var page = require('webpage').create();

var url = system.args[4];

page.viewportSize = {
    width: 1024,
    height: 768
};

page.paperSize = {
    format: 'A3',
    margin: '1cm'
};

// page.settings.userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36';
page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36';

var added = phantom.addCookie({
   'name': system.args[1],
   'value': system.args[2],
   'domain': system.args[3],
   'path': '/'
});

console.log('Cookie added: ' + added);
system.args.forEach(function (arg, i) {
    console.log(i + ': ' + arg);
});


function waitFor(testFx, onReady, timeOutMillis) {
    var maxtimeOutMillis = timeOutMillis ? timeOutMillis : 10000, //< Default Max Timout is 3s
        start = new Date().getTime(),
        condition = false,
        interval = setInterval(function() {
            if ( (new Date().getTime() - start < maxtimeOutMillis) && !condition ) {
                // If not time-out yet and condition not yet fulfilled
                condition = (typeof(testFx) === "string" ? eval(testFx) : testFx()); //< defensive code
            } else {
                if(!condition) {
                    // If condition still not fulfilled (timeout but condition is 'false')
                    console.log("'waitFor()' timeout");
                    phantom.exit(1);
                } else {
                    // Condition fulfilled (timeout and/or condition is 'true')
                    console.log("'waitFor()' finished in " + (new Date().getTime() - start) + "ms.");
                    typeof(onReady) === "string" ? eval(onReady) : onReady(); //< Do what it's supposed to do once the condition is fulfilled
                    clearInterval(interval); //< Stop this interval
                }
            }
        }, 250); //< repeat check every 250ms
}

page.onLoadStarted = function() {
  console.log('= onLoadStarted()');
  var currentUrl = page.evaluate(function() {
    return window.location.href;
  });
  console.log('  leaving url: ' + currentUrl);
};

page.onLoadFinished = function(status) {
  console.log('= onLoadFinished()');
  console.log('  status: ' + status);
};

page.onNavigationRequested = function(url, type, willNavigate, main) {
  console.log('= onNavigationRequested');
  console.log('  destination_url: ' + url);
  console.log('  type (cause): ' + type);
  console.log('  will navigate: ' + willNavigate);
  console.log('  from page\'s main frame: ' + main);
};

page.onResourceError = function(resourceError) {
    console.log('onResourceError ' + JSON.stringify(resourceError, undefined, 4));
};

page.onResourceTimeout = function(request) {
    console.log('onResourceTimeout ' + JSON.stringify(request, undefined, 4));
};

page.onResourceRequested = function(request) {
    console.log('Request ' + JSON.stringify(request, undefined, 4));
};

page.onResourceReceived = function(response) {
    console.log('Receive ' + JSON.stringify(response, undefined, 4));
};

page.onAlert = function(msg) {
    console.log('ALERT: ' + msg);
};

page.onError = function (msg, trace) {
    console.log(msg);
    trace.forEach(function(item) {
        console.log('  ', item.file, ':', item.line);
    });
};

page.open(url, function (status) {
    if (status !== "success") {
        console.log("Unable to access network");
        phantom.exit();
    } else {
        // Wait till data will be loaded and showed on the web page
        waitFor(function() {
            // Check in the page if a specific element is now visible
            return page.evaluate(function() {
                return $("#uniques-data-loaded-flag").length;
            });
        }, function() {
            page.render(system.args[5]);
            phantom.exit();
        });
    }
});


