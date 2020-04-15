/*
 * OIDplus 2.0
 * Copyright 2019 Daniel Marschall, ViaThinkSoft
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*jshint esversion: 6 */

// $('#html').jstree();

var current_node = "";
var popstate_running = false;

function oidplus_loadScript(src) {
	var js = document.createElement('script');
	js.src = src;
	js.onload = function() {
	};
	js.onerror = function() {
	};
	document.head.appendChild(js);
}

function oidplus_external_polyfill() {
	// Disable this code by adding following line to includes/config.inc.php
	// define('RECAPTCHA_ENABLED', false);
	var ua = window.navigator.userAgent;
	if ((ua.indexOf("MSIE ") > 0) || (ua.indexOf("Trident/") > 0)) {
		// Compatibility with Internet Explorer
		oidplus_loadScript('https://polyfill.io/v3/polyfill.min.js?features=fetch%2CURL');
	}
}

function oidplus_external_recaptcha() {
	// Disable this code by adding following lines to includes/config.inc.php
	// define('DISABLE_MSIE_COMPAT', true);
	oidplus_loadScript('https://www.google.com/recaptcha/api.js');
}

String.prototype.explode = function (separator, limit) {
	// https://stackoverflow.com/questions/4514323/javascript-equivalent-to-php-explode
	const array = this.split(separator);
	if (limit !== undefined && array.length >= limit) {
		array.push(array.splice(limit - 1).join(separator));
	}
	return array;
};

String.prototype.htmlentities = function () {
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};

String.prototype.html_entity_decode = function () {
	return $('<textarea />').html(this).text();
};

function getMeta(metaName) {
	const metas = document.getElementsByTagName('meta');

	for (let i = 0; i < metas.length; i++) {
		if (metas[i].getAttribute('name') === metaName) {
			return metas[i].getAttribute('content');
		}
	}

	return '';
}

function getOidPlusSystemTitle() {
	return getMeta('OIDplus-SystemTitle');
}

function combine_systemtitle_and_pagetitle(systemtitle, pagetitle) {
	if (systemtitle == pagetitle) {
		return systemtitle;
	} else {
		return systemtitle + ' - ' + pagetitle;
	}
}

function getTreeLoadURL() {
	var url = new URL(window.location.href);
	var goto = url.searchParams.get("goto");
	return (goto != null) ? "ajax.php?action=tree_load&goto="+encodeURIComponent(goto)
	                      : "ajax.php?action=tree_load";
}

function reloadContent() {
	// window.location.href = "?goto="+encodeURIComponent(current_node);
	openOidInPanel(current_node, false);
	$('#oidtree').jstree("refresh");
}

function x_rec(x_data, i) {
	$('#oidtree').jstree('open_node', x_data[i], function(e, data) {
		if (i+1 < x_data.length) {
			x_rec(x_data, i+1);
		} else {
			popstate_running = true; // don't call openOidInPanel again
			try {
				$('#oidtree').jstree('select_node', x_data[i]);
			} catch (err) {
				popstate_running = false;
			} finally {
				popstate_running = false;
			}
		}
	});
}

