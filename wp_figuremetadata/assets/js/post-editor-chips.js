/**
 * Bunny Figure Metadata — Post editor chip fields
 *
 * Turns the Manufacturer/Illustrator/Series/Character metabox fields
 * into chip lists, backed by a hidden input that keeps the same
 * "A & B" plain-text storage format the server already expects — no
 * change to how BFM_Post_Editor::save_metadata() reads $_POST.
 *
 * Autocomplete suggestions come from existing administered entity
 * values (preloaded once per field via BFM_POST_EDITOR_CHIPS.suggestions,
 * itself sourced from BFM_Entity_Repository — the same data used by the
 * entity admin screens), not from WordPress tags. If a typed value
 * matches an existing suggestion case-insensitively, that suggestion's
 * exact text is reused; otherwise the typed text is added as a new
 * entity value.
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

	// Committers for every chip field on the page, so a page-level "about
	// to save" safety net can flush all of them at once.
	var pendingCommitters = [];

	function parseValues(raw) {
		return String(raw || '')
			.split('&')
			.map(function (part) { return $.trim(part); })
			.filter(function (part) { return part.length > 0; });
	}

	function serializeValues(values) {
		return values.join(' & ');
	}

	function initField() {
		var $field = $(this);
		var metaKey = $field.data('meta-key');
		var $hidden = $field.find('input[type="hidden"]');
		var $list = $field.find('.bfm-figure-chip-list');
		var $input = $field.find('.bfm-figure-chip-input');

		var suggestions = (BFM_POST_EDITOR_CHIPS.suggestions && BFM_POST_EDITOR_CHIPS.suggestions[metaKey]) || [];
		var values = parseValues($hidden.val());

		function containsValue(name) {
			var needle = name.toLowerCase();
			return values.some(function (v) { return v.toLowerCase() === needle; });
		}

		function render() {
			$list.empty();

			$.each(values, function (index, name) {
				var $chip = $('<span class="bfm-figure-chip"></span>').text(name);
				var $remove = $('<button type="button" class="bfm-figure-chip-remove">&times;</button>')
					.attr('aria-label', BFM_POST_EDITOR_CHIPS.i18n.remove + ': ' + name);

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
			var name = $.trim(rawName);

			if (!name) {
				return;
			}

			// Reuse the exact existing text if this matches a known entity
			// value case-insensitively; otherwise it's a brand new value.
			var existing = null;
			for (var i = 0; i < suggestions.length; i++) {
				if (suggestions[i].toLowerCase() === name.toLowerCase()) {
					existing = suggestions[i];
					break;
				}
			}

			var finalName = existing || name;

			if (!containsValue(finalName)) {
				values.push(finalName);

				if (!existing) {
					// Make the new value immediately reusable for further
					// chips typed into this same field.
					suggestions.push(finalName);
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
			var raw = $.trim($input.val());

			if (raw) {
				addValue(raw);
			}
		}

		$input.autocomplete({
			minLength: 1,
			source: function (request, response) {
				var term = request.term.toLowerCase();

				var matches = $.grep(suggestions, function (name) {
					return name.toLowerCase().indexOf(term) !== -1 && !containsValue(name);
				});

				response(matches.slice(0, 20));
			},
			select: function (event, ui) {
				event.preventDefault();
				addValue(ui.item.value);
			}
		});

		$input.on('keydown', function (e) {
			var isEnter = 'Enter' === e.key || 13 === e.keyCode;
			var isTab = 'Tab' === e.key || 9 === e.keyCode;

			if (!isEnter && !isTab) {
				return;
			}

			var instance = $input.autocomplete('instance');
			var hasActiveSuggestion = instance && instance.menu && instance.menu.active;

			if (hasActiveSuggestion) {
				// Let jQuery UI's own handling apply the highlighted
				// suggestion (both Enter and Tab do this natively); for
				// Tab, default focus-navigation still proceeds afterward.
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
