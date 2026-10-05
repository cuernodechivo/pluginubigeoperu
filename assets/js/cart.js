/**
 * Estimador de costo de envío en la página del carrito.
 */
(function ($) {
	'use strict';

	if (typeof uep_cart === 'undefined') {
		return;
	}

	function llenar(select, filas, placeholder, idKey, nameKey) {
		select.empty().append($('<option>', { value: '', text: placeholder }));

		$.each(filas || [], function (_, fila) {
			select.append($('<option>', { value: fila[idKey], text: fila[nameKey] }));
		});
	}

	$(document).on('change', '#uep_est_depa', function () {
		var idDepa = $(this).val();

		llenar($('#uep_est_prov'), [], uep_cart.i18n.provincia);
		llenar($('#uep_est_dist'), [], uep_cart.i18n.distrito);

		if (!idDepa) {
			return;
		}

		$.post(uep_cart.ajax_url, { action: 'uep_provincias', nonce: uep_cart.nonce, idDepa: idDepa })
			.done(function (res) {
				if (res && res.success) {
					llenar($('#uep_est_prov'), res.data, uep_cart.i18n.provincia, 'idProv', 'provincia');
				}
			});
	});

	$(document).on('change', '#uep_est_prov', function () {
		var idProv = $(this).val();

		llenar($('#uep_est_dist'), [], uep_cart.i18n.distrito);

		if (!idProv) {
			return;
		}

		$.post(uep_cart.ajax_url, { action: 'uep_distritos', nonce: uep_cart.nonce, idProv: idProv })
			.done(function (res) {
				if (res && res.success) {
					llenar($('#uep_est_dist'), res.data, uep_cart.i18n.distrito, 'idDist', 'distrito');
				}
			});
	});

	var calculando = false;

	function calcular() {
		var idDepa = $('#uep_est_depa').val();

		if (!idDepa || calculando) {
			return;
		}

		calculando = true;
		$('#uep_est_calcular').prop('disabled', true).text('Calculando…');

		$.post(uep_cart.ajax_url, {
			action: 'uep_estimar',
			nonce: uep_cart.nonce,
			idDepa: idDepa,
			idProv: $('#uep_est_prov').val() || 0,
			idDist: $('#uep_est_dist').val() || 0
		}).always(function () {
			// Recarga para que WooCommerce recalcule los totales con el nuevo ubigeo.
			window.location.reload();
		});
	}

	$(document).on('click', '#uep_est_calcular', function (e) {
		e.preventDefault();
		calcular();
	});

	// Cálculo automático al elegir el distrito (el botón queda como respaldo).
	$(document).on('change', '#uep_est_dist', function () {
		if ($(this).val()) {
			calcular();
		}
	});
})(jQuery);