function openOidInPanel(id, reselect/*=false*/, anchor/*=''*/) {
	reselect = (typeof reselect === 'undefined') ? false : reselect;
	anchor = (typeof anchor === 'undefined') ? '' : anchor;

	if (reselect) {
		$('#oidtree').jstree('deselect_all');

		popstate_running = true; // don't call openOidInPanel during tree selection
		try {
			// If the node is already loaded in the tree, select it
			if (!$('#oidtree').jstree('select_node', id)) {
				// If the node is not loaded, then we try to search it.
				// If it can be found, then open all parent nodes and select the node
				$.ajax({
					url:"ajax.php",
					method:"POST",
					data:{
						action:"tree_search",
						search:id
					},
					error:function(jqXHR, textStatus, errorThrown) {
						console.error("Error: " + errorThrown);
					},
					success:function(data) {
						if ("error" in data) {
							console.error(data);
						} else if ((data instanceof Array) && (data.length > 0)) {
							x_rec(data, 0);
						} else {
							console.error(data);
						}
					}
				});
			}
		} catch (err) {
			popstate_running = false;
		} finally {
			popstate_running = false;
		}
	}

	// This loads the actual content

	document.title = "";
	$('#real_title').html("&nbsp;");
	$('#real_content').html("Loading...");
	$('#static_link').attr("href", "index.php?goto="+encodeURIComponent(id));
	$("#gotoedit").val(id);

	// Normal opening of a description
	fetch('ajax.php?action=get_description&id='+encodeURIComponent(id))
	.then(function(response) {
		response.json()
		.then(function(data) {
			if ("error" in data) {
				alert("Failed to load content: " + data.error);
				console.error(data.error);
				return;
			}

			data.id = id;

			document.title = combine_systemtitle_and_pagetitle(getOidPlusSystemTitle(), data.title);
			var state = {
				"node_id":id,
				"titleHTML":(data.icon ? '<img src="'+data.icon+'" width="48" height="48" alt="'+data.title.htmlentities()+'"> ' : '') + data.title.htmlentities(),
				"textHTML":data.text,
				"staticlinkHREF":"index.php?goto="+encodeURIComponent(id),
			};
			if (current_node != id) {
				window.history.pushState(state, data.title, "?goto="+encodeURIComponent(id));
			} else {
				window.history.replaceState(state, data.title, "?goto="+encodeURIComponent(id));
			}

			if (data.icon) {
				$('#real_title').html('<img src="'+data.icon+'" width="48" height="48" alt="'+data.title.htmlentities()+'"> ' + data.title.htmlentities());
			} else {
				$('#real_title').html(data.title.htmlentities());
			}
			$('#real_content').html(data.text);
			document.title = combine_systemtitle_and_pagetitle(getOidPlusSystemTitle(), data.title);
			current_node = id;

			if (anchor != '') {
				jumpToAnchor(anchor);
			}
		})
		.catch(function(error) {
			alert("Failed to load content: " + error);
			console.error(error);
		});
	})
	.catch(function(error) {
		alert("Failed to load content: " + error);
		console.error(error);
	});
}

// This function opens the "parentID" node, and then selects the "childID" node (which should be beneath the parent node)
function openAndSelectNode(childID, parentID) {
	if ($('#oidtree').jstree(true).get_node(parentID)) {
		$('#oidtree').jstree('open_node', parentID, function(e, data) { // open parent node
			if ($('#oidtree').jstree(true).get_node(childID)) { // is the child there?
				$('#oidtree').jstree('deselect_all').jstree('select_node', childID); // select it
			} else {
				// This can happen if the content page contains brand new items which are not in the treeview yet
				$("#gotoedit").val(childID);
				window.location.href = "?goto="+encodeURIComponent(childID);
			}
		}, true);
	} else {
		// This should usually not happen
		$("#gotoedit").val(childID);
		window.location.href = "?goto="+encodeURIComponent(childID);
	}
}

$(window).on("popstate", function(e) {
	popstate_running = true;
	try {
		var data = e.originalEvent.state;

		current_node = data.node_id;
		$('#oidtree').jstree('deselect_all').jstree('select_node', data.node_id);
		$('#real_title').html(data.titleHTML);
		$('#real_content').html(data.textHTML);
		$('#static_link').attr("href", data.staticlinkHREF);
		document.title = combine_systemtitle_and_pagetitle(getOidPlusSystemTitle(), data.titleHTML.html_entity_decode());
	} catch (err) {
		popstate_running = false;
	} finally {
		popstate_running = false;
	}
});

