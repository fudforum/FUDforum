/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id$
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

JS_HELPOFF = false;
/* indentify the browser */
DOM = (document.getElementById) ? 1 : 0;
NS4 = (document.layers) ? 1 : 0;
IE4 = (document.all) ? 1 : 0;
OPERA = (navigator.userAgent.indexOf("Opera 5") > -1 || navigator.userAgent.indexOf("Opera/5") > -1 || navigator.userAgent.indexOf("Opera 6") > -1 || navigator.userAgent.indexOf("Opera/6") > -1) ? 1 : 0;

/* edit box stuff */
function insertTag(obj, stag, etag)
{

	if ( navigator.userAgent.indexOf("MSIE") > -1 && !OPERA ) {
		insertTagIE(obj, stag, etag);
	}
	else {
		insertTagNS(obj, stag, etag);	
	}
}

function insertTagNS(obj, stag, etag)
{
	obj.value = obj.value+stag+etag;	
}

function insertTagIE(obj, stag, etag)
{
	r=document.selection.createRange();
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
var q;
	q = prompt(qst, def);
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
	
}

function window_open(url,winName,width,height)
{
	xpos = (screen.width-width)/2;
	ypos = (screen.height-height)/2;
	options = "scrollbars=1,width="+width+",height="+height+",left="+xpos+",top="+ypos+"position:absolute";
	window.open(url,winName,options);
}

function layerVis(layer,on)
{
        if (on) {
		if (DOM)
			document.getElementById(layer).style.visibility = "visible";
		else if (NS4)
			document.layers[layer].visibility = "show";
		else if (IE4)
			document.all[layer].style.visibility = "visible";
	} else {
		if (DOM)
			document.getElementById(layer).style.visibility = "hidden";
		else if (NS4)
			document.layers[layer].visibility = "hide";
		else if (IE4)
			document.all[layer].style.visibility = "hidden";
	}
}