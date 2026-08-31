/**
 * Bunny Figure Metadata — Post editor chip fields
 *
 * Turns the Manufacturer/Illustrator/Series/Character metabox fields
 * into chip lists, backed by a hidden input that keeps the same
 * "A & B" plain-text storage format the server already expects — no
 * change to how BFM_Post_Editor::save_metadata() reads $_POST.
 *
 * Existing entity values are preloaded once per field via
 * BFM_POST_EDITOR_CHIPS.suggestions, sourced from BFM_Entity_Repository
 * (the same data used by the entity admin screens), not from WordPress
 * tags. Exact matches reuse the canonical text; sufficiently similar
 * values are shown as suggestions without being replaced automatically.
 *
 * Pending (typed-but-not-yet-chipped) text is committed automatically
 * on Enter, Tab, blur, and — as a last-resort safety net — right before
 * the post is actually saved/published, so a value never silently fails
 * to reach the metadata just because the user didn't explicitly press
 * Enter.
 *
 * @package BunnyFigureMetadata
 */

(function ($) {
	'use strict';

	if (typeof BFM_POST_EDITOR_CHIPS === 'undefined') {
		return;
	}

	window.BFM_POST_EDITOR_CHIPS = window.BFM_POST_EDITOR_CHIPS || {};
	window.BFM_POST_EDITOR_CHIPS.initField = window.BFM_POST_EDITOR_CHIPS.initField || function (field) {
		if (!field || $(field).data('bfm-chip-bound')) {
			return;
		}
		$(field).data('bfm-chip-bound', true);
		initField.call(field);
	};
	window.BFM_POST_EDITOR_CHIPS.initPage = function ($context) {
		var $scope = $context || $(document);
		$scope.find('.bfm-figure-chip-field').each(function () {
			if (!$(this).data('bfm-chip-bound')) {
				$(this).data('bfm-chip-bound', true);
				initField.call(this);
			}
		});
	};

	// Committers for every chip field on the page, so a page-level "about
	// to save" safety net can flush all of them at once.
	var pendingCommitters = [];

	function isExcluded(val) {
		var str = String(typeof val === 'string' ? val : (val && val.name ? val.name : (val || ''))).trim().toLowerCase();
		return !str || str === 'no dice';
	}

	function parseValues(raw) {
		return String(raw || '')
			.split('&')
			.map(function (part) { return $.trim(part); })
			.filter(function (part) { return part.length > 0 && !isExcluded(part); });
	}

	function serializeValues(values) {
		return values
			.filter(function (value) { return value && !isExcluded(value.name); })
			.map(function (value) { return String(value.name || '').trim(); })
			.filter(function (name) { return name.length > 0; })
			.join(' & ');
	}

	function normalizeValue(value) {
		var str = String(typeof value === 'string' ? value : (value && value.name ? value.name : (value || '')));
		return str
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9]+/g, ' ')
			.trim()
			.replace(/\s+/g, ' ');
	}

	function wordKey(value) {
		return normalizeValue(value).split(' ').sort().join(' ');
	}

	function editDistance(first, second) {
		var previous = [];
		var current = [];
		var i;
		var j;

		for (j = 0; j <= second.length; j++) {
			previous[j] = j;
		}

		for (i = 1; i <= first.length; i++) {
			current[0] = i;

			for (j = 1; j <= second.length; j++) {
				current[j] = Math.min(
					current[j - 1] + 1,
					previous[j] + 1,
					previous[j - 1] + (first[i - 1] === second[j - 1] ? 0 : 1)
				);
			}

			previous = current;
			current = [];
		}

		return previous[second.length];
	}

	function similarity(first, second) {
		var normalizedFirst = normalizeValue(first);
		var normalizedSecond = normalizeValue(second);
		var wordsFirst = normalizedFirst.split(' ');
		var wordsSecond = normalizedSecond.split(' ');
		var shorter;
		var longer;
		var sharedWord;
		var distance;

		if (!normalizedFirst || !normalizedSecond) {
			return 0;
		}

		if (normalizedFirst === normalizedSecond || wordKey(first) === wordKey(second)) {
			return 1;
		}

		shorter = normalizedFirst.length <= normalizedSecond.length ? normalizedFirst : normalizedSecond;
		longer = normalizedFirst.length <= normalizedSecond.length ? normalizedSecond : normalizedFirst;

		if (shorter.length >= 4 && wordsFirst.length <= 3 && wordsSecond.length <= 3) {
			sharedWord = wordsFirst.some(function (word) {
				return word.length >= 4 && wordsSecond.indexOf(word) !== -1;
			});

			if (sharedWord && shorter.split(' ').length === 1) {
				return 0.84;
			}
		}

		distance = editDistance(normalizedFirst, normalizedSecond);
		return Math.max(0, 1 - (distance / Math.max(normalizedFirst.length, normalizedSecond.length)));
	}

	function findPossibleMatch(name, catalog) {
		var best = null;
		var bestScore = 0;
		var target = String(name || '');

		catalog.forEach(function (candidate) {
			var candStr = String(candidate || '');
			if (isExcluded(candStr)) {
				return;
			}
			var score = similarity(target, candStr);

			if (score >= 0.82 && score > bestScore) {
				best = candStr;
				bestScore = score;
			}
		});

		return best;
	}

	function initField() {
		var $field = $(this);
		var metaKey = $field.data('meta-key');
		var $hidden = $field.find('input[type="hidden"]');
		var $list = $field.find('.bfm-figure-chip-list');
		var $input = $field.find('.bfm-figure-chip-input');

		var rawSuggestions = (BFM_POST_EDITOR_CHIPS.suggestions && BFM_POST_EDITOR_CHIPS.suggestions[metaKey]) || [];
		var suggestions = Array.isArray(rawSuggestions)
			? rawSuggestions.map(function (s) { return String(s || ''); }).filter(function (s) { return !isExcluded(s); })
			: [];
		var catalog = suggestions.slice();
		var values = [];

		function containsValue(name) {
			var needle = String(typeof name === 'string' ? name : (name && name.name ? name.name : (name || ''))).toLowerCase();
			return values.some(function (value) {
				var valName = String(typeof value === 'string' ? value : (value && value.name ? value.name : (value || ''))).toLowerCase();
				return valName === needle;
			});
		}

		function render() {
			$list.empty();

			$.each(values, function (index, value) {
				if (!value || isExcluded(value.name)) {
					return;
				}
				var $chip = $('<span class="bfm-figure-chip"></span>')
					.addClass('bfm-figure-chip-' + value.state)
					.attr('title', value.possible ? 'Posible coincidencia: ' + value.possible : '');
				$chip.append($('<span class="bfm-figure-chip-name"></span>').text(value.name));

				if ('possible' === value.state) {
					var $accept = $('<button type="button" class="bfm-figure-chip-action bfm-figure-chip-accept">&#10003;</button>')
						.attr('aria-label', 'Aceptar coincidencia: ' + value.possible)
						.attr('title', 'Aceptar coincidencia: ' + value.possible)
						.on('click', function () {
							value.name = value.possible;
							value.state = 'existing';
							value.possible = null;
							render();
						});
					var $reject = $('<button type="button" class="bfm-figure-chip-action bfm-figure-chip-reject">&#8617;</button>')
						.attr('aria-label', 'Rechazar coincidencia: ' + value.possible)
						.attr('title', 'Rechazar coincidencia')
						.on('click', function () {
							value.state = 'new';
							value.possible = null;
							render();
						});
					$chip.append($accept, $reject);
				}

				var $remove = $('<button type="button" class="bfm-figure-chip-remove">&times;</button>')
					.attr('aria-label', BFM_POST_EDITOR_CHIPS.i18n.remove + ': ' + value.name)
					.attr('title', BFM_POST_EDITOR_CHIPS.i18n.remove + ': ' + value.name);

				$remove.on('click', function () {
					values.splice(index, 1);
					render();
				});

				$chip.append($remove);
				$list.append($chip);
			});

			$hidden.val(serializeValues(values));
		}

		function addValue(rawName) {
			var name = $.trim(typeof rawName === 'string' ? rawName : (rawName && rawName.name ? rawName.name : String(rawName || '')));
			var existing = null;
			var possible = null;

			if (!name || isExcluded(name)) {
				return;
			}

			for (var i = 0; i < catalog.length; i++) {
				var cand = String(catalog[i] || '');
				if (normalizeValue(cand) === normalizeValue(name)) {
					existing = cand;
					break;
				}
			}

			if (!existing) {
				possible = findPossibleMatch(name, catalog);
			}

			var chosen = existing || name;
			if (!containsValue(chosen)) {
				values.push({
					name: chosen,
					state: existing ? 'existing' : (possible ? 'possible' : 'new'),
					possible: possible
				});

				if (!existing) {
					suggestions.push(chosen);
				}

				render();
			}

			$input.val('');
		}

		/**
		 * Commit whatever text is currently typed (but not yet turned
		 * into a chip) as a value, if any. Safe to call repeatedly —
		 * it's a no-op once the input is empty.
		 */
		function commitPending() {
			parseValues($input.val()).forEach(addValue);
		}

		$input.on('keydown', function (e) {
			var isEnter = 'Enter' === e.key || 13 === e.keyCode;
			var isTab = 'Tab' === e.key || 9 === e.keyCode;

			if (!isEnter && !isTab) {
				return;
			}

			if (isEnter) {
				// Prevent a stray form submit / newline; Tab's default
				// focus-navigation is intentionally left alone so tabbing
				// through fields keeps working normally.
				e.preventDefault();
			}

			commitPending();
		});

		$input.on('blur', function () {
			commitPending();
		});

		pendingCommitters.push(commitPending);

		parseValues($hidden.val()).forEach(addValue);
		render();
	}

	function commitAllPending() {
		pendingCommitters.forEach(function (commit) { commit(); });
	}

	$(function () {
		$('.bfm-figure-chip-field').each(initField);

		// Last-resort safety net: flush any pending text right before the
		// post is actually saved. #post is the real underlying form for
		// both the classic editor and the block editor (Gutenberg submits
		// classic meta boxes like this one through that same form via its
		// meta-box-compat mechanism), so hooking its submit event covers
		// both. The button-level mousedown handlers below fire even
		// earlier, before the editor's own click handling runs.
		$('#post').on('submit', commitAllPending);

		$(document).on(
			'mousedown',
			'#publish, .editor-post-publish-button__button, .editor-post-save-draft, .editor-post-publish-panel__toggle',
			commitAllPending
		);
	});

})(jQuery);
