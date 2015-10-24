/*
 * Context.js
 * Copyright Jacob Kelley
 * MIT License
 */

var context = context || (function () {

	var options = {
		fadeSpeed: 100,
		filter: function ($obj) {
			// Modify $obj, Do not return
		},
		above: 'auto',
		preventDoubleContext: true,
		compress: false
	};

	function initialize(opts) {

		options = $.extend({}, options, opts);

		$(document).on('click', 'html', function () {
			$('.dropdown-context').fadeOut(options.fadeSpeed, function(){
				$('.dropdown-context').css({display:''}).find('.drop-left').removeClass('drop-left');
			});
		});
		if(options.preventDoubleContext){
			$(document).on('contextmenu', '.dropdown-context', function (e) {
				e.preventDefault();
			});
		}
		$(document).on('mouseenter', '.dropdown-submenu', function(){
			var $sub = $(this).find('.dropdown-context-sub:first'),
				subWidth = $sub.width(),
				subLeft = $sub.offset().left,
				collision = (subWidth+subLeft) > window.innerWidth;
			if(collision){
				$sub.addClass('drop-left');
			}
		});

	}

	function updateOptions(opts){
		options = $.extend({}, options, opts);
	}

	function buildMenu(data, id, subMenu) {
		var subClass = (subMenu) ? ' dropdown-context-sub' : '',
			compressed = options.compress ? ' compressed-context' : '',
			$menu = $('<ul class="dropdown-menu dropdown-context' + subClass + compressed+'" id="dropdown-' + id + '"></ul>');
		var i = 0, linkTarget = '', useIcons = false;
		for(i; i<data.length; i++) {
			if (typeof data[i].icon !== 'undefined' && data[i].icon !== '' ) {
				$menu.addClass('fa-ul');
				useIcons = true;
				break;
			}
		}
		for(i = 0; i<data.length; i++) {
			var icon = '';
			if (typeof data[i].divider !== 'undefined') {
				$menu.append('<li class="divider"></li>');
			} else if (typeof data[i].header !== 'undefined') {
				if (typeof data[i].image !== 'undefined' && data[i].image !== '') {
					$menu.append('<li class="nav-header"><img src="' + data[i].image + '"/> ' + data[i].header + '</li>');
				} else {
					$menu.append('<li class="nav-header">' + data[i].header + '</li>');
				}

			} else {
				if (typeof data[i].href == 'undefined') {
					data[i].href = '#';
				}
				if (typeof data[i].target !== 'undefined') {
					linkTarget = ' target="'+data[i].target+'"';
				}
				if (useIcons){
					if (! options.compress ) {
						largeIcon = " fa-lg ";
					} else {
						largeIcon = "";
					}
					if (typeof data[i].icon !== 'undefined' && data[i].icon !== '' ) {
						icon = ' <i class="fa fa-fw ' + data[i].icon + largeIcon + '"></i>';
					} else {
						icon = ' <i class="fa fa-fw"></i>';
					}
				}
				if (typeof data[i].subMenu !== 'undefined') {
					$sub = ('<li><a tabindex="-1" href="' + data[i].href + '">' + icon + "&nbsp;&nbsp;" + data[i].text + '</a></li>');
				} else {
					$sub = $('<li><a tabindex="-1" href="' + data[i].href + '"'+linkTarget+'>' + icon + "&nbsp;&nbsp;" + data[i].text + '</a></li>');
				}
				if (typeof data[i].action !== 'undefined') {
					var actiond = new Date(),
						actionID = 'event-' + actiond.getTime() * Math.floor(Math.random()*100000),
						eventAction = data[i].action;
					$sub.find('a').attr('id', actionID);
					$('#' + actionID).addClass('context-event');
					$(document).on('click', '#' + actionID, eventAction);
				}
				$menu.append($sub);
				if (typeof data[i].subMenu != 'undefined') {
					var subMenuData = buildMenu(data[i].subMenu, id, true);
					$menu.find('li:last').append(subMenuData);
				}
			}
			if (typeof options.filter == 'function') {
				options.filter($menu.find('li:last'));
			}
		}
		return $menu;
	}

	function addContext(selector, data) {
		destroyContext(selector);

		var id = selector.replace('#', ''),
			$menu = buildMenu(data, id);

		$('body').append($menu);

		$(document).on('click', selector, function (e) {
			e.preventDefault();
			e.stopPropagation();

			$('.dropdown-context:not(.dropdown-context-sub)').hide();
			$dd = $('#dropdown-' + id);

			var place_above = false;
			if (typeof options.above == 'boolean') {
				place_above = options.above;
			} else if (typeof options.above == 'string' && options.above == 'auto') {
				place_above = ((e.pageY + $dd.height() + 32) > $(document).height());
			}

			if (place_above) {
				$dd.addClass('dropdown-context-up').css({
					top: e.pageY - 24 - $dd.height(),
					left: Math.min(Math.max(e.pageX - 13, 0), window.innerWidth - 168)
				}).fadeIn(options.fadeSpeed);
			} else {
				$dd.removeClass('dropdown-context-up').css({
					top: e.pageY + 24,
					left: Math.min(Math.max(e.pageX - 13, 0), window.innerWidth - 168)
				}).fadeIn(options.fadeSpeed);
			}
		});
	}

	function destroyContext(selector) {
		var id = selector.replace('#', '');

		$(document).off('contextmenu', selector).off('click', '.context-event');
		$('#dropdown-' + id).remove();
	}

	return {
		init: initialize,
		settings: updateOptions,
		attach: addContext,
		destroy: destroyContext
	};
})();