var d=!1;if("undefined"===typeof f){var g=function(a){var b=window.getSelection(),c=document.createRange();c.selectNodeContents(a);b.removeAllRanges();b.addRange(c)},h=function(a){a.className=a.className.replace(/(\s|^)kint-minus(\s|$)/," ");return a},i=function(a){var b;b||(b="dd");do a=a.nextElementSibling;while(a.nodeName.toLowerCase()!=b);return a},j=function(a,b){var c=i(a),e=a.getElementsByClassName("_kint-collapse")[0];"undefined"==typeof b&&(b="block"==c.style.display);b?(c.style.display="none",h(e)):
(c.style.display="block",h(e).className+=" kint-minus")},l=function(a,b){var c=a.parentNode.parentNode.getElementsByClassName(b)[0];c.style.display="block"==c.style.display?"none":"block"},f={};window.addEventListener("load",function(){for(var a=document.getElementsByClassName("kint-parent"),b=a.length,c,e=document.getElementsByClassName("kint");b--;)a[b].addEventListener("mousedown",function(){j(this)},d);a=document.getElementsByClassName("_kint-collapse");for(b=a.length;b--;)a[b].addEventListener("mousedown",
function(a){var b=this;setTimeout(function(){if(0<parseInt(b.a,10))b.a--;else{for(var a=b.parentNode,c=i(a),k=c.getElementsByClassName("kint-parent"),e=k.length,c="block"==c.style.display;e--;)j(k[e],c);j(a,c)}},300);a.stopPropagation()},d),a[b].addEventListener("dblclick",function(a){this.a=2;for(var b=document.getElementsByClassName("kint-parent"),c=b.length,e="block"==i(this.parentNode).style.display;c--;)j(b[c],e);a.stopPropagation()},d);for(b=e.length;b--;){a=e[b].getElementsByTagName("var");
for(c=a.length;c--;)a[c].addEventListener("mouseup",function(){g(this)},d);a=e[b].getElementsByTagName("dfn");for(c=a.length;c--;)a[c].addEventListener("mouseup",function(){g(this)},d)}a=document.getElementsByClassName("kint-args-parent");for(b=a.length;b--;)a[b].addEventListener("click",function(a){l(this,"kint-args");a.preventDefault()},d);a=document.getElementsByClassName("kint-source-parent");for(b=a.length;b--;)a[b].addEventListener("click",function(a){l(this,"kint-source");a.preventDefault()},
d);a=document.getElementsByClassName("kint-object-parent");for(b=a.length;b--;)a[b].addEventListener("click",function(a){l(this,"kint-object");a.preventDefault()},d);a=document.getElementsByClassName("kint-ide-link");for(c=a.length;c--;)a[c].addEventListener("click",function(a){a.preventDefault();a=new XMLHttpRequest;a.open("GET",this.href);a.send(null);return d},d)},d)}