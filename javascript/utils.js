// JavaScript Document
var u = {};

//****************************************

u.eid = function(idStr)
{
return document.getElementById(idStr);
}

//****************************************

u.class = function(idStr)
{
return document.getElementsByClassName(idStr);
}

//****************************************

u.ename = function(nmStr)
{
return document.getElementsByName(nmStr);
}

//****************************************

u.etag = function(elm, tag)
{
if (((typeof elm) === "object") && (elm.nodeName))
   return elm.getElementsByTagName(tag);

console.error("u.etag: first parameter must be an element");
return null;
}

//****************************************

u.estag = function(elmId, tag)
{
if ((typeof elmId) === "string")
   return u.eid(elmId).getElementsByTagName(tag);

console.error("u.estag: first parameter must be id of an element as a string");
return null;
}