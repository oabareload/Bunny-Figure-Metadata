/**
 * Bunny Figure Metadata - global metadata settings.
 */
(function ($) {
	'use strict';

	var prefix = '_bunny_figure_';
	var $modal = $('#bfm-metadata-modal');
	var $label = $('#bfm-new-metadata-label');
	var $error = $('#bfm-new-metadata-error');
	var rowIndex = $('#bfm-metadata-rows tbody tr').length;

	function slugify(value) {
		var aliases = {
			'fabricante': 'manufacturer',
			'ilustrador': 'illustrator',
			'serie': 'series',
			'personaje': 'character'
		};
		var normalized = value.toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
		return aliases[normalized] || normalized;
	}

	function metadataKeyExists(metaKey) {
		var exists = false;
		$('#bfm-metadata-rows input[name$="[meta_key]"]').each(function () {
			if ($(this).val() === metaKey) {
				exists = true;
			}
		});
		return exists;
	}

	function inputName(key) {
		return 'bfm_metadata_fields[' + rowIndex + '][' + key + ']';
	}

	function addRow(label, metaKey) {
		var $row = $('<tr>');
		$row.append($('<td>').append($('<code>').text(metaKey)).append($('<input>', {
			type: 'hidden', name: inputName('meta_key'), value: metaKey
		})));
		$row.append($('<td>').append($('<input>', {
			type: 'text', class: 'regular-text', name: inputName('label'), value: label
		})));
		$row.append($('<td>').append($('<select>', { name: inputName('type') })
			.append($('<option>', { value: 'text', text: 'text', selected: true }))
			.append($('<option>', { value: 'number', text: 'number' }))
			.append($('<option>', { value: 'currency', text: 'currency' }))
			.append($('<option>', { value: 'month', text: 'month' }))
			.append($('<option>', { value: 'textarea', text: 'textarea' }))));
		$row.append($('<td>').append($('<label>').append($('<input>', {
			type: 'checkbox', name: inputName('searchable'), value: '1'
		})).append(' Incluir este metadato en las búsquedas de WordPress')));
		$row.append($('<td>').append($('<label>').append($('<input>', {
			type: 'checkbox', name: inputName('entity'), value: '1'
		})).append(' Usar entidad')));
		$row.find('input[name$="[entity]"]').addClass('bfm-catalog-toggle');
		var $checks = $('<div>', { class: 'bfm-catalog-checks' })
			.append($('<label>', { class: 'bfm-inline-check' }).append($('<input>', { type: 'checkbox', name: inputName('tag'), value: '1' })).append(' Asociar a TAG'))
			.append($('<label>', { class: 'bfm-inline-check' }).append($('<input>', { type: 'checkbox', name: inputName('url'), value: '1' })).append(' Permitir URL externa'))
			.append($('<label>', { class: 'bfm-inline-check' }).append($('<input>', { type: 'checkbox', class: 'bfm-logo-toggle', name: inputName('logo'), value: '1' })).append(' Usar logo'));
		var $imageSourceRow = $('<div>', { class: 'bfm-image-source-row', hidden: true })
			.append($('<label>', { class: 'bfm-inline-select' })
				.append($('<span>').text('Origen de imagen'))
				.append($('<select>', { class: 'bfm-image-source-select', name: inputName('image_source') })
					.append($('<option>', { value: 'custom', text: 'Imagen personalizada', selected: true }))
					.append($('<option>', { value: 'related', text: 'Post relacionado' }))));
		$row.append($('<td>').append($('<div>', { class: 'bfm-catalog-integrations', hidden: true }).append($checks).append($imageSourceRow)));
		$('#bfm-metadata-rows tbody').append($row);
		rowIndex += 1;
	}

	$(document).on('change', '.bfm-catalog-toggle', function () {
		$(this).closest('tr').find('.bfm-catalog-integrations').prop('hidden', !this.checked);
	});

	$(document).on('change', '.bfm-logo-toggle', function () {
		$(this).closest('.bfm-catalog-integrations').find('.bfm-image-source-row').prop('hidden', !this.checked);
	});

	function closeModal() {
		$modal.prop('hidden', true);
		$error.text('');
		$label.val('');
	}

	$('#bfm-add-metadata').on('click', function () {
		$error.text('');
		$label.val('').trigger('focus');
		$modal.prop('hidden', false);
	});

	$('#bfm-cancel-metadata').on('click', closeModal);

	$('#bfm-confirm-metadata').on('click', function () {
		var visibleName = $.trim($label.val());
		var slug = slugify(visibleName);
		var metaKey = prefix + slug;

		if (!visibleName || !slug) {
			$error.text('Introduce un nombre visible válido.');
			return;
		}
		if (metadataKeyExists(metaKey)) {
			$error.text('Ese nombre ya corresponde a un metadata existente.');
			return;
		}

		addRow(visibleName, metaKey);
		closeModal();
	});

	$label.on('keydown', function (event) {
		if ('Enter' === event.key) {
			e.preventDefault();
			$('#bfm-confirm-metadata').trigger('click');
		}
		if ('Escape' === event.key) {
			closeModal();
		}
	});
})(jQuery);
