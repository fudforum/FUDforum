/***************************************************************************
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License.
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
	} else {
		insertTagMoz(obj, stag, etag);
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
	}

	if (!txt || txt == '') {
		var t = document.getElementById('txtb');
		var scrollPos = t.scrollTop;
		if (t.selectionStart == t.selectionEnd) {
			t.value = t.value.substring(0, t.selectionStart) + stag + etag +  t.value.substring(t.selectionEnd, t.value.length);
			t.scrollTop = scrollPos;
			return;
		}
		txt = t.value.substring(t.selectionStart, t.selectionEnd);
		if (txt) {
			t.value = t.value.substring(0, t.selectionStart) + stag + txt + etag +  t.value.substring(t.selectionEnd, t.value.length);
			t.scrollTop = scrollPos;
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

function highlightWord(node,word,Wno)
{
	// Iterate into this nodes childNodes
	if (node.hasChildNodes) {
		for (var i = 0; node.childNodes[i]; i++) {
			highlightWord(node.childNodes[i], word, Wno);
		}
	}

	// And do this node itself
	if (node.nodeType == 3) { // text node
		var tempNodeVal = node.nodeValue.toLowerCase();
		var pn = node.parentNode;
		var nv = node.nodeValue;

		if ((ni = tempNodeVal.indexOf(word)) == -1 || pn.className.indexOf('st') != -1) return;

		// Create a load of replacement nodes
		before = document.createTextNode(nv.substr(0,ni));
		after = document.createTextNode(nv.substr(ni+word.length));
		if (document.all && !OPERA) {
			hiword = document.createElement('<span class="st'+Wno+'"></span>');
		} else {
			hiword = document.createElement("span");
			hiword.setAttribute('class', 'st'+Wno);
		}
		hiword.appendChild(document.createTextNode(word));
		pn.insertBefore(before,node);
		pn.insertBefore(hiword,node);
		pn.insertBefore(after,node);
		pn.removeChild(node);
	}
}

function highlightSearchTerms(searchText)
{
	searchText = searchText.toLowerCase()
	var terms = searchText.split(" ");
	var e = document.getElementsByTagName('span'); // message body

	for (var i = 0; e[i]; i++) {
		if (e[i].className != 'MsgBodyText') continue;
		for (var j = 0, k = 0; j < terms.length; j++, k++) {
			if (k > 9) k = 0; // we only have 9 colors
			highlightWord(e[i], terms[j], k);
		}
	}

	e = document.getElementsByTagName('td'); // subject
	for (var i = 0; e[i]; i++) {
		if (e[i].className.indexOf('MsgSubText') == -1) continue;
		for (var j = 0, k = 0; j < terms.length; j++, k++) {
			if (k > 9) k = 0; // we only have 9 colors
			highlightWord(e[i], terms[j], k);
		}
	}
}

function rs_txt_box(col_inc, row_inc)
{
        var obj = $('textarea');
        obj.height( obj.height() + row_inc);
        obj.width(obj.width() + col_inc);
}

function topicVote(rating, topic_id, ses, sq)
{
        $.ajax({
          url: 'index.php?t=ratethread&sel_vote='+rating+'&rate_thread_id='+topic_id+'&S='+ses+'&SQ='+sq,
          success: function(data){
                $("#threadRating").html(data);
                $("#RateFrm").empty();
          },
          error: function(xhr, desc, e) {
                alert("Failed to submit: " + desc);
          }
        });
}

function prevCat(id)
{
	var p = document.getElementById(id);
	if (!p) {
		return;
	}
	while (p = p.previousSibling) {
		if (p.id && p.id.substring(0,1) == 'c' && p.style.display != 'none') {
			chng_focus(p.id);
			break;
		}
	}
}

function nextCat(id)
{
	var p = document.getElementById(id);
	if (!p) {
		return;
	}
	while (p = p.nextSibling) {
		if (p.id && p.id.substring(0,1) == 'c' && p.style.display != 'none') {
			chng_focus(p.id);
			break;
		}
	}
}