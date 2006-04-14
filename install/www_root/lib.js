/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

var JS_HELPOFF = false;
/* indentify the browser */
var DOM = (document.getElementById) ? 1 : 0;
var NS4 = (document.layers) ? 1 : 0;
var IE4 = (document.all) ? 1 : 0;
var OPERA = navigator.userAgent.indexOf("Opera") > -1 ? 1 : 0;
var MAC = navigator.userAgent.indexOf("Mac") > -1 ? 1 : 0;

/* edit box stuff */
function insertTag(obj, stag, etag)
{
	if (navigator.userAgent.indexOf("MSIE") > -1 && !OPERA) {
		insertTagIE(obj, stag, etag);
	} else if (window.getSelection) {
		insertTagMoz(obj, stag, etag);
	} else {
		insertTagNS(obj, stag, etag);
	}
	obj.focus();
}

function insertTagNS(obj, stag, etag)
{
	obj.value = obj.value+stag+etag;
}

function insertTagMoz(obj, stag, etag)
{
	var txt;

	if (window.getSelection) {
		txt = window.getSelection();
	} else if (document.getSelection) {
		txt = document.getSelection();
	} else if (document.selection) {
		txt = document.selection.createRange().text;
	}

	if (!txt || txt == '') {
		var t = document.getElementById('txtb');
		var h = document.getElementsByTagName('textarea')[0];
		if (t.selectionStart == t.selectionEnd) {
			t.value = t.value.substring(0, t.selectionStart) + stag + etag +  t.value.substring(t.selectionEnd, t.value.length);
			return;
		}
		txt = t.value.substring(t.selectionStart, t.selectionEnd);
		if (txt) {
			t.value = t.value.substring(0, t.selectionStart) + stag + txt + etag +  t.value.substring(t.selectionEnd, t.value.length);
			return;
		}
	}
	obj.value = obj.value+stag+etag;
}

function insertTagIE(obj, stag, etag)
{
	var r = document.selection.createRange();
	if( document.selection.type == 'Text' && (obj.value.indexOf(r.text) != -1) ) {
		a = r.text;
		r.text = stag+r.text+etag;
		if ( obj.value.indexOf(document.selection.createRange().text) == -1 ) {
			document.selection.createRange().text = a;
		}
	}
	else insertAtCaret(obj, stag+etag);	
}

function dialogTag(obj, qst, def, stag, etag)
{
	var q = prompt(qst, def);
	if ( !q ) return;
	stag = stag.replace(/%s/i, q);
	insertTag(obj, stag, etag);
}

function url_insert()
{
	if ( check_selection() )
		dialogTag(document.post_form.msg_body, 'Location:', 'http://', '[url=%s]', '[/url]');
	else
		dialogTag(document.post_form.msg_body, 'Location:', 'http://', '[url]%s[/url]', '');
}

function check_selection()
{
	var rn;
	var sel;
	var r;

	if (window.getSelection && window.getSelection()) {
		return 1;
	}

	if ( document.layers ) return 0;
	if ( navigator.userAgent.indexOf("MSIE") < 0 ) return 0;

	r = document.selection.createRange();

	if ( r.text.length && (document.post_form.msg_body.value.indexOf(r.text) != -1) ) {
		a = document.selection.createRange().text;
		rn = Math.random();
		r.text = r.text + ' ' + rn;
		
		if ( document.post_form.msg_body.value.indexOf(rn) != -1 ) {
			sel = 1;
		} else {
			sel = 0;
		}
		
		document.selection.createRange().text = a;
	}
	
	return sel;
}

function storeCaret(textEl)
{
	 if (textEl.createTextRange) textEl.caretPos = document.selection.createRange().duplicate();
}

function insertAtCaret(textEl, text)
{
	if (textEl.createTextRange && textEl.caretPos)
	{
		var caretPos = textEl.caretPos;
		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
	}
	else 
		textEl.value  =  textEl.value + text;
}

