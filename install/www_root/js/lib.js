/***************************************************************************
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License.
***************************************************************************/

/* Edit box stuff */
function insertTag(obj, startTag, endTag) {
	var field = document.getElementById(obj);
	if (field == null ) {
		field = document.getElementById('txtb');
	}
	var scroll = field.scrollTop;
	field.focus();
	var startSelection   = field.value.substring(0, field.selectionStart);
	var currentSelection = field.value.substring(field.selectionStart, field.selectionEnd);
	var endSelection     = field.value.substring(field.selectionEnd);
	field.value = startSelection + startTag + currentSelection + endTag + endSelection;
	field.focus();
	field.setSelectionRange(startSelection.length + startTag.length, startSelection.length + startTag.length + currentSelection.length);
}

function dialogTag(obj, qst, def, stag, etag)
{
	var q = prompt(qst, def);
	if ( !q ) return;
	stag = stag.replace(/%s/i, q);
	insertTag(obj, stag, etag);
}

function url_insert(prompt)
{
	if ( check_selection() )
		dialogTag(document.post_form.msg_body, prompt, 'http://', '[url=%s]', '[/url]');
	else
		dialogTag(document.post_form.msg_body, prompt, 'http://', '[url]%s[/url]', '');
}

function email_insert(prompt)
{
	if ( check_selection() ) {
		dialogTag(document.post_form.msg_body, prompt, '', '[email=%s]', '[/email]');
	} else {
		dialogTag(document.post_form.msg_body, prompt, '', '[email]%s[/email]', '');
	}
}

