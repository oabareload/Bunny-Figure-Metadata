/**
 * Bunny Figure Metadata — Entity complementary info (URL + Logo)
 *
 * Handles the "URL" and "Logo" columns in the entity admin screens
 * (Fabricantes / Ilustradores / Series / Personajes):
 *   - URL: a plain text input that saves via AJAX on blur, only when
 *     changed. No Save button.
 *   - Logo: uses the WordPress Media Library (wp.media), behaving like
 *     the core Featured Image control — pick an image, see an instant
 *     preview, then Reemplazar/Eliminar over the image.
 *
 * Expects a localized `BFM_ENTITY_INFO` object with: ajaxUrl, nonce,
 * setUrlAction, setImageAction, removeImageAction, i18n.
 *
 * @package BunnyFigureMetadata
 */

(function ($) {
	'use strict';

	if (typeof BFM_ENTITY_INFO === 'undefined') {
		return;
	}

	/* ==========================================================================
	   Shared helpers
	   ========================================================================== */

	function showCellError($cell, message) {
		$cell.find('.bfm-tag-error').remove();
		var $error = $('<span class="bfm-tag-error"></span>').text(message);
		$cell.append($error);
		setTimeout(function () {
			$error.fadeOut(200, function () {
				$(this).remove();
			});
		}, 4000);
	}

	/* ==========================================================================
	   URL — autosave on blur
	   ========================================================================== */

	$(document).on('blur', '.bfm-url-input', function () {
		var $input = $(this);
		var $cell = $input.closest('.bfm-url-cell');
		var newValue = $.trim($input.val());
		var savedValue = $cell.data('saved-value') || '';

		if (newValue === savedValue) {
			return;
		}

		$input.prop('disabled', true);

		$.ajax({
			url: BFM_ENTITY_INFO.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: BFM_ENTITY_INFO.setUrlAction,
				nonce: BFM_ENTITY_INFO.nonce,
				entity_slug: $cell.data('entity-slug'),
				value: String($cell.data('value')),
				url: newValue
			}
		})
			.done(function (response) {
				$input.prop('disabled', false);

				if (response && response.success) {
					$cell.data('saved-value', response.data.url);
					$input.val(response.data.url);
				} else {
					$input.val(savedValue);
					showCellError($cell, (response && response.data && response.data.message) || BFM_ENTITY_INFO.i18n.error);
				}
			})
			.fail(function () {
				$input.prop('disabled', false);
				$input.val(savedValue);
				showCellError($cell, BFM_ENTITY_INFO.i18n.error);
			});
	});

	/* ==========================================================================
	   Logo — Media Library (Featured-Image-style)
	   ========================================================================== */

	function renderLogoSaving($cell) {
		$cell.empty().append(
			$('<span class="bfm-tag-saving"></span>').text(BFM_ENTITY_INFO.i18n.saving)
		);
	}

	function renderLogoPlaceholder($cell) {
		$cell.empty().append(
			$('<button type="button" class="button bfm-logo-select"></button>').text(BFM_ENTITY_INFO.i18n.upload)
		);
	}

	function renderLogoPreview($cell, imageUrl) {
		var $preview = $('<span class="bfm-logo-preview"></span>');

		$preview.append($('<img>').attr('src', imageUrl).attr('alt', ''));

		var $actions = $('<span class="bfm-logo-actions"></span>');
		$actions.append(
			$('<button type="button" class="button-link bfm-logo-replace"></button>').text(BFM_ENTITY_INFO.i18n.replace)
		);
		$actions.append(
			$('<button type="button" class="button-link bfm-logo-remove"></button>').text(BFM_ENTITY_INFO.i18n.remove)
		);
		$preview.append($actions);

		$cell.empty().append($preview);
	}

	function saveImage($cell, attachmentId, previewUrl) {
		renderLogoSaving($cell);

		$.ajax({
			url: BFM_ENTITY_INFO.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: BFM_ENTITY_INFO.setImageAction,
				nonce: BFM_ENTITY_INFO.nonce,
				entity_slug: $cell.data('entity-slug'),
				value: String($cell.data('value')),
				attachment_id: attachmentId
			}
		})
			.done(function (response) {
				if (response && response.success) {
					renderLogoPreview($cell, response.data.thumbnail || previewUrl);
				} else {
					renderLogoPlaceholder($cell);
					showCellError($cell, (response && response.data && response.data.message) || BFM_ENTITY_INFO.i18n.error);
				}
			})
			.fail(function () {
				renderLogoPlaceholder($cell);
				showCellError($cell, BFM_ENTITY_INFO.i18n.error);
			});
	}

	function removeImage($cell) {
		renderLogoSaving($cell);

		$.ajax({
			url: BFM_ENTITY_INFO.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: BFM_ENTITY_INFO.removeImageAction,
				nonce: BFM_ENTITY_INFO.nonce,
				entity_slug: $cell.data('entity-slug'),
				value: String($cell.data('value'))
			}
		})
			.done(function (response) {
				renderLogoPlaceholder($cell);
				if (!response || !response.success) {
					showCellError($cell, (response && response.data && response.data.message) || BFM_ENTITY_INFO.i18n.error);
				}
			})
			.fail(function () {
				renderLogoPlaceholder($cell);
				showCellError($cell, BFM_ENTITY_INFO.i18n.error);
			});
	}

	function openMediaFrame($cell) {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}

		var frame = wp.media({
			title: BFM_ENTITY_INFO.i18n.selectImage,
			button: { text: BFM_ENTITY_INFO.i18n.useImage },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var previewUrl = (attachment.sizes && attachment.sizes.thumbnail)
				? attachment.sizes.thumbnail.url
				: attachment.url;

			saveImage($cell, attachment.id, previewUrl);
		});

		frame.open();
	}

	$(document).on('click', '.bfm-logo-select, .bfm-logo-replace', function (e) {
		e.preventDefault();
		openMediaFrame($(this).closest('.bfm-logo-cell'));
	});

	$(document).on('click', '.bfm-logo-remove', function (e) {
		e.preventDefault();
		removeImage($(this).closest('.bfm-logo-cell'));
	});

})(jQuery);
