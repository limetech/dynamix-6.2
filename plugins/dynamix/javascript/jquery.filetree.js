/**
 * jQuery File Tree Plugin
 *
 * @author - Cory S.N. LaViska - A Beautiful Site (http://abeautifulsite.net/) - 24 March 2008
 * @author - Dave Rogers - https://github.com/daverogers/jQueryFileTree
 *
 *
 * Usage: $('.fileTreeDemo').fileTree( options, callback )
 *
 * Options:  root           - root folder to display; default = /
 *           filter         - filter on file extension; default = none
 *           script         - location of the serverside AJAX file to use; default = FileTree.php
 *           folderEvent    - event to trigger expand/collapse; default = click
 *           expandSpeed    - default = 500 (ms); use -1 for no animation
 *           collapseSpeed  - default = 500 (ms); use -1 for no animation
 *           expandEasing   - easing function to use on expand (optional)
 *           collapseEasing - easing function to use on collapse (optional)
 *           multiFolder    - whether or not to limit the browser to one subfolder at a time
 *           loadMessage    - Message to display while initial tree loads (can be HTML)
 *           multiSelect    - append checkbox to each line item to select more than one
 *
 *
 * TERMS OF USE
 *
 * This plugin is dual-licensed under the GNU General Public License and the MIT License and
 * is copyright 2008 A Beautiful Site, LLC.
 */

