/**
 * Selects encadenados en el formulario de tarifas (admin).
 */
(function ($) {
	'use strict';

	if (typeof uep_admin === 'undefined') {
		return;
	}

	var depa = $('#uep_depa');
	var prov = $('#uep_prov');
	var dist = $('#uep_dist');

	function reset(select, placeholder) {
		select.empty().append($('<option>', { value: '0', text: placeholder }));
	}

	depa.on('change', function () {
		reset(prov, uep_admin.i18n.todas_prov);
		reset(dist, uep_admin.i18n.todos_dist);

		var idDepa = $(this).val();
		if (!idDepa) {
			return;
		}

		$.post(uep_admin.ajax_url, {
			action: 'uep_provincias',
			nonce: uep_admin.nonce,
			idDepa: idDepa
		}).done(function (res) {
			if (res && res.success) {
				$.each(res.data, function (_, fila) {
					prov.append($('<option>', { value: fila.idProv, text: fila.provincia }));
				});
			}
		});
	});

	// Selects de zona (cobertura y recojo, en Ajustes): recarga provincias al cambiar departamento.
	function encadenarZona(depaId, provId) {
		$(document).on('change', depaId, function () {
			var provSel = $(provId);
			reset(provSel, uep_admin.i18n.todas_prov);

			$.post(uep_admin.ajax_url, {
				action: 'uep_provincias',
				nonce: uep_admin.nonce,
				idDepa: $(this).val()
			}).done(function (res) {
				if (res && res.success) {
					$.each(res.data, function (_, fila) {
						provSel.append($('<option>', { value: fila.idProv, text: fila.provincia }));
					});
				}
			});
		});
	}

	encadenarZona('#uep_cov_depa', '#uep_cov_prov');
	encadenarZona('#uep_pk_depa', '#uep_pk_prov');

	// Listas de zonas (cobertura y recojo): agregar y quitar.
	$(document).on('click', '.uep-zona-quitar', function (e) {
		e.preventDefault();
		$(this).closest('li').remove();
	});

	$(document).on('click', '.uep-zona-agregar', function (e) {
		e.preventDefault();

		var lista = $('#' + $(this).data('lista'));
		var depaSel = $('#' + $(this).data('depa'));
		var provSel = $('#' + $(this).data('prov'));
		var nombreCampo = $(this).data('name');

		var depa = depaSel.val();
		if (!depa) {
			return;
		}

		var prov = provSel.val() || '0';
		var valor = depa + '|' + prov;

		if (lista.find('input[value="' + valor + '"]').length) {
			return;
		}

		var etiqueta = depaSel.find('option:selected').text() +
			(prov !== '0' ? ' › ' + provSel.find('option:selected').text() : ' (todo el departamento)');

		var li = $('<li>');
		li.append($('<input>', { type: 'hidden', name: nombreCampo + '[]', value: valor }));
		li.append($('<span>').text(etiqueta));
		li.append(' ');
		li.append($('<a>', { href: '#', 'class': 'uep-zona-quitar', text: 'Quitar' }));
		lista.append(li);
	});

	// Edición rápida en la tabla de tarifas: Editar ↔ Guardar/Cancelar.
	$(document).on('click', '.uep-tabla .uep-editar', function (e) {
		e.preventDefault();
		$(this).closest('tr').addClass('uep-editando');
	});

	$(document).on('click', '.uep-tabla .uep-cancelar', function (e) {
		e.preventDefault();
		$(this).closest('tr').removeClass('uep-editando');
	});

	prov.on('change', function () {
		reset(dist, uep_admin.i18n.todos_dist);

		var idProv = $(this).val();
		if (!idProv || idProv === '0') {
			return;
		}

		$.post(uep_admin.ajax_url, {
			action: 'uep_distritos',
			nonce: uep_admin.nonce,
			idProv: idProv
		}).done(function (res) {
			if (res && res.success) {
				$.each(res.data, function (_, fila) {
					dist.append($('<option>', { value: fila.idDist, text: fila.distrito }));
				});
			}
		});
	});

	/* ------------------------------------------------------------------
	 * Inicio: simulador "¿qué verá un cliente?"
	 * ---------------------------------------------------------------- */
	var simDepa = $('#uep_sim_depa');
	var simProv = $('#uep_sim_prov');
	var simDist = $('#uep_sim_dist');
	var simRes  = $('#uep_sim_resultado');

	function simVaciar(select) {
		select.empty().append($('<option>', { value: '', text: uep_admin.i18n.elige })).prop('disabled', true);
	}

	function simCargar(select, accion, datos, idKey, nameKey) {
		$.post(uep_admin.ajax_url, $.extend({ action: accion, nonce: uep_admin.nonce }, datos)).done(function (res) {
			if (res && res.success) {
				$.each(res.data, function (_, fila) {
					select.append($('<option>', { value: fila[idKey], text: fila[nameKey] }));
				});
				select.prop('disabled', false);
			}
		});
	}

	function simular() {
		if (!simDist.val()) {
			return;
		}

		simRes.html('<p class="description">' + uep_admin.i18n.calculando + '</p>');

		$.post(uep_admin.ajax_url, {
			action: 'uep_simular',
			nonce: uep_admin.sim_nonce,
			idDepa: simDepa.val(),
			idProv: simProv.val(),
			idDist: simDist.val(),
			rol: $('input[name="uep_sim_rol"]:checked').val()
		}).done(function (res) {
			simRes.html(res && res.data && res.data.html ? res.data.html : '<p>' + uep_admin.i18n.error + '</p>');
		}).fail(function () {
			simRes.html('<p>' + uep_admin.i18n.error + '</p>');
		});
	}

	simDepa.on('change', function () {
		simVaciar(simProv);
		simVaciar(simDist);
		simRes.empty();
		if ($(this).val()) {
			simCargar(simProv, 'uep_provincias', { idDepa: $(this).val() }, 'idProv', 'provincia');
		}
	});

	simProv.on('change', function () {
		simVaciar(simDist);
		simRes.empty();
		if ($(this).val()) {
			simCargar(simDist, 'uep_distritos', { idProv: $(this).val() }, 'idDist', 'distrito');
		}
	});

	simDist.on('change', simular);
	$(document).on('change', 'input[name="uep_sim_rol"]', simular);

	/* ------------------------------------------------------------------
	 * Tarifas: buscador de la tabla
	 * ---------------------------------------------------------------- */
	function normalizar(texto) {
		return (texto || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	$('#uep_buscar_tarifa').on('input', function () {
		var q     = normalizar($(this).val()).trim();
		var filas = $('.uep-tabla tbody tr');
		var vis   = 0;

		filas.each(function () {
			var ok = !q || normalizar($(this).children('td').first().text()).indexOf(q) !== -1;
			$(this).toggle(ok);
			if (ok) {
				vis++;
			}
		});

		$('#uep_buscar_conteo').text(q ? uep_admin.i18n.conteo.replace('%1$d', vis).replace('%2$d', filas.length) : '');
	});

	/* ------------------------------------------------------------------
	 * Ajustes: atenuar las opciones de una sección apagada (Flash, Recojo)
	 * ---------------------------------------------------------------- */
	$('table[data-uep-toggle]').each(function () {
		var tabla = $(this);
		var check = tabla.find('input[name="' + tabla.data('uep-toggle') + '"]');

		check.closest('tr').addClass('uep-toggle-fila');

		function aplicar() {
			tabla.toggleClass('uep-apagado', !check.is(':checked'));
		}

		check.on('change', aplicar);
		aplicar();
	});
})(jQuery);
