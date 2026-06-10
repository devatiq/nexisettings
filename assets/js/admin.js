(function ($) {
	'use strict';

	$(function () {
		var frame;

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
					.html('<img src="' + imageUrl + '" alt="" />');
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
