(function ($) {
	'use strict';

	if (typeof BFM_POST_MAINTENANCE === 'undefined') {
		return;
	}

	var currentPage = 1;
	var isLoading = false;
	var hasMore = true;
	var $status = $('#bfm-post-maintenance-status');
	var $loading = $('#bfm-post-maintenance-loading');
	var $body = $('#bfm-post-maintenance-body');
	var $loadMore = $('#bfm-post-maintenance-load-more');

	function setStatus(message, type) {
		if (!$status.length) {
			return;
		}
		$status.text(message || '');
		$status.removeClass('bfm-status-error bfm-status-success');
		if (type) {
			$status.addClass('bfm-status-' + type);
		}
	}

	function readFilterState() {
		return {
			title: $('#bfm-post-maintenance-title').val(),
			meta_key: $('#bfm-post-maintenance-meta-key').val(),
			value_status: $('#bfm-post-maintenance-value-status').val(),
			value: $('#bfm-post-maintenance-value').val(),
		};
	}

	function refreshChipControls() {
		if (typeof BFM_POST_EDITOR_CHIPS !== 'undefined' && typeof BFM_POST_EDITOR_CHIPS.initPage === 'function') {
			BFM_POST_EDITOR_CHIPS.initPage($body);
		}
	}

	function loadRows(reset) {
		if (isLoading) {
			return;
		}

		var filters = readFilterState();
		var requestedPage = reset ? 1 : currentPage + 1;
		isLoading = true;
		$loadMore.prop('disabled', true);
		if ($loading.length) {
			$loading.show();
		}
		if (reset) {
			setStatus('');
		}

		$.ajax({
			url: BFM_POST_MAINTENANCE.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: BFM_POST_MAINTENANCE.loadAction,
				nonce: BFM_POST_MAINTENANCE.nonce,
				page: requestedPage,
				title: filters.title,
				meta_key: filters.meta_key,
				value_status: filters.value_status,
				value: filters.value,
			},
			success: function (response) {
				if (!response || !response.success) {
					setStatus((response && response.data && response.data.message) || BFM_POST_MAINTENANCE.i18n.load_error || BFM_POST_MAINTENANCE.i18n.error, 'error');
					return;
				}

				if (reset) {
					$body.empty();
				}

				var rowsHtml = (response.data && response.data.rows) || '';
				if (!rowsHtml && reset) {
					setStatus(BFM_POST_MAINTENANCE.i18n.no_posts || 'No se encontraron posts.', '');
				} else {
					$body.append(rowsHtml);
					setStatus('');
				}

				currentPage = Number(response.data.page) || requestedPage;
				hasMore = !!response.data.has_more;
				if (hasMore) {
					$loadMore.show();
				} else {
					$loadMore.hide();
				}
				refreshChipControls();
			},
			error: function () {
				setStatus(BFM_POST_MAINTENANCE.i18n.load_error || 'No se pudieron cargar los posts.', 'error');
			},
			complete: function () {
				isLoading = false;
				if ($loading.length) {
					$loading.hide();
				}
				$loadMore.prop('disabled', false);
			}
		});
	}

	$('#bfm-post-maintenance-filter-form').on('submit', function (event) {
		event.preventDefault();
		currentPage = 1;
		hasMore = true;
		loadRows(true);
	});

	$('#bfm-post-maintenance-load-more').on('click', function () {
		if (!hasMore || isLoading) {
			return;
		}
		loadRows(false);
	});

	$(window).on('scroll', function () {
		if (!hasMore || isLoading) {
			return;
		}
		if ($(window).scrollTop() + $(window).height() > $(document).height() - 260) {
			loadRows(false);
		}
	});

	$(document).on('click', '.bfm-maintenance-save', function () {
		var $button = $(this);
		var $card = $button.closest('.bfm-post-card, .bfm-post-maintenance-row, tr');
		var postId = parseInt($card.data('post-id'), 10) || parseInt($button.data('post-id'), 10);
		var $cardStatus = $card.find('.bfm-card-save-status');
		var metaValues = {};

		$card.find('.bfm-card-field[data-meta-key], .bfm-maintenance-meta-cell[data-meta-key]').each(function () {
			var $field = $(this);
			var metaKey = $field.data('meta-key');
			if (!metaKey) {
				return;
			}
			var $input = $field.find('input[type="hidden"], input[type="text"]').first();
			if ($input.length) {
				metaValues[metaKey] = $input.val();
			}
		});

		if (!postId || $.isEmptyObject(metaValues)) {
			if ($cardStatus.length) {
				$cardStatus.text(BFM_POST_MAINTENANCE.i18n.error || 'Error').addClass('bfm-status-error');
			}
			return;
		}

		$button.prop('disabled', true).text(BFM_POST_MAINTENANCE.i18n.saving || 'Guardando...');
		$card.addClass('bfm-post-card-saving').removeClass('bfm-post-card-saved bfm-post-card-error');
		if ($cardStatus.length) {
			$cardStatus.text(BFM_POST_MAINTENANCE.i18n.saving || 'Guardando...').removeClass('bfm-status-error bfm-status-success');
		}

		$.ajax({
			url: BFM_POST_MAINTENANCE.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: BFM_POST_MAINTENANCE.saveAction,
				nonce: BFM_POST_MAINTENANCE.nonce,
				post_id: postId,
				meta_values: metaValues,
			},
			success: function (response) {
				if (!response || !response.success) {
					var errText = (response && response.data && response.data.message) || BFM_POST_MAINTENANCE.i18n.error;
					$card.removeClass('bfm-post-card-saving').addClass('bfm-post-card-error');
					$button.prop('disabled', false).text('Guardar');
					if ($cardStatus.length) {
						$cardStatus.text(errText).addClass('bfm-status-error');
					}
					return;
				}
				$card.removeClass('bfm-post-card-saving').addClass('bfm-post-card-saved');
				$button.text(BFM_POST_MAINTENANCE.i18n.saved || 'Guardado');
				if ($cardStatus.length) {
					$cardStatus.text(BFM_POST_MAINTENANCE.i18n.saved || 'Guardado').addClass('bfm-status-success');
				}
				window.setTimeout(function () {
					$card.removeClass('bfm-post-card-saved');
					$button.text('Guardar').prop('disabled', false);
					if ($cardStatus.length) {
						$cardStatus.text('').removeClass('bfm-status-success');
					}
				}, 1400);
			},
			error: function () {
				$card.removeClass('bfm-post-card-saving').addClass('bfm-post-card-error');
				$button.prop('disabled', false).text('Guardar');
				if ($cardStatus.length) {
					$cardStatus.text(BFM_POST_MAINTENANCE.i18n.error || 'Error').addClass('bfm-status-error');
				}
			},
		});
	});

	$(function () {
		refreshChipControls();
		if ($('#bfm-post-maintenance-body .bfm-post-card, #bfm-post-maintenance-body tr').length >= 20) {
			$loadMore.show();
		}
	});

	// ── Textarea modal management for bulk maintenance ───────────────────────
	var $textareaModal = $('#bfm-post-textarea-modal');
	var $textareaEditor = $('#bfm-post-textarea-editor');
	var $textareaModalTitle = $('#bfm-post-textarea-modal-title');
	var $textareaModalSubtitle = $('#bfm-post-textarea-modal-subtitle');
	var activeTextareaContext = null;

	function closeTextareaModal() {
		if ($textareaModal.length) {
			$textareaModal.prop('hidden', true);
		}
		if (activeTextareaContext && activeTextareaContext.$trigger) {
			activeTextareaContext.$trigger.trigger('focus');
		}
		activeTextareaContext = null;
	}

	$(document).on('click', '.bfm-card-textarea-open', function () {
		var $btn = $(this);
		var $compact = $btn.closest('.bfm-card-textarea-compact');
		var $hidden = $compact.find('.bfm-card-textarea-hidden');
		var $snippet = $compact.find('.bfm-card-textarea-snippet');
		var label = $btn.data('label') || '';
		var postTitle = $btn.data('post-title') || '';
		var currentValue = $hidden.val() || '';

		activeTextareaContext = {
			$hidden: $hidden,
			$snippet: $snippet,
			$trigger: $btn
		};

		$textareaModalTitle.text(label || 'Editar contenido');
		$textareaModalSubtitle.text(postTitle);
		$textareaEditor.val(currentValue);
		$textareaModal.prop('hidden', false);
		$textareaEditor.trigger('focus');
	});

	$('#bfm-post-textarea-confirm').on('click', function () {
		if (!activeTextareaContext) {
			return;
		}
		var newVal = $textareaEditor.val();
		activeTextareaContext.$hidden.val(newVal);

		var preview = $.trim(newVal.replace(/\s+/g, ' '));
		if (preview.length > 0) {
			var display = preview.length > 45 ? preview.substring(0, 42) + '...' : preview;
			activeTextareaContext.$snippet.text(display).attr('title', newVal);
		} else {
			activeTextareaContext.$snippet.html('<span class="bfm-card-textarea-empty">(Vacío)</span>').removeAttr('title');
		}

		closeTextareaModal();
	});

	$('#bfm-post-textarea-cancel').on('click', function () {
		closeTextareaModal();
	});

	$textareaModal.on('click', function (e) {
		if (e.target === this) {
			closeTextareaModal();
		}
	});

	$(document).on('keydown', function (e) {
		if (27 === e.which && $textareaModal.length && !$textareaModal.prop('hidden')) {
			closeTextareaModal();
		}
	});
})(jQuery);
