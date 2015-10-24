function clearHistory(){
	window.history.pushState('VMs', 'Title', '/VMs');
}

function ordinal_suffix_of(i) {
	var j = i % 10,
		k = i % 100;
	if (j == 1 && k != 11) {
		return i + "st";
	}
	if (j == 2 && k != 12) {
		return i + "nd";
	}
	if (j == 3 && k != 13) {
		return i + "rd";
	}
	return i + "th";
}

function slideUpRows($tr, onComplete) {
	$tr.not('tr,table').finish().fadeOut('fast');

	$tr.filter('tr').find('td').finish().each(function(){
		$(this)
			.data("paddingstate", $(this).css(["paddingTop", "paddingBottom"]))
			.animate({ paddingTop: 0, paddingBottom: 0 }, { duration: 'fast' })
			.wrapInner('<div />')
			.children()
			.slideUp("fast", function() {
				$(this).contents().unwrap();
				$tr.filter('tr').hide();
				if ($.isFunction(onComplete)) {
					onComplete();
				}
			});
	});

	$tr.filter('table').finish().each(function(){
		$(this)
			.wrap('<div style="overflow: hidden"></div>')
			.parent()
			.slideUp("fast", function() {
				$(this).contents().unwrap();
				$tr.filter('table').hide();
				if ($.isFunction(onComplete)) {
					onComplete();
				}
			});
	});

	return $tr;
}

function slideDownRows($tr, onComplete) {
	$tr.filter(':hidden').not('tr,table').finish().fadeIn('fast');

	$tr.filter('tr:hidden').find('td').finish().each(function(){
		$(this)
			.wrapInner('<div style="display: none"></div>')
			.animate($(this).data("paddingstate"), { duration: 'fast', start: function() { $tr.filter('tr:hidden').show(); } })
			.children()
			.slideDown("fast", function() {
				$(this).contents().unwrap();
				if ($.isFunction(onComplete)) {
					onComplete();
				}
			});
	});

	$tr.filter('table:hidden').finish().each(function(){
		$(this)
			.wrap('<div style="display: none; overflow: hidden"></div>')
			.show()
			.parent()
			.slideDown("fast", function() {
				$(this).contents().unwrap();
				if ($.isFunction(onComplete)) {
					onComplete();
				}
			});
	});

	return $tr;
}

function toggleRows(what, val, what2, onComplete) {
	if (val == 1) {
		slideDownRows($('.'+what), onComplete);
		if (arguments.length > 2) {
			slideUpRows($('.'+what2), onComplete);
		}
	} else {
		slideUpRows($('.'+what), onComplete);
		if (arguments.length > 2) {
			slideDownRows($('.'+what2), onComplete);
		}
	}
}

function updatePrefixLabels(category) {
	$("#form_content table:data(category)").filter(function() {
		return $(this).data('category') == category;
	}).each(function (index) {
		var oldprefix = $(this).data('prefix');
		var newprefix = oldprefix;

		if (index > 0) {
			newprefix = ordinal_suffix_of(index+1);
		}

		$(this)
			.data('prefix', newprefix)
			.find('tr').each(function() {
				var $td = $(this).children('td').first();

				var old = $td.text();
				if (oldprefix && old.indexOf(oldprefix) === 0) {
					old = old.replace(oldprefix + ' ', '');
				}

				$td.text(newprefix + ' ' + old);
			});
	});
}

function bindSectionEvents(category) {
	var $Filtered = $("#form_content table:data(category)").filter(function(index) {
		return $(this).data('category') == category;
	});

	var count = $Filtered.length;

	$Filtered.each(function(index) {
		var $table = $(this);
		var config = $(this).data();
		var boolAdd = false;
		var boolDelete = false;

		if (!config.hasOwnProperty('multiple')) {
			return;
		}

		// Clean old sections
		var $first_td = $(this).find('td').first();

		// delete section
		if (!config.hasOwnProperty('minimum') || parseInt(config.minimum) < (index+1)) {
			if ($first_td.children('.sectionbutton.remove').length === 0) {
				var $el_remove = $('<div class="sectionbutton remove" title="Remove ' + config.prefix + ' ' + category.replace('_', ' ') + '"><i class="fa fa-minus-circle red"></i></div>').one('click', clickRemoveSection);
				$first_td.append($el_remove);
			}
			boolDelete = true;
		} else {
			$first_td.children('.sectionbutton.remove').fadeOut('fast', function() { $(this).remove(); });
		}

		// add section (can only add from the last section)
		if ((index+1) == count) {
			if (!config.hasOwnProperty('maximum') || parseInt(config.maximum) > (index+1)) {
				if ($first_td.children('.sectionbutton.add').length === 0) {
					var $el_add = $('<div class="sectionbutton add" title="Add another ' + category.replace('_', ' ') + '"><i class="fa fa-plus-circle green"></i></div>').one('click', clickAddSection);
					$first_td.append($el_add);
				}
				boolAdd = true;
			} else {
				$first_td.children('.sectionbutton.add').fadeOut('fast', function() { $(this).remove(); });
			}
		}

		if (boolDelete || boolAdd) {
			$table.addClass("multiple");
			if ($first_td.children('.sectiontab').length === 0) {
				var $el_tab = $('<div class="sectiontab"></div>');
				$first_td.append($el_tab);
			}
		} else {
			$first_td.children('.sectionbutton, .sectiontab').fadeOut('fast', function() {
				$(this).remove();
				$table.removeClass("multiple");
			});
		}
	});
}

function clickAddSection() {
	var $table = $(this).closest('table');
	$(this).remove();
	var newindex = new Date().getTime();
	var config = $table.data();

	var $template = $($('<div/>').loadTemplate($("#tmpl" + config.category)).html().replace(/{{INDEX}}/g, newindex));

	$template
		.data({
			multiple: true,
			category: config.category,
			index: newindex,
			minimum: config.minimum,
			maximum: config.maximum
		})
		.find('tr').hide()
		.find("input[data-pickroot]").fileTreeAttach();

	$table.after($template);

	updatePrefixLabels(config.category);
	bindSectionEvents(config.category);

	$el_showable = $template.find('tr').not("." + (isVMAdvancedMode() ? 'basic' : 'advanced'));

	slideDownRows($el_showable);
}

function clickRemoveSection() {
	var $table = $(this).closest('table');
	var category = $table.data('category');

	slideUpRows($table.find('tr'), function() {
		$table.remove();
		updatePrefixLabels(category);
		bindSectionEvents(category);
	});
}
