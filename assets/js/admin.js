(function ($) {
	'use strict';

	$(function () {
		var frame;

		function showNotices(html) {
			var $notices = $('.nexisettings-notices');

			if (!$notices.length) {
				return;
			}

			$notices.html(html || '');
		}

		function toggleCustomBlockUrlField() {
			var $select = $('select[name="nexisettings_options[login_block_action]"]');
			var $field = $('.nexisettings-custom-block-url-field');
			var isCustom = $select.val() === 'custom_url';

			$field.toggleClass('is-hidden', !isCustom);
			$field.find('input').prop('required', isCustom);
		}

		function setButtonState($form, isSaving) {
			var $buttons = $form.find(':submit');
			var $primaryButton = $form.data('clicked-submit') ? $($form.data('clicked-submit')) : $buttons.first();

			$buttons.prop('disabled', isSaving);

			if (!$primaryButton.length) {
				return;
			}

			if (isSaving) {
				if (typeof $primaryButton.data('original-label') === 'undefined') {
					$primaryButton.data('original-label', $primaryButton.is('input') ? $primaryButton.val() : $primaryButton.text());
				}

				if ($primaryButton.is('input')) {
					$primaryButton.val(nexiSettingsAdmin.saving);
				} else {
					$primaryButton.text(nexiSettingsAdmin.saving);
				}
			} else if (typeof $primaryButton.data('original-label') !== 'undefined') {
				if ($primaryButton.is('input')) {
					$primaryButton.val($primaryButton.data('original-label'));
				} else {
					$primaryButton.text($primaryButton.data('original-label'));
				}
			}
		}

		function getSerializedFormData($form) {
			var data = $form.serializeArray();
			var clickedSubmit = $form.data('clicked-submit');

			if (clickedSubmit && clickedSubmit.name && !clickedSubmit.disabled) {
				data.push({
					name: clickedSubmit.name,
					value: clickedSubmit.value || '1'
				});
			}

			return $.param(data);
		}

		function syncOptions(options, responseData) {
			if (!options) {
				return;
			}

			$.each(options, function (key, value) {
				var $fields = $('[name="nexisettings_options[' + key + ']"]');
				var colorDefaults = {
					login_background_color: '#f0f0f1',
					login_text_color: '#3c434a',
					login_link_color: '#2271b1'
				};

				if (!$fields.length) {
					return;
				}

				if ($fields.first().is(':checkbox')) {
					$fields.prop('checked', value === true || value === 1 || value === '1');
					return;
				}

				if ($fields.first().is('[type="color"]') && !value && colorDefaults[key]) {
					value = colorDefaults[key];
				}

				$fields.val(value);
			});

			if (responseData && typeof responseData.currentLoginHtml !== 'undefined') {
				$('.nexisettings-current-login-wrap').html(responseData.currentLoginHtml);
			}

			if (responseData && typeof responseData.logoUrl !== 'undefined') {
				if (responseData.logoUrl) {
					$('.nexisettings-logo-preview')
						.removeClass('is-empty')
						.empty()
						.append($('<img />', {
							src: responseData.logoUrl,
							alt: ''
						}));
				} else {
					$('.nexisettings-logo-preview')
						.addClass('is-empty')
						.html('<span>' + nexiSettingsAdmin.noLogo + '</span>');
				}
			}

			toggleCustomBlockUrlField();
		}

		$(document).on('click', '.nexisettings-form :submit', function () {
			$(this).closest('form').data('clicked-submit', this);
		});

		$('.nexisettings-options-form').on('submit', function (event) {
			var $form = $(this);
			var data;

			event.preventDefault();

			data = getSerializedFormData($form) + '&action=nexisettings_save_options&nonce=' + encodeURIComponent(nexiSettingsAdmin.nonce);
			setButtonState($form, true);

			$.post(nexiSettingsAdmin.ajaxUrl, data)
				.done(function (response) {
					if (!response || !response.success) {
						showNotices(response && response.data && response.data.notices ? response.data.notices : '<div class="notice notice-error nexisettings-notice"><p>' + nexiSettingsAdmin.saveFailed + '</p></div>');
						return;
					}

					showNotices(response.data.notices);
					syncOptions(response.data.options, response.data);
				})
				.fail(function (xhr) {
					var notices = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.notices ? xhr.responseJSON.data.notices : '<div class="notice notice-error nexisettings-notice"><p>' + nexiSettingsAdmin.ajaxError + '</p></div>';
					showNotices(notices);
				})
				.always(function () {
					setButtonState($form, false);
					$form.removeData('clicked-submit');
				});
		});

		$('.nexisettings-redirects-form').on('submit', function (event) {
			var $form = $(this);
			var data;

			event.preventDefault();

			data = getSerializedFormData($form) + '&action=nexisettings_save_redirects&nonce=' + encodeURIComponent(nexiSettingsAdmin.nonce);
			setButtonState($form, true);

			$.post(nexiSettingsAdmin.ajaxUrl, data)
				.done(function (response) {
					if (!response || !response.success) {
						showNotices(response && response.data && response.data.notices ? response.data.notices : '<div class="notice notice-error nexisettings-notice"><p>' + nexiSettingsAdmin.saveFailed + '</p></div>');
						return;
					}

					showNotices(response.data.notices);
				})
				.fail(function (xhr) {
					var notices = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.notices ? xhr.responseJSON.data.notices : '<div class="notice notice-error nexisettings-notice"><p>' + nexiSettingsAdmin.ajaxError + '</p></div>';
					showNotices(notices);
				})
				.always(function () {
					setButtonState($form, false);
					$form.removeData('clicked-submit');
				});
		});

		$(document).on('change', 'select[name="nexisettings_options[login_block_action]"]', toggleCustomBlockUrlField);
		toggleCustomBlockUrlField();

		$('.nexisettings-upload-logo').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: nexiSettingsAdmin.chooseLogo,
				button: {
					text: nexiSettingsAdmin.useLogo
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

				$('.nexisettings-logo-id').val(attachment.id);
				$('.nexisettings-logo-preview')
					.removeClass('is-empty')
					.empty()
					.append($('<img />', {
						src: imageUrl,
						alt: ''
					}));
			});

			frame.open();
		});

		$('.nexisettings-remove-logo').on('click', function (event) {
			event.preventDefault();
			$('.nexisettings-logo-id').val('');
			$('.nexisettings-logo-preview')
				.addClass('is-empty')
				.html('<span>' + nexiSettingsAdmin.noLogo + '</span>');
		});

		$('.nexisettings-add-redirect').on('click', function (event) {
			event.preventDefault();

			var template = $('#nexisettings-redirect-row-template').html();
			var index = Date.now();

			$('.nexisettings-redirects-table tbody').append(template.replace(/__index__/g, index));
		});

		$(document).on('click', '.nexisettings-delete-row', function (event) {
			event.preventDefault();

			var $row = $(this).closest('tr');
			$row.find('.nexisettings-delete-value').val('1');
			$row.addClass('nexisettings-redirect-row-deleted');
		});
	});
})(jQuery);