function email_insert()
{
	if ( check_selection() ) {
		dialogTag(document.post_form.msg_body, 'Email:', '', '[url=mailto:%s]', '[/url]');
	}	
	else
		dialogTag(document.post_form.msg_body, 'Email:', '', '[email]%s[/email]', '');
}

function image_insert()
{
	dialogTag(document.post_form.msg_body, 'Image URL:', 'http://', '[img]%s[/img]', '');
}

function insertParentTagIE(stag, etag)
{
	r=window.opener.document.selection.createRange();
	obj = window.opener.document.post_form.msg_body;
	
	if( window.opener.document.selection.type == 'Text' && (obj.value.indexOf(r.text) != -1) ) {
		a = r.text;
		r.text = stag+r.text+etag;
		if ( obj.value.indexOf(window.opener.document.selection.createRange().text) == -1 ) {
			window.opener.document.selection.createRange().text = a;
		}
	}
	else insertAtCaret(obj, stag+etag);
}

function insertParentTagNS(stag, etag)
{
	window.opener.document.post_form.msg_body.value = window.opener.document.post_form.msg_body.value + stag + etag;
}

function insertParentTag(stag, etag)
{
	if ( document.all ) 
		insertParentTagIE(stag, etag);
	else
		insertParentTagNS(stag, etag);

	window.opener.document.post_form.msg_body.focus();	
}

function window_open(url,winName,width,height)
{
	xpos = (screen.width-width)/2;
	ypos = (screen.height-height)/2;
	options = "scrollbars=1,width="+width+",height="+height+",left="+xpos+",top="+ypos+"position:absolute";
	window.open(url,winName,options);
}

function layerVis(layer, on)
{
	thisDiv = document.getElementById(layer);
	if (thisDiv) {
		if (thisDiv.style.display == "none") {
			thisDiv.style.display = "block";
		} else {
			thisDiv.style.display = "none";
		}
	}
}

function fud_msg_focus(mid_hash)
{
	if (!window.location.hash) {
		self.location.replace(window.location+"#"+mid_hash);
	}
}

function chng_focus(phash)
{
	window.location.hash = phash;
}

function doHighlight(bodyText, searchTerm, highlightStartTag, highlightEndTag) 
{
	// find all occurences of the search term in the given text,
	// and add some "highlight" tags to them (we're not using a
	// regular expression search, because we want to filter out
	// matches that occur within HTML tags and script blocks, so
	// we have to do a little extra validation)
	var newText = "";
	var i = 0, j = 0;
	var lcSearchTerm = searchTerm.toLowerCase();
	var lcBodyText = bodyText.toLowerCase();

	while ((i = lcBodyText.indexOf(lcSearchTerm, i)) > 0) {
		if (lcBodyText.lastIndexOf(">", i) >= lcBodyText.lastIndexOf("<", i)) {
			if (lcBodyText.lastIndexOf("/script>", i) >= lcBodyText.lastIndexOf("<script", i)) {
				newText += bodyText.substring(j, i) + highlightStartTag + bodyText.substr(i, searchTerm.length) + highlightEndTag;
				i += searchTerm.length;
				j = i;
				continue;
			}
		}
		i++;
	}
	newText += bodyText.substring(j, bodyText.length);

	return newText;
}

function highlightSearchTerms(searchText)
{
	if (!document.body || typeof(document.body.innerHTML) == "undefined") {
		return false;
	}
  
	var bodyText = document.body.innerHTML;
	searchArray = searchText.split(" ");

	var j = 0;
	for (var i = 0; i < searchArray.length; i++) {
		if (j > 9) j = 0;
		bodyText = doHighlight(bodyText, searchArray[i], '<span class="st'+j+'">', '</span>');
		j++;
	}

	document.body.innerHTML = bodyText;
	return true;
}

function rs_txt_box(name, col_inc, row_inc)
{
        if (IE4) {  
                var obj = document.all[name];
        } else {
                var obj = document.getElementById(name);
        }                                   

        obj.rows += row_inc;           
        obj.cols += col_inc;            
}

