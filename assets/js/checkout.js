/**
 * Selects encadenados de ubigeo en el checkout clásico.
 * Departamento -> Provincia -> Distrito (billing y shipping).
 */
(function ($) {
	'use strict';

	if (typeof uep_checkout === 'undefined') {
		return;
	}

	/**
	 * Rellena un select con opciones {id, nombre}.
	 */
	function llenar(select, filas, placeholder, idKey, nameKey, valorActual) {
		select.empty().append($('<option>', { value: '', text: placeholder }));

		$.each(filas, function (_, fila) {
			select.append($('<option>', { value: fila[idKey], text: fila[nameKey] }));
		});

		if (valorActual && select.find('option[value="' + valorActual + '"]').length) {
			select.val(valorActual);
		}

		select.trigger('change');
	}

	function cargarProvincias(prefijo, idDepa, valorActual) {
		var select = $('#' + prefijo + '_provincia');

		if (!idDepa) {
			llenar(select, [], uep_checkout.i18n.provincia);
			return;
		}

		$.post(uep_checkout.ajax_url, {
			action: 'uep_provincias',
			nonce: uep_checkout.nonce,
			idDepa: idDepa
		}).done(function (res) {
			llenar(select, res && res.success ? res.data : [], uep_checkout.i18n.provincia, 'idProv', 'provincia', valorActual);
		});
	}

	function cargarDistritos(prefijo, idProv, valorActual) {
		var select = $('#' + prefijo + '_distrito');

		if (!idProv) {
			llenar(select, [], uep_checkout.i18n.distrito);
			return;
		}

		$.post(uep_checkout.ajax_url, {
			action: 'uep_distritos',
			nonce: uep_checkout.nonce,
			idProv: idProv
		}).done(function (res) {
			llenar(select, res && res.success ? res.data : [], uep_checkout.i18n.distrito, 'idDist', 'distrito', valorActual);
		});
	}

	function enganchar(prefijo) {
		var doc = $(document.body);

		// Delegado: el checkout puede re-renderizar los campos.
		doc.on('change', '#' + prefijo + '_departamento', function () {
			cargarProvincias(prefijo, $(this).val());
			llenar($('#' + prefijo + '_distrito'), [], uep_checkout.i18n.distrito);
			doc.trigger('update_checkout');
		});

		doc.on('change', '#' + prefijo + '_provincia', function () {
			cargarDistritos(prefijo, $(this).val());
		});

		doc.on('change', '#' + prefijo + '_distrito', function () {
			// Copia el distrito al campo "ciudad" (oculto para Perú): así WooCommerce
			// considera la dirección completa y muestra/calcula el envío siempre.
			var texto = $(this).find('option:selected').text();
			if ($(this).val()) {
				$('#' + prefijo + '_city').val(texto);
			}
			doc.trigger('update_checkout');
		});
	}

	// Envío por agencia: muestra el campo de texto al elegir "Otro (escribir)".
	$(document.body).on('change', '#uep_agencia_nombre', function () {
		$('#uep_agencia_otro_wrap').toggle($(this).val() === '__otro');
	});

	// Recojo en tienda: bloquea días sin atención y feriados en el calendario.
	$(document.body).on('change', '#uep_pickup_fecha', function () {
		var v = this.value;
		var datos = uep_checkout.pickup;

		if (!v || !datos) {
			return;
		}

		var dia = new Date(v + 'T12:00:00').getDay();

		if (datos.cerrados.indexOf(String(dia)) !== -1 || datos.feriados.indexOf(v) !== -1) {
			window.alert(datos.msg);
			this.value = '';
		}
	});

	$(function () {
		enganchar('billing');
		enganchar('shipping');
	});
})(jQuery);