function image_insert(prompt)
{
	dialogTag(document.post_form.msg_body, prompt, 'http://', '[img]%s[/img]', '');
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
	if ( navigator.userAgent.indexOf('MSIE') < 0 ) return 0;

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

function window_open(url, winName, width, height)
{
	xpos = (screen.width-width)/2;
	ypos = (screen.height-height)/2;
	options = 'scrollbars=1,width='+width+',height='+height+',left='+xpos+',top='+ypos+'position:absolute';
	window.open(url,winName,options);
}

function layerVis(layer, on)
{
	thisDiv = document.getElementById(layer);
	if (thisDiv) {
		if (thisDiv.style.display == 'none') {
			thisDiv.style.display = 'block';
		} else {
			thisDiv.style.display = 'none';
		}
	}
}

/* Jump page to specified anchor. */
function chng_focus(phash)
{
	window.location.hash = phash;
}

/* Bring message into view. */
function fud_msg_focus(mid_hash)
{
	if (!window.location.hash) {
		self.location.replace(window.location+'#'+mid_hash);
	}
}

/* AJAX call to replace message with next in tree view. */
function fud_tree_msg_focus(mid, s, CHARSET)
{
	jQuery('body').css('cursor', 'progress');
	jQuery('#msgTbl').fadeTo('fast', 0.33);

	jQuery.ajax({
		url: 'index.php?t=tree_msg&id='+mid+'&S='+s,
		dataType: 'html',
		contentType: 'text/html; charset='+CHARSET,
		beforeSend: function(xhr) {
			if (xhr.overrideMimeType) {	// IE doesn't have this
			    xhr.overrideMimeType('text/html; charset='+CHARSET);
			}
		},
		success: function(data){
			// Put new message on page.
			jQuery('#msgTbl').empty().append('<tbody><tr><td>'+data+'</td></tr></tbody>').fadeTo('fast', 1);

			// Mark message as read (unread.png -> read.png).
			var read_img = jQuery('#b' + cur_msg).find('img').first();
			read_img.attr('src', read_img.attr('src').replace('unread', 'read'));

			// Change row color.
			jQuery('#b' + mid).removeClass().addClass('RowStyleC');
			jQuery('#b' + cur_msg).removeClass().addClass( (cur_msg % 2 ? 'RowStyleA' : 'RowStyleB') );
			cur_msg = mid;
		},
		error: function(xhr, desc, e) {
			alert('Failed to submit: ' + desc);
		},
		complete: function() {
			chng_focus('page_top');
			jQuery('body').css('cursor', 'auto');
		}
	});
}

/* Highlight a word in document. */
function highlightWord(node, word, Wno)
{
	/* Iterate into this nodes childNodes */
	if (node.hasChildNodes) {
		for (var i = 0; node.childNodes[i]; i++) {
			highlightWord(node.childNodes[i], word, Wno);
		}
	}

	/* And do this node itself */
	if (node.nodeType == 3) { /* text node */
		var tempNodeVal = node.nodeValue.toLowerCase();
		var pn = node.parentNode;
		var nv = node.nodeValue;

		if ((ni = tempNodeVal.indexOf(word)) == -1 || pn.className.indexOf('st') != -1) return;

		/* Create replacement nodes - preserving case */
		realWord = nv.substr(ni, word.length);
		before = document.createTextNode(nv.substr(0,ni));
		after = document.createTextNode(nv.substr(ni+word.length));
		if (document.all && !OPERA) {
			hiword = document.createElement('<span class="st'+Wno+'"></span>');
		} else {
			hiword = document.createElement('span');
			hiword.setAttribute('class', 'st'+Wno);
		}
		hiword.appendChild(document.createTextNode(realWord));
		pn.insertBefore(before,node);
		pn.insertBefore(hiword,node);
		pn.insertBefore(after,node);
		pn.removeChild(node);
	}
}

/* Highlight search terms in document. */
function highlightSearchTerms(searchText, treatAsPhrase)
{
	searchText = searchText.toLowerCase();
	if (treatAsPhrase) {
    		var terms = [searchText];
	} else {
    		var terms = searchText.split(' ');
	}
	/* Sorting from longest to shortest. */
	terms.sort(function(a, b) {return b.length - a.length});

	var e = document.getElementsByTagName('span');

	/* message body */
	for (var i = 0; e[i]; i++) {
		if (e[i].className != 'MsgBodyText') continue;
		for (var j = 0, k = 0; j < terms.length; j++, k++) {
			if (k > 9) k = 0; /* we only have 9 colors */
			if (terms[j].length > 2) {	/* Skip 1 and 2 char words */
				highlightWord(e[i], terms[j], k);
			}
		}
	}

	/* subject */
	for (var i = 0; e[i]; i++) {
		if (e[i].className.indexOf('MsgSubText') == -1) continue;
		for (var j = 0, k = 0; j < terms.length; j++, k++) {
			if (k > 9) k = 0; /* we only have 9 colors */
			if (terms[j].length > 2) {	/* Skip 1 and 2 char words */
				highlightWord(e[i], terms[j], k);
			}
		}
	}
}

/* Increase or decrease textareas size. Function is depricated, may still be used in old user themes. */
function rs_txt_box(col_inc, row_inc)
{
	var obj = jQuery('textarea');
	obj.height( obj.height() + row_inc);
	obj.width(obj.width() + col_inc);
}

function topicVote(rating, topic_id, ses, sq)
{
	jQuery.ajax({
		url: 'index.php?t=ratethread&sel_vote='+rating+'&rate_thread_id='+topic_id+'&S='+ses+'&SQ='+sq,
		success: function(data){
			jQuery('#threadRating').html(data);
			jQuery('#RateFrm').empty();
		},
		error: function(xhr, desc, e) {
			alert('Failed to submit: ' + desc);
		}
	});
}

function changeKarma(msg_id, user_id, updown, ses, sq)
{
	jQuery.ajax({
		url: 'index.php?t=karma_change&karma_msg_id='+msg_id+'&sel_number='+updown+'&S='+ses+'&SQ='+sq,
		success: function(data){
			jQuery('.karma_usr_'+user_id).html(data);
			jQuery('#karma_link_'+msg_id).hide();
		},
		error: function(xhr, desc, e) {
			alert('Failed to submit: ' + desc);	}
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

function min_max_cats(theme_image_root, minimize_category, maximize_category, sq, s)
{
	jQuery(document).ready(function() {
		var toggleMinus = theme_image_root +'/min.png';
		var togglePlus  = theme_image_root +'/max.png';

		jQuery('.collapsed').prepend('<img src="'+ togglePlus +'" alt="+" title="'+ maximize_category +'" /> ')
		               .addClass('collapsable');
		jQuery('.expanded').prepend('<img src="'+ toggleMinus +'" alt="-" title="'+ minimize_category +'" /> ')
		              .addClass('collapsable');

  jQuery('img', jQuery('.collapsable')).addClass('clickable')
  .css('cursor', 'pointer')
  .click(function(e) {
    var toggleSrc = jQuery(this).attr('src');
    var cat = jQuery(this).parents('tr').attr('id');
    var on;
    e.stopPropagation();

    if ( toggleSrc.indexOf(toggleMinus) >= 0 ) {        /* Hide cat */
      jQuery(this).attr('src', togglePlus)
             .attr('title', maximize_category)
             .attr('alt', '+')
             .parents('tr').siblings('.child-'+cat).fadeOut('slow');
      on = 1;
    } else{                             /* Show cat */
      jQuery(this).attr('src', toggleMinus)
             .attr('title', minimize_category)
             .attr('alt', '-')
             .parents('tr').siblings('.child-'+cat).fadeIn('slow');
      on = 0;
    };

    if (sq != '') {
       jQuery.ajax({
          type: 'POST',
          url: 'index.php?t=cat_focus',
          data: 'SQ='+ sq +'&S='+ s +'&c='+ cat.substr(1) +'&on='+ on
        });
    } 

  });
})

}

function min_max_posts(theme_image_root, minimize_message, maximize_message)
{
jQuery(document).ready(function() {
  var toggleMinus = theme_image_root +'/min.png';
  var togglePlus  = theme_image_root +'/max.png';

  jQuery('.collapsed').prepend('<img src="'+ togglePlus +'" alt="+" title="'+ maximize_message +'" class="collapsable" /> ');
  jQuery('.expanded').prepend('<img src="'+ toggleMinus +'" alt="-" title="'+ minimize_message +'" class="collapsable" /> ');

  jQuery('.collapsable').addClass('clickable').css('cursor', 'pointer')
  .click(function() {
    var toggleSrc = jQuery(this).attr('src');

    if ( toggleSrc.indexOf(toggleMinus) >= 0 ) {        /* Hide message */
      jQuery(this).attr('src', togglePlus)
             .attr('title', maximize_message)
             .attr('alt', '+')
             .parents('.MsgTable').find('td').not('.MsgR1').fadeOut('slow');
    } else {                                             /* Show message */
      jQuery(this).attr('src', toggleMinus)
             .attr('title', minimize_message)
             .attr('alt', '-')
             .parents('.MsgTable').find('td').fadeIn('slow');
    };
  });
})
}

/* Highlight code, ready for copying. */
function select_code(a) {
   'use strict';

   // Get ID of code block
   var e = a.parentNode.parentNode.getElementsByTagName('PRE')[0];
   var s, r;

   // Not IE and IE9+
   if (window.getSelection) {
      s = window.getSelection();
      // Safari and Chrome
      if (s.setBaseAndExtent) {
         var l = (e.innerText.length > 1) ? e.innerText.length - 1 : 1;
         try {
            s.setBaseAndExtent(e, 0, e, l);
         } catch (error) {
            r = document.createRange();
            r.selectNodeContents(e);
            s.removeAllRanges();
            s.addRange(r);
         }
      }
      // Firefox and Opera
      else {
         // workaround for bug # 42885
         if (window.opera && e.innerHTML.substring(e.innerHTML.length - 4) === '<BR>') {
            e.innerHTML = e.innerHTML + '&nbsp;';
         }

         r = document.createRange();
         r.selectNodeContents(e);
         s.removeAllRanges();
         s.addRange(r);
      }
   }
   // Some older browsers
   else if (document.getSelection) {
      s = document.getSelection();
      r = document.createRange();
      r.selectNodeContents(e);
      s.removeAllRanges();
      s.addRange(r);
   }
   // IE
   else if (document.selection) {
      r = document.body.createTextRange();
      r.moveToElementText(e);
      r.select();
   }
}

/* Add controls to code blocks. */
function format_code(codeMsg, selMsg, hideMsg) 
{
	jQuery(document).ready(function() {
		jQuery('div pre').each(function() {
			// jQuery(this).addClass('highlight');
			var content = jQuery(this).parent().html();
			jQuery(this).parent().html(
			  '<span><div class="codehead">'+codeMsg+' '+
			  '[<a href="#" onclick="select_code(this); return false;">'+selMsg+'</a>] '+
			  '[<a href="#" onclick="jQuery(this).parent().parent().find(\'pre\').slideToggle(); return false;">'+hideMsg+'</a>]'+
			  '</div>'+content+'</span>');
		});
	});
}

/* Allow users to select text and add it as a quote to the message box. */
function quote_selected_text(quoteButtonText) {
	// Add "Quote selected text" button.
	jQuery(".miniMH").parent().parent().append('<div class="ar"><button class="button" id="quote">'+ quoteButtonText +'</button></class>');

	// Handle button clicks.
	jQuery("#quote").click(function() {
		//  Get user selected text.
		var selectedText = '';
		if(window.getSelection){
			selectedText = window.getSelection().toString();
		} else if(document.getSelection){
			selectedText = document.getSelection();
		} else if(document.selection){
			selectedText = document.selection.createRange().text;
		}

		// Append it to the textarea as quoted text.
		if (selectedText) {
			var textAreaVal = jQuery("#txtb").val();
			jQuery("#txtb").val(textAreaVal +"\n[quote]"+ selectedText +"[/quote]").focus();
		}
	});
}

/* Visual indication to confirm password matches the original password. 
 * Passwords must be at least 6 characters long.
 */
function passwords_match(password1, password2) {
	if (jQuery(password2).val().length >= 6 && jQuery(password2).val() == jQuery('#'+ password1).val()) {
		jQuery(password2).css("color", "green");
	} else {
		jQuery(password2).css("color", "red");
	}
}

/* Code that will run on each page. */
jQuery(function init() {
	/* Open external links in a new window. */
	jQuery('a[href^="http://"], a[href^="https://"]').attr({
		target: "_blank", 
		title: "Opens in a new window"
	});
	// .append('<small><sup>&crarr;</sup></small>');

	/* Add rel="nofollow" to external links. */
	// jQuery('a[href^="http"]').attr('rel','nofollow');

	/* Make textareas resizable (jQuery UI). */
	jQuery("textarea:visible").resizable({
		minHeight: 32,	// Leave at least one line in the textarea.
		handles: "s"	// South handle at the bottom.
	});

	/* Enable jQuery UI buttons. */
	// jQuery(".button").button();

	/* jQuery  syntax highlighting for code blocks. */
	// jQuery.SyntaxHighlighter.init();
	
	/* jQuery TimeAgo plugin. */
	// jQuery('.DateText').timeago();
	// jQuery("time").timeago();
	// Example: <time class="DateText" datetime="2008-07-17T09:24:17Z">July 17, 2008</time>

});