if(jQuery) (function($){

	$.extend($.fn, {
		fileTree: function(options, file, folder) {
			// Default options
			if( options.root			=== undefined ) options.root			= '/mnt/';
			if( options.filter			=== undefined ) options.filter			= '';
			if( options.script			=== undefined ) options.script			= '/webGui/include/FileTree.php';
			if( options.folderEvent		=== undefined ) options.folderEvent		= 'click';
			if( options.expandSpeed		=== undefined ) options.expandSpeed		= 300;
			if( options.collapseSpeed	=== undefined ) options.collapseSpeed	= 300;
			if( options.expandEasing	=== undefined ) options.expandEasing	= null;
			if( options.collapseEasing	=== undefined ) options.collapseEasing	= null;
			if( options.multiFolder		=== undefined ) options.multiFolder		= false;
			if( options.loadMessage		=== undefined ) options.loadMessage		= 'Loading...';
			if( options.multiSelect		=== undefined ) options.multiSelect		= false;
			if( options.allowBrowsing	=== undefined ) options.allowBrowsing	= false;

			$(this).each( function() {

				function showTree(element, dir, show_parent) {
					$(element).addClass('wait');
					$(".jqueryFileTree.start").remove();
					$.post(options.script,
					{
						dir: dir,
						multiSelect: options.multiSelect,
						filter: options.filter,
						show_parent : show_parent
					})
					.done(function(data){
						$(element).find('.start').html('');
						$(element).removeClass('wait').append(data);
						if( options.root == dir ) $(element).find('UL:hidden').show(); else $(element).find('UL:hidden').slideDown({ duration: options.expandSpeed, easing: options.expandEasing });
						bindTree(element);

						//$(this).parent().removeClass('collapsed').addClass('expanded');

						_trigger($(this), 'filetreeexpanded', data);
					})
					.fail(function(){
						$(element).find('.start').html('');
						$(element).removeClass('wait').append("<li>Unable to get file tree information</li>");
					});
				}

				function bindTree(element) {
					$(element).find('LI A').on(options.folderEvent, function(event) {
						event.preventDefault();
						// set up data object to send back via trigger
						var data = {};
						data.li = $(this).closest('li');
						data.type = ( data.li.hasClass('directory') ? 'directory' : 'file' );
						data.value	= $(this).text();
						data.rel	= $(this).prop('rel');
						if ($(this).text() == "..") {
							// Restart fileTree with the parent dir as root
							options.root = data.rel;
							if (folder) folder($(this).attr('rel'));
							_trigger($(this), 'filetreefolderclicked', data);
							root = $(this).closest('ul.jqueryFileTree');
							root.html('<ul class="jqueryFileTree start"><li class="wait">' + options.loadMessage + '<li></ul>');
							showTree( $(root), options.root, options.allowBrowsing );
						} else if( $(this).parent().hasClass('directory') ) {
							if( $(this).parent().hasClass('collapsed') ) {
								// Expand
								_trigger($(this), 'filetreeexpand', data);

								if( !options.multiFolder ) {
									$(this).parent().parent().find('UL').slideUp({ duration: options.collapseSpeed, easing: options.collapseEasing });
									$(this).parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
								}

								$(this).parent().removeClass('collapsed').addClass('expanded');
								$(this).parent().find('UL').remove(); // cleanup
								showTree( $(this).parent(), $(this).attr('rel').match( /.*\// )[0], false );
							} else {
								// Collapse
								_trigger($(this), 'filetreecollapse', data);

								$(this).parent().find('UL').slideUp({ duration: options.collapseSpeed, easing: options.collapseEasing });
								$(this).parent().removeClass('expanded').addClass('collapsed');

								_trigger($(this), 'filetreecollapsed', data);
							}

							// this is a folder click, return folder information
							if (folder) folder($(this).attr('rel'));

							_trigger($(this), 'filetreefolderclicked', data);
						} else {
							// this is a file click, return file information
							if (file) file($(this).attr('rel'));

							_trigger($(this), 'filetreeclicked', data);
						}
						return false;
					});
					// Prevent A from triggering the # on non-click events
					if( options.folderEvent.toLowerCase != 'click' ) $(element).find('LI A').on('click', function(event) { event.preventDefault(); return false; });
				}

				// Loading message
				$(this).html('<ul class="jqueryFileTree start"><li class="wait">' + options.loadMessage + '<li></ul>');

				// Get the initial file list
				showTree( $(this), options.root, options.allowBrowsing );

				// wrapper to append trigger type to data
				function _trigger(element, eventType, data) {
					data.trigger = eventType;
					element.trigger(eventType, data);
				}

				// checkbox event (multiSelect)
				$(this).on('change', 'input:checkbox' , function(){
					var data = {};
					data.li		= $(this).closest('li');
					data.type	= ( data.li.hasClass('directory') ? 'directory' : 'file' );
					data.value	= data.li.children('a').text();
					data.rel	= data.li.children('a').prop('rel');

					// propagate check status to (visible) child checkboxes
					data.li.find('input:checkbox').prop( 'checked', $(this).prop('checked') );

					// set triggers
					if( $(this).prop('checked') )
						_trigger($(this), 'filetreechecked', data);
					else
						_trigger($(this), 'filetreeunchecked', data);
				});
			});
		},

		// Univeral file tree attacher
		//
		// Valid options:
		//   pickfolders (true/false)  -- if true, the input value is populated when clicking folders within the tree picker
		//   pickfilter (string)  -- File extension to filter on, comma-seperated for multiple
		//   pickroot (string)  -- The initial path to "start in" and list folders from
		//   pickcloseonfile (true/false)  -- if true, hides the tree picker after selecting a file
		//
		// The above options can exists (and override) as html data attributes (e.g. data-pickroot="/mnt/" )
		fileTreeAttach: function(options, file_event, folder_event) {
			var baseconfig = {};

			if ($.isFunction(options)) {
				if ($.isFunction(file_event)) {
					folder_event = file_event;
				}
				file_event = options;
			} else if (options) {
				$.extend(baseconfig, options);
			}

			$(this).each( function() {
				var input = $(this);
				var config = $.extend({}, baseconfig, input.data());
				var picker = input.next(".fileTree");

				if (picker.length === 0) {
					$(document).mousedown(function hideTreePicker(e) {
						var container = $(".fileTree");
						if (!container.is(e.target) && container.has(e.target).length === 0) {
							container.slideUp('fast');
						}
					});

					picker = $('<div>', {'class': 'textarea fileTree'});

					input.after(picker);
				}

				input.click(function() {
					if (picker.is(':visible')) {
						picker.slideUp('fast');
					} else {
						if (picker.html() === "") {
							picker.html('<span style="padding-left: 20px"><img src="/webGui/images/spinner.gif"> Loading...</span>');

							picker.fileTree({
									root: config.pickroot,
									filter: (config.pickfilter || '').split(",")
								},
								($.isFunction(file_event) ? file_event : function(file) {
									input.val(file).change();
									if (config.hasOwnProperty('pickcloseonfile')) {
										picker.slideUp('fast');
									}
								}),
								($.isFunction(folder_event) ? folder_event : function(folder) {
									if (config.hasOwnProperty('pickfolders')) {
										input.val(folder).change();
									}
								})
							);
						}

						picker.offset({left: input.position().left});
						picker.slideDown('fast');
					}
				});
			});
		}
	});

})(jQuery);
