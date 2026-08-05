/**
 * Bunny Figure Metadata — Entity TAG association
 *
 * Handles the "TAG asociado" column in the entity admin screens
 * (Fabricantes / Ilustradores / Series / Personajes):
 *   - turns the "Seleccionar TAG..." trigger into an autocomplete input;
 *   - saves the association via AJAX as soon as a tag is picked;
 *   - removes the association via AJAX when the chip's "×" is clicked;
 *   - no Save button: every change is persisted immediately.
 *
 * Expects a localized `BFM_ENTITY_TAG` object with: ajaxUrl, nonce,
 * searchAction, setAction, removeAction, i18n.
 *
 * @package BunnyFigureMetadata
 */

(function ($) {
	'use strict';

	if (typeof BFM_ENTITY_TAG === 'undefined') {
		return;
	}

	function selectorMarkup() {
		return $('<button type="button" class="button-link bfm-tag-select-trigger"></button>')
			.text(BFM_ENTITY_TAG.i18n.select);
	}

	function chipMarkup(termId, name) {
		var $chip = $('<span class="bfm-tag-chip"></span>').attr('data-term-id', termId);

		$chip.append($('<span class="bfm-tag-chip-name"></span>').text(name));
		$chip.append(
			$('<button type="button" class="bfm-tag-remove"></button>')
				.attr('aria-label', BFM_ENTITY_TAG.i18n.remove)
				.text('\u00D7')
		);

		return $chip;
	}

	function renderSelector($cell) {
		$cell.empty().append(selectorMarkup());
	}

	function renderChip($cell, termId, name) {
		$cell.empty().append(chipMarkup(termId, name));
	}

	function showError($cell, message) {
		$cell.find('.bfm-tag-error').remove();
		var $error = $('<span class="bfm-tag-error"></span>').text(message);
		$cell.append($error);
		setTimeout(function () {
			$error.fadeOut(200, function () {
				$(this).remove();
			});
		}, 4000);
	}

	/**
	 * Turn the trigger into a text input with autocomplete against the
	 * plugin's own search endpoint (returns term_id + name as JSON).
	 */
	function activateInput($cell) {
		var entitySlug = $cell.data('entity-slug');
		var value = String($cell.data('value'));

		var $input = $('<input type="text" class="bfm-tag-input" autocomplete="off">');

		$cell.empty().append($input);
		$input.trigger('focus');

		$input.autocomplete({
			minLength: 2,
			source: function (request, response) {
				$.ajax({
					url: BFM_ENTITY_TAG.ajaxUrl,
					method: 'POST',
					dataType: 'json',
					data: {
						action: BFM_ENTITY_TAG.searchAction,
						nonce: BFM_ENTITY_TAG.nonce,
						q: request.term
					}
				})
					.done(function (result) {
						var items = (result && result.success && result.data) ? result.data : [];

						response(
							$.map(items, function (item) {
								return { label: item.name, value: item.name, id: item.id };
							})
						);
					})
					.fail(function () {
						response([]);
					});
			},
			select: function (event, ui) {
				event.preventDefault();
				saveTag($cell, entitySlug, value, ui.item.id, ui.item.value);
			}
		});

		$input.on('blur', function () {
			setTimeout(function () {
				if ($cell.find('input.bfm-tag-input').length) {
					renderSelector($cell);
				}
			}, 150);
		});
	}

	/**
	 * Persist the association immediately after a tag is picked.
	 */
	function saveTag($cell, entitySlug, value, termId, tagName) {

		$cell.empty().append(
			$('<span class="bfm-tag-saving"></span>').text(BFM_ENTITY_TAG.i18n.saving)
		);

		$.ajax({
			url: BFM_ENTITY_TAG.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: BFM_ENTITY_TAG.setAction,
				nonce: BFM_ENTITY_TAG.nonce,
				entity_slug: entitySlug,
				value: value,
				term_id: termId
			}
		})
			.done(function (response) {
				if (response && response.success) {
					renderChip($cell, response.data.term_id, response.data.name);
				} else {
					renderSelector($cell);
					showError($cell, (response && response.data && response.data.message) || BFM_ENTITY_TAG.i18n.error);
				}
			})
			.fail(function () {
				renderSelector($cell);
				showError($cell, BFM_ENTITY_TAG.i18n.error);
			});
	}

	/**
	 * Remove the association for a cell.
	 */
	function removeTag($cell) {
		var entitySlug = $cell.data('entity-slug');
		var value = String($cell.data('value'));

		$cell.empty().append(
			$('<span class="bfm-tag-saving"></span>').text(BFM_ENTITY_TAG.i18n.removing)
		);

		$.ajax({
			url: BFM_ENTITY_TAG.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: BFM_ENTITY_TAG.removeAction,
				nonce: BFM_ENTITY_TAG.nonce,
				entity_slug: entitySlug,
				value: value
			}
		})
			.done(function (response) {
				renderSelector($cell);
				if (!response || !response.success) {
					showError($cell, (response && response.data && response.data.message) || BFM_ENTITY_TAG.i18n.error);
				}
			})
			.fail(function () {
				renderSelector($cell);
				showError($cell, BFM_ENTITY_TAG.i18n.error);
			});
	}

	$(document).on('click', '.bfm-tag-select-trigger', function () {
		activateInput($(this).closest('.bfm-tag-cell'));
	});

	$(document).on('click', '.bfm-tag-remove', function () {
		removeTag($(this).closest('.bfm-tag-cell'));
	});

})(jQuery);
