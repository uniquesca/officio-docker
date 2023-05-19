/* PluginDetect v0.7.5 by Eric Gerds www.pinlady.net/PluginDetect [ onWindowLoaded isMinVersion getVersion onDetectionDone AdobeReader PDFreader(OTF & NOTF) ] */var PluginDetect={version:"0.7.5",name:"PluginDetect",handler:function(c,b,a){return function(){c(b,a)
}
},isDefined:function(b){return typeof b!="undefined"
},isArray:function(b){return(/array/i).test(Object.prototype.toString.call(b))
},isFunc:function(b){return typeof b=="function"
},isString:function(b){return typeof b=="string"
},isNum:function(b){return typeof b=="number"
},isStrNum:function(b){return(typeof b=="string"&&(/\d/).test(b))
},getNumRegx:/[\d][\d\.\_,-]*/,splitNumRegx:/[\.\_,-]/g,getNum:function(b,c){var d=this,a=d.isStrNum(b)?(d.isDefined(c)?new RegExp(c):d.getNumRegx).exec(b):null;
return a?a[0]:null
},compareNums:function(h,f,d){var e=this,c,b,a,g=parseInt;
if(e.isStrNum(h)&&e.isStrNum(f)){if(e.isDefined(d)&&d.compareNums){return d.compareNums(h,f)
}c=h.split(e.splitNumRegx);
b=f.split(e.splitNumRegx);
for(a=0;
a<Math.min(c.length,b.length);
a++){if(g(c[a],10)>g(b[a],10)){return 1
}if(g(c[a],10)<g(b[a],10)){return -1
}}}return 0
},formatNum:function(b,c){var d=this,a,e;
if(!d.isStrNum(b)){return null
}if(!d.isNum(c)){c=4
}c--;
e=b.replace(/\s/g,"").split(d.splitNumRegx).concat(["0","0","0","0"]);
for(a=0;
a<4;
a++){if(/^(0+)(.+)$/.test(e[a])){e[a]=RegExp.$2
}if(a>c||!(/\d/).test(e[a])){e[a]="0"
}}return e.slice(0,4).join(",")
},$$hasMimeType:function(a){return function(d){if(!a.isIE&&d){var c,b,e,f=a.isString(d)?[d]:d;
if(!f||!f.length){return null
}for(e=0;
e<f.length;
e++){if(/[^\s]/.test(f[e])&&(c=navigator.mimeTypes[f[e]])&&(b=c.enabledPlugin)&&(b.name||b.description)){return c
}}}return null
}
},findNavPlugin:function(l,e,c){var j=this,h=new RegExp(l,"i"),d=(!j.isDefined(e)||e)?/\d/:0,k=c?new RegExp(c,"i"):0,a=navigator.plugins,g="",f,b,m;
for(f=0;
f<a.length;
f++){m=a[f].description||g;
b=a[f].name||g;
if((h.test(m)&&(!d||d.test(RegExp.leftContext+RegExp.rightContext)))||(h.test(b)&&(!d||d.test(RegExp.leftContext+RegExp.rightContext)))){if(!k||!(k.test(m)||k.test(b))){return a[f]
}}}return null
},getMimeEnabledPlugin:function(a,f){var e=this,b,c=new RegExp(f,"i"),d="";
if((b=e.hasMimeType(a))&&(b=b.enabledPlugin)&&(c.test(b.description||d)||c.test(b.name||d))){return b
}return 0
},getPluginFileVersion:function(f,b){var h=this,e,d,g,a,c=-1;
if(h.OS>2||!f||!f.version||!(e=h.getNum(f.version))){return b
}if(!b){return e
}e=h.formatNum(e);
b=h.formatNum(b);
d=b.split(h.splitNumRegx);
g=e.split(h.splitNumRegx);
for(a=0;
a<d.length;
a++){if(c>-1&&a>c&&d[a]!="0"){return b
}if(g[a]!=d[a]){if(c==-1){c=a
}if(d[a]!="0"){return b
}}}return e
},AXO:window.ActiveXObject,getAXO:function(b){var f=null,d,c=this,a;
;
try{f=new c.AXO(b);
}catch(d){}return f
},convertFuncs:function(g){var a,h,f,b=/^[\$][\$]/,d={},c=this;
for(a in g){if(b.test(a)){d[a]=1
}}for(a in d){try{h=a.slice(2);
if(h.length>0&&!g[h]){g[h]=g[a](g);
delete g[a]
}}catch(f){}}},initScript:function(){var c=this,a=navigator,e="/",i=a.userAgent||"",g=a.vendor||"",b=a.platform||"",h=a.product||"";
;
;
;
c.OS=100;
if(b){var f,d=["Win",1,"Mac",2,"Linux",3,"FreeBSD",4,"iPhone",21.1,"iPod",21.2,"iPad",21.3,"Win.*CE",22.1,"Win.*Mobile",22.2,"Pocket\\s*PC",22.3,"",100];
for(f=d.length-2;
f>=0;
f=f-2){if(d[f]&&new RegExp(d[f],"i").test(b)){c.OS=d[f+1];
break
}}}c.convertFuncs(c);
;
c.isIE=new Function("return "+e+"*@cc_on!@*"+e+"false")();
c.verIE=c.isIE&&(/MSIE\s*(\d+\.?\d*)/i).test(i)?parseFloat(RegExp.$1,10):null;
c.ActiveXEnabled=false;
;
;
if(c.isIE){var f,j=["Msxml2.XMLHTTP","Msxml2.DOMDocument","Microsoft.XMLDOM","ShockwaveFlash.ShockwaveFlash","TDCCtl.TDCCtl","Shell.UIHelper","Scripting.Dictionary","wmplayer.ocx"];
for(f=0;
f<j.length;
f++){if(c.getAXO(j[f])){c.ActiveXEnabled=true;
break
}}c.head=c.isDefined(document.getElementsByTagName)?document.getElementsByTagName("head")[0]:null
}c.isGecko=(/Gecko/i).test(h)&&(/Gecko\s*\/\s*\d/i).test(i);
c.verGecko=c.isGecko?c.formatNum((/rv\s*\:\s*([\.\,\d]+)/i).test(i)?RegExp.$1:"0.9"):null;
;
;
c.isSafari=(/Safari\s*\/\s*\d/i).test(i)&&(/Apple/i).test(g);
;
c.isChrome=(/Chrome\s*\/\s*(\d[\d\.]*)/i).test(i);
c.verChrome=c.isChrome?c.formatNum(RegExp.$1):null;
;
;
c.isOpera=(/Opera\s*[\/]?\s*(\d+\.?\d*)/i).test(i);
c.verOpera=c.isOpera&&((/Version\s*\/\s*(\d+\.?\d*)/i).test(i)||1)?parseFloat(RegExp.$1,10):null;
;
;
;
;
;
c.addWinEvent("load",c.handler(c.runWLfuncs,c));

},init:function(c){var b=this,a,c;
if(!b.isString(c)){
return -3
}if(c.length==1){b.getVersionDelimiter=c;
return -3
}c=c.toLowerCase().replace(/\s/g,"");
a=b[c];
if(!a||!a.getVersion){
return -3
}b.plugin=a;
if(!b.isDefined(a.installed)){a.installed=a.version=a.version0=a.getVersionDone=null;
a.$=b;
a.pluginName=c
}b.garbage=false;
if(b.isIE&&!b.ActiveXEnabled){if(a!==b.java){return -2
}}return 1
},fPush:function(b,a){var c=this;
if(c.isArray(a)&&(c.isFunc(b)||(c.isArray(b)&&b.length>0&&c.isFunc(b[0])))){a.push(b)
}},callArray:function(b){var c=this,a;
if(c.isArray(b)){for(a=0;
a<b.length;
a++){if(b[a]===null){return
}c.call(b[a]);
b[a]=null
}}},call:function(c){var b=this,a=b.isArray(c)?c.length:-1;
if(a>0&&b.isFunc(c[0])){c[0](b,a>1?c[1]:0,a>2?c[2]:0,a>3?c[3]:0)
}else{if(b.isFunc(c)){c(b)
}}},$$isMinVersion:function(a){return function(h,g,d,c){var e=a.init(h),f,b=-1,j;
;
if(e<0){return e
}f=a.plugin;
g=a.formatNum(a.isNum(g)?g.toString():(a.isStrNum(g)?a.getNum(g):"0"));
;
if(f.getVersionDone!=1){f.getVersion(g,d,c);
if(f.getVersionDone===null){f.getVersionDone=1
}}a.cleanup();
if(f.installed!==null){b=f.installed<=0.5?f.installed:(f.installed==0.7?1:(f.version===null?0:(a.compareNums(f.version,g,f)>=0?1:-1)))
};
return b
}
},getVersionDelimiter:",",$$getVersion:function(a){return function(g,d,c){var e=a.init(g),f,b,h;
;
if(e<0){return null
};
f=a.plugin;
if(f.getVersionDone!=1){f.getVersion(null,d,c);
if(f.getVersionDone===null){f.getVersionDone=1
}}a.cleanup();
b=(f.version||f.version0);
b=b?b.replace(a.splitNumRegx,a.getVersionDelimiter):b;
;
return b
}
},cleanup:function(){
},addWinEvent:function(d,c){var e=this,a=window,b;
if(e.isFunc(c)){if(a.addEventListener){a.addEventListener(d,c,false)
}else{if(a.attachEvent){a.attachEvent("on"+d,c)
}else{b=a["on"+d];
a["on"+d]=e.winHandler(c,b)
}}}},winHandler:function(d,c){return function(){d();
if(typeof c=="function"){c()
}}
},WLfuncs0:[],WLfuncs:[],runWLfuncs:function(a){a.winLoaded=true;
;
;
;
a.callArray(a.WLfuncs0);
a.callArray(a.WLfuncs);
;
if(a.onDoneEmptyDiv){a.onDoneEmptyDiv()
}},winLoaded:false,$$onWindowLoaded:function(a){return function(b){
if(a.winLoaded){
a.call(b);
}else{a.fPush(b,a.WLfuncs)
}}
},$$onDetectionDone:function(a){return function(h,g,c,b){var d=a.init(h),j,e;
if(d==-3){return -1
}e=a.plugin;
;
if(!a.isArray(e.funcs)){e.funcs=[]
}if(e.getVersionDone!=1){j=a.isMinVersion?a.isMinVersion(h,"0",c,b):a.getVersion(h,c,b)
}if(e.installed!=-0.5&&e.installed!=0.5){
;
a.call(g);
;
return 1
}if(e.NOTF){a.fPush(g,e.funcs);
return 0
}return 1
}
},div:null,divWidth:50,pluginSize:1,emptyDiv:function(){var c=this,a,e,b,d=0;
if(c.div&&c.div.childNodes){
for(a=c.div.childNodes.length-1;
a>=0;
a--){b=c.div.childNodes[a];
if(b&&b.childNodes){if(d==0){for(e=b.childNodes.length-1;
e>=0;
e--){b.removeChild(b.childNodes[e])
}c.div.removeChild(b)
}else{}}}};
},DONEfuncs:[],onDoneEmptyDiv:function(){var c=this,a,b;
if(!c.winLoaded){return
}if(c.WLfuncs&&c.WLfuncs.length&&c.WLfuncs[c.WLfuncs.length-1]!==null){return
}for(a in c){b=c[a];
if(b&&b.funcs){if(b.OTF==3){return
}if(b.funcs.length&&b.funcs[b.funcs.length-1]!==null){return
}}}for(a=0;
a<c.DONEfuncs.length;
a++){c.callArray(c.DONEfuncs)
}c.emptyDiv()
},getWidth:function(c){if(c){var a=c.scrollWidth||c.offsetWidth,b=this;
if(b.isNum(a)){return a
}}return -1
},getTagStatus:function(m,g,a,b){var c=this,f,k=m.span,l=c.getWidth(k),h=a.span,j=c.getWidth(h),d=g.span,i=c.getWidth(d);
if(!k||!h||!d||!c.getDOMobj(m)){return -2
}if(j<i||l<0||j<0||i<0||i<=c.pluginSize||c.pluginSize<1){return 0
}if(l>=i){return -1
}try{if(l==c.pluginSize&&(!c.isIE||c.getDOMobj(m).readyState==4)){if(!m.winLoaded&&c.winLoaded){return 1
}if(m.winLoaded&&c.isNum(b)){if(!c.isNum(m.count)){m.count=b
}if(b-m.count>=10){return 1
}}}}catch(f){}return 0
},getDOMobj:function(g,a){var f,d=this,c=g?g.span:0,b=c&&c.firstChild?1:0;
try{if(b&&a){c.firstChild.focus()
}}catch(f){}return b?c.firstChild:null
},setStyle:function(b,g){var f=b.style,a,d,c=this;
if(f&&g){for(a=0;
a<g.length;
a=a+2){try{f[g[a]]=g[a+1]
}catch(d){}}}},insertDivInBody:function(i){var g,d=this,h="pd33993399",c=null,f=document,b="<",a=(f.getElementsByTagName("body")[0]||f.body);
if(!a){try{f.write(b+'div id="'+h+'">o'+b+"/div>");
c=f.getElementById(h)
}catch(g){}}a=(f.getElementsByTagName("body")[0]||f.body);
if(a){if(a.firstChild&&d.isDefined(a.insertBefore)){a.insertBefore(i,a.firstChild)
}else{a.appendChild(i)
}if(c){a.removeChild(c)
}}else{}},insertHTML:function(g,b,h,a,k){var l,m=document,j=this,q,o=m.createElement("span"),n,i,f="<";
var c=["outlineStyle","none","borderStyle","none","padding","0px","margin","0px","visibility","visible"];
if(!j.isDefined(a)){a=""
}if(j.isString(g)&&(/[^\s]/).test(g)){q=f+g+' width="'+j.pluginSize+'" height="'+j.pluginSize+'" ';
for(n=0;
n<b.length;
n=n+2){if(/[^\s]/.test(b[n+1])){q+=b[n]+'="'+b[n+1]+'" '
}}q+=">";
for(n=0;
n<h.length;
n=n+2){if(/[^\s]/.test(h[n+1])){q+=f+'param name="'+h[n]+'" value="'+h[n+1]+'" />'
}}q+=a+f+"/"+g+">"
}else{q=a
}if(!j.div){j.div=m.createElement("div");
i=m.getElementById("plugindetect");
if(i){j.div=i
}else{j.div.id="plugindetect";
j.insertDivInBody(j.div)
}j.setStyle(j.div,c.concat(["width",j.divWidth+"px","height",(j.pluginSize+3)+"px","fontSize",(j.pluginSize+3)+"px","lineHeight",(j.pluginSize+3)+"px","verticalAlign","baseline","display","block"]));
if(!i){j.setStyle(j.div,["position","absolute","right","0px","top","0px"])
}}if(j.div&&j.div.parentNode){
;
j.div.appendChild(o);
j.setStyle(o,c.concat(["fontSize",(j.pluginSize+3)+"px","lineHeight",(j.pluginSize+3)+"px","verticalAlign","baseline","display","inline"]));
try{if(o&&o.parentNode){o.focus()
}}catch(l){}try{o.innerHTML=q
}catch(l){}if(o.childNodes.length==1&&!(j.isGecko&&j.compareNums(j.verGecko,"1,5,0,0")<0)){j.setStyle(o.firstChild,c.concat(["display","inline"]))
}return{span:o,winLoaded:j.winLoaded,tagName:(j.isString(g)?g:"")}
}return{span:null,winLoaded:j.winLoaded,tagName:""}
},adobereader:{mimeType:"application/pdf",navPluginObj:null,progID:["AcroPDF.PDF","PDF.PdfCtrl"],classID:"clsid:CA8A9780-280D-11CF-A24D-444553540000",INSTALLED:{},pluginHasMimeType:function(d,c,f){var b=this,e=b.$,a;
for(a in d){if(d[a]&&d[a].type&&d[a].type==c){return 1
}}if(e.getMimeEnabledPlugin(c,f)){return 1
}return 0
},getVersion:function(i,j){var f=this,c=f.$,h,d,k,m=p=null,g=null,l=null,a,b;
j=(c.isString(j)&&j.length)?j.replace(/\s/,"").toLowerCase():f.mimeType;
if(c.isDefined(f.INSTALLED[j])){f.installed=f.INSTALLED[j];
return
}if(!c.isIE){a="Adobe.*PDF.*Plug-?in|Adobe.*Acrobat.*Plug-?in|Adobe.*Reader.*Plug-?in";
if(f.getVersionDone!==0){f.getVersionDone=0;
p=c.getMimeEnabledPlugin(f.mimeType,a);
if(!p&&c.hasMimeType(f.mimeType)){p=c.findNavPlugin(a,0)
}if(p){f.navPluginObj=p;
g=c.getNum(p.description)||c.getNum(p.name);
g=c.getPluginFileVersion(p,g);
if(!g&&c.OS==1){if(f.pluginHasMimeType(p,"application/vnd.adobe.pdfxml",a)){g="9"
}else{if(f.pluginHasMimeType(p,"application/vnd.adobe.x-mars",a)){g="8"
}}}}}else{g=f.version
}m=c.getMimeEnabledPlugin(j,a);
f.installed=m&&g?1:(m?0:(f.navPluginObj?-0.2:-1))
}else{p=c.getAXO(f.progID[0])||c.getAXO(f.progID[1]);
b=/=\s*([\d\.]+)/g;
try{d=(p||c.getDOMobj(c.insertHTML("object",["classid",f.classID],["src",""],"",f))).GetVersions();
for(k=0;
k<5;
k++){if(b.test(d)&&(!g||RegExp.$1>g)){g=RegExp.$1
}}}catch(h){}f.installed=g?1:(p?0:-1)
}if(!f.version){f.version=c.formatNum(g)
}f.INSTALLED[j]=f.installed
}},pdfreader:{mimeType:"application/pdf",progID:["AcroPDF.PDF","PDF.PdfCtrl"],classID:"clsid:CA8A9780-280D-11CF-A24D-444553540000",OTF:null,fileUsed:0,fileEnabled:1,isValid:function(b){var a=this,c=a.$;
if(!a.fileEnabled||!c.isString(b)||/\\/.test(b)||!/\.pdf\s*$/.test(b)){return 0
}return 1
},EndGetVersion:function(b){var a=this,c=a.$;
if(a.OTF==3){a.installed=-0.5
}else{a.installed=b?0:(c.isIE?-1.5:-1)
}a.getVersionDone=a.OTF<2&&a.fileEnabled&&a.installed<=-1&&a.getVersionDone!=1?0:1
},getVersion:function(l,g,c){var h=this,d=h.$,b=false,f,i,a,k,j=h.NOTF,m=h.doc;
;
if(((d.isGecko&&d.compareNums(d.verGecko,"2,0,0,0")<=0&&d.OS<=4)||(d.isOpera&&d.verOpera<=11&&d.OS<=4)||(d.isChrome&&d.compareNums(d.verChrome,"11,0,0,0")<0&&d.OS<=4)||0)&&!c){h.fileEnabled=0
}if(h.getVersionDone===null){h.OTF=0;
m.$=d;
m.parentNode=h;
if(j){j.$=d;
j.parentNode=h
}if(!d.isIE){if(!b&&!c&&d.hasMimeType(h.mimeType)){b=true
}}else{if(!b&&!c){try{if((d.getAXO(h.progID[0])||d.getAXO(h.progID[1])).GetVersions()){b=true
}}catch(k){}}}}if(!b){f=m.insertHTMLQuery(g,c);
if(f>0){b=true
}}i=document.getElementsByTagName("body")[0]||document.body;
if(c&&h.isValid(g)&&i&&i.firstChild){f=document.createElement("div");
d.setStyle(f,["outlineStyle","none","borderStyle","none","padding","0px","paddingBottom","50px","margin","0px","visibility","visible"]);
i.insertBefore(f,i.firstChild);
f.innerHTML="The red box below should display the PluginDetect DummyPDF.<br/>If it does, then the path/filename for DummyPDF are correct:<br/><iframe style='border:solid red 2px; padding:2px;' src='"+g+"' width='98%' height='250'></iframe>"
}h.EndGetVersion(b);
h.version=null
},doc:{HTML:0,DummyObjTagHTML:0,DummySpanTagHTML:0,queryObject:function(c){var g=this,b=g.parentNode,d=b.$,a;
if(d.isIE){a=-1;
try{if(d.getDOMobj(g.HTML).GetVersions()){a=1
}}catch(f){}}else{a=d.getTagStatus(g.HTML,g.DummySpanTagHTML,g.DummyObjTagHTML,c)
};
return a
},insertHTMLQuery:function(d,h){var f=this,b=f.parentNode,e=b.$,a,c="&nbsp;&nbsp;&nbsp;&nbsp;";
if(e.isIE){if(h&&!b.isValid(d)){return 0
}if(!f.HTML){f.HTML=e.insertHTML("object",["classid",b.classID],["src",h?d:""],c,b)
}if(h){b.fileUsed=1
}}else{if(!b.isValid(d)){return 0
}if(!f.HTML){f.HTML=e.insertHTML("object",["type",b.mimeType,"data",d],["src",d],c,b)
}b.fileUsed=1
}if(b.OTF<2){b.OTF=2
}if(!f.DummyObjTagHTML){f.DummyObjTagHTML=e.insertHTML("object",[],[],c)
}if(!f.DummySpanTagHTML){f.DummySpanTagHTML=e.insertHTML("",[],[],c)
}a=f.queryObject();
if(a!=0){return a
};
var g=b.NOTF;
if(b.OTF<3&&f.HTML&&g){b.OTF=3;
g.onIntervalQuery=e.handler(g.$$onIntervalQuery,g);
if(!e.winLoaded){e.WLfuncs0.push([g.winOnLoadQuery,g])
}setTimeout(g.onIntervalQuery,g.intervalLength);
;
};
return a
}},NOTF:{count:0,countMax:25,intervalLength:250,$$onIntervalQuery:function(e){var c=e.$,b=e.parentNode,d=b.doc,a;
if(b.OTF==3){a=d.queryObject(e.count);
if(a>0||a<0||(c.winLoaded&&e.count>e.countMax)){e.queryCompleted(a)
}}e.count++;
if(b.OTF==3){setTimeout(e.onIntervalQuery,e.intervalLength)
}},winOnLoadQuery:function(c,e){var b=e.parentNode,d=b.doc,a;
if(b.OTF==3){a=d.queryObject(e.count);
e.queryCompleted(a)
}},queryCompleted:function(b){var d=this,c=d.$,a=d.parentNode;
if(a.OTF==4){return
}a.OTF=4;
a.EndGetVersion(b>0?true:false);
;
if(a.funcs){
;
c.callArray(a.funcs);
}if(c.onDoneEmptyDiv){c.onDoneEmptyDiv()
}}},zz:0},zz:0};
PluginDetect.initScript();