$(document).ready(function () {

	// --- JsTree

	$('#oidtree')
	.jstree({
		plugins: ['massload','search','conditionalselect'],
		'core' : {
			'data' : {
				"url" : getTreeLoadURL(),
				"data" : function (node) {
					return { "id" : node.id };
				}
			},
			"multiple": false
		},
		'conditionalselect' : function (node) {
			if (node.original.conditionalselect !== undefined) {
				return eval(node.original.conditionalselect);
			} else {
				return true; // allow select
			}
		},
	})
	.on('ready.jstree', function (e, data) {
		var url = new URL(window.location.href);
		var goto = url.searchParams.get("goto");
		if (goto == null) goto = "oidplus:system"; // the page was not called with ?goto=...
		$("#gotoedit").val(goto);

		// By setting current_node, select_node() will not cause ajax.php?action=get_description to load (since we already loaded the first static content via PHP, for search engines mainly)
		// But then we need to set the history state manually
		current_node = goto;
		window.history.replaceState({
			"node_id":goto,
			"titleHTML":$('#real_title').html(),
			"textHTML":$('#real_content').html(),
			"staticlinkHREF":"index.php?goto="+encodeURIComponent(goto),
		}, $('#real_title').html(), "?goto="+encodeURIComponent(goto));

		if (goto != null) data.instance.select_node([goto]);

		setTimeout(glayoutWorkaroundA, 100);
		setTimeout(glayoutWorkaroundB, 100);
	})
	.on('select_node.jstree', function (node, selected, event) {
		mobileNavClose();

		var id = selected.node.id;
		if ((!popstate_running) && (current_node != id)) {
			openOidInPanel(id, false);
		}
	});

	// --- Layout

	document.getElementById('system_title_menu').style.display = "block";

	$('#oidtree').addClass('ui-layout-west');
	$('#content_window').addClass('ui-layout-center');
	$('#system_title_bar').addClass('ui-layout-north');
	glayout = $('#frames').layout({
		north__size:                  40,
		north__slidable:              false,
		north__closable:              false,
		north__resizable:             false,
		west__size:                   450,
		west__spacing_closed:         20,
		west__togglerLength_closed:   230,
		west__togglerAlign_closed:    "top",
		west__togglerContent_closed:  "O<br>B<br>J<br>E<br>C<br>T<br><br>T<BR>R<BR>E<BR>E",
		west__togglerTip_closed:      "Open & Pin Menu",
		west__sliderTip:              "Slide Open Menu",
		west__slideTrigger_open:      "mouseover",
		center__maskContents:         true // IMPORTANT - enable iframe masking
	});

	$("#gotobox").addClass("mobilehidden");
	document.getElementById('gotobox').style.display = "block";
	$('#gotoedit').keypress(function(event) {
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if (keycode == '13') {
			gotoButtonClicked();
		}
	});
});

function glayoutWorkaroundA() {
	// "Bug A": Sometimes, the design is completely destroyed after reloading the page. It does not help when glayout.resizeAll()
	//          is called at the beginning (e.g. during the ready function), and it does not help if we wait 500ms.
	//          So we do it all the time. It has probably something to do with slow loading times, since the error
	//          does only appear when the page is "blank" for a short while while it is loading.
	glayout.resizeAll();
	setTimeout(glayoutWorkaroundA, 100);
}

function glayoutWorkaroundB() {
	// "Bug B": Sometimes, after reload, weird space between oidtree and content window, because oidtree has size of 438px
	document.getElementById("oidtree").style.width = "450px";
}

function mobileNavClose() {
	if ($("#system_title_menu").is(":hidden")) {
		return;
	}

	$("#oidtree").slideUp("medium").promise().done(function() {
		$("#oidtree").addClass("ui-layout-west");
		$("#oidtree").show();
//		$("#gotobox").hide();
		$("#gotobox").addClass("mobilehidden");
	});
	$("#system_title_menu").removeClass("active");
}

function mobileNavOpen() {
	$("#oidtree").hide();
	$("#oidtree").removeClass("ui-layout-west");
	$("#oidtree").slideDown("medium");
//	$("#gotobox").show();
	$("#gotobox").removeClass("mobilehidden");
	$("#system_title_menu").addClass("active");
}

function mobileNavButtonClick(sender) {
	if ($("#oidtree").hasClass("ui-layout-west")) {
		mobileNavOpen();
	} else {
		mobileNavClose();
	}
}

function mobileNavButtonHover(sender) {
	sender.classList.toggle("hover");
}

function gotoButtonClicked() {
	openOidInPanel($("#gotoedit").val(), 1);
}

function jumpToAnchor(anchor) {
	window.location.href = "#" + anchor;
}