function topicVote(rating, topic_id, ses, sq)
{
	var responseFailure = function(o) { alert('XMLHTTPRequest Failure: ' + o.statusText + ' ' + o.allResponseHeaders + ' ' + o.status); }
	var rateTopic = function(o) { 
		if (o.responseText) {
			document.getElementById('threadRating').innerHTML = o.responseText;
			var p = document.getElementById('RateFrm').parentNode;
			p.removeChild(document.getElementById('RateFrm'));
		}
	}

	var callback = 
	{
		success:rateTopic,
		failure:responseFailure
	}

	YAHOO.util.Connect.asyncRequest('GET','index.php?t=ratethread&sel_vote='+rating+'&rate_thread_id='+topic_id+'&S='+ses+'&SQ='+sq,callback);
}

/* Copyright (c) 2006 Yahoo! Inc. All rights reserved. */

/**
 * @class The Yahoo global namespace
 */
var YAHOO = function() {

    return {
        util: {},
        widget: {},
        example: {},
        namespace: function( sNameSpace ) {

            if (!sNameSpace || !sNameSpace.length) {
                return null;
            }

            var levels = sNameSpace.split(".");

            var currentNS = YAHOO;

            for (var i=(levels[0] == "YAHOO") ? 1 : 0; i<levels.length; ++i) {
                currentNS[levels[i]] = currentNS[levels[i]] || {};
                currentNS = currentNS[levels[i]];
            }

            return currentNS;

        }
    };

} ();

/*
Copyright (c) 2006 Yahoo! Inc. All rights reserved.
version 0.9.0
*/

YAHOO.util.Connect = {};

YAHOO.util.Connect =
{
	_msxml_progid:[
		'MSXML2.XMLHTTP.5.0',
		'MSXML2.XMLHTTP.4.0',
		'MSXML2.XMLHTTP.3.0',
		'MSXML2.XMLHTTP',
		'Microsoft.XMLHTTP'
		],

	_http_header:[],
	_isFormPost:false,
	_sFormData:null,
	_polling_interval:300,
	_transaction_id:0,

	setProgId:function(id)
	{
		this.msxml_progid.unshift(id);
	},

	createXhrObject:function(transactionId)
	{
		var obj,http;
		try
		{
			http = new XMLHttpRequest();
			obj = { conn:http, tId:transactionId };
		}
		catch(e)
		{
			for(var i=0; i<this._msxml_progid.length; ++i){
				try
				{
					http = new ActiveXObject(this._msxml_progid[i]);
					obj = { conn:http, tId:transactionId };
				}
				catch(e){}
			}
		}
		finally
		{
			return obj;
		}
	},

	getConnectionObject:function()
	{
		var o;
		var tId = this._transaction_id;

		try
		{
			o = this.createXhrObject(tId);
			if(o){
				this._transaction_id++;
			}
		}
		catch(e){}
		finally
		{
			return o;
		}
	},

	asyncRequest:function(method, uri, callback, postData)
	{
		var errorObj;
		var o = this.getConnectionObject();

		if(!o){
			return null;
		}
		else{
			var oConn = this;
			o.conn.open(method, uri, true);
			this.handleReadyState(o, callback);

			if(this._isFormPost){
				postData = this._sFormData;
				this._isFormPost = false;
			}
			else if(postData){
				this.initHeader('Content-Type','application/x-www-form-urlencoded');
			}

			if(this._http_header.length>0){
				this.setHeader(o);
			}
			postData?o.conn.send(postData):o.conn.send(null);

			return o;
		}
	},

	handleReadyState:function(o, callback)
	{
		var oConn = this;
		var poll = window.setInterval(
			function(){
				if(o.conn.readyState==4){
					oConn.handleTransactionResponse(o, callback);
					window.clearInterval(poll);
				}
			}
		,this._polling_interval);
	},

	handleTransactionResponse:function(o, callback)
	{
		var httpStatus;
		var responseObject;

		try{
			httpStatus = o.conn.status;
		}
		catch(e){
			httpStatus = 13030;
		}

		if(httpStatus == 200){
			responseObject = this.createResponseObject(o, callback.argument);
			if(callback.success){
				if(!callback.scope){
					callback.success(responseObject);
				}
				else{
					callback.success.apply(callback.scope, [responseObject]);
				}
			}
		}
		else{
			switch(httpStatus){
				case 12002:
				case 12029:
				case 12030:
				case 12031:
				case 12152:
				case 13030:
					responseObject = this.createExceptionObject(o, callback.argument);
					if(callback.failure){
						if(!callback.scope){
							callback.failure(responseObject);
						}
						else{
							callback.failure.apply(callback.scope,[responseObject]);
						}
					}
					break;
				default:
					responseObject = this.createResponseObject(o, callback.argument);
					if(callback.failure){
						if(!callback.scope){
							callback.failure(responseObject);
						}
						else{
							callback.failure.apply(callback.scope,[responseObject]);
						}
					}
			}
		}

		this.releaseObject(o);
	},

	createResponseObject:function(o, callbackArg)
	{
		var obj = {};

		obj.tId = o.tId;
		obj.status = o.conn.status;
		obj.statusText = o.conn.statusText;
		obj.allResponseHeaders = o.conn.getAllResponseHeaders();
		obj.responseText = o.conn.responseText;
		obj.responseXML = o.conn.responseXML;
		if(callbackArg){
			obj.argument = callbackArg;
		}

		return obj;
	},

	createExceptionObject:function(tId, callbackArg)
	{
		var COMM_CODE = 0;
		var COMM_ERROR = 'communication failure';

		var obj = {};

		obj.tId = tId;
		obj.status = COMM_CODE;
		obj.statusText = COMM_ERROR;
		if(callbackArg){
			obj.argument = callbackArg;
		}

		return obj;
	},

	initHeader:function(label,value)
	{
		var oHeader = [label,value];
		this._http_header.push(oHeader);
	},

	setHeader:function(o)
	{
		var oHeader = this._http_header;
		for(var i=0;i<oHeader.length;i++){
			o.conn.setRequestHeader(oHeader[i][0],oHeader[i][1]);
		}
		oHeader.splice(0,oHeader.length);
	},

	setForm:function(formName)
	{
		this._sFormData = '';
		var oForm = document.forms[formName];
		var oElement, elName, elValue;
		for (var i=0; i<oForm.elements.length; i++){
			oElement = oForm.elements[i];
			elName = oForm.elements[i].name;
			elValue = oForm.elements[i].value;
			switch (oElement.type)
			{
				case 'select-multiple':
					for(var j=0; j<oElement.options.length; j++){
						if(oElement.options[j].selected){
							this._sFormData += encodeURIComponent(elName) + '=' + encodeURIComponent(oElement.options[j].value) + '&';
						}
					}
					break;
				case 'radio':
				case 'checkbox':
					if(oElement.checked){
						this._sFormData += encodeURIComponent(elName) + '=' + encodeURIComponent(elValue) + '&';
					}
					break;
				case 'file':
					break;
				case undefined:
					break;
				default:
					this._sFormData += encodeURIComponent(elName) + '=' + encodeURIComponent(elValue) + '&';
					break;
			}
		}
		this._sFormData = this._sFormData.substr(0, this._sFormData.length - 1);
		this._isFormPost = true;
		this.initHeader('Content-Type','application/x-www-form-urlencoded');
	},

	abort:function(o)
	{
		if(this.isCallInProgress(o)){
			o.conn.abort();
			this.releaseObject(o);
		}
	},

	isCallInProgress:function(o)
	{
		if(o){
			return o.conn.readyState != 4 && o.conn.readyState != 0;
		}
	},

	releaseObject:function(o)
	{
			o.conn = null;
			o = null;
	}
}