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

	/* ------------------------------------------------------------------
	 * Montos: cuánto paga en soles el cliente ("≈ S/ 26.25 para el cliente")
	 * ---------------------------------------------------------------- */
	function textoMonto(valor) {
		return uep_admin.moneda + ' ' + parseFloat(valor).toFixed(2);
	}

	function actualizarEquiv(input) {
		var span = input.next('.uep-equiv-vivo');
		var valor = parseFloat(input.val());

		span.text(valor > 0 ? uep_admin.i18n.equiv.replace('%s', (valor * uep_admin.tc).toFixed(2)) : '');
	}

	function prepararEquiv(input) {
		if (!(uep_admin.tc > 0) || input.next('.uep-equiv-vivo').length) {
			return;
		}
		input.after($('<span class="uep-equiv uep-equiv-vivo">'));
		actualizarEquiv(input);
	}

	$('input[data-uep-equiv]').each(function () {
		prepararEquiv($(this));
	});

	$(document).on('input change', 'input[data-uep-equiv]', function () {
		if (uep_admin.tc > 0) {
			actualizarEquiv($(this));
		}
	});

	/* ------------------------------------------------------------------
	 * Tarifas: avisar (y precargar) cuando la zona elegida ya tiene precio
	 * ---------------------------------------------------------------- */
	var costo = $('#uep_costo');
	var costoFlash = $('#uep_costo_express');

	function precargar(input, valor) {
		if (!input.val() || input.data('uep-precarga')) {
			input.val(valor).data('uep-precarga', valor !== '').trigger('change');
		}
	}

	function revisarExistente() {
		var aviso = $('#uep_tarifa_existente');

		if (!aviso.length) {
			return;
		}

		var clave = (depa.val() || 0) + '|' + (prov.val() || 0) + '|' + (dist.val() || 0);
		var previo = depa.val() ? uep_admin.existentes[clave] : null;

		if (!previo) {
			aviso.prop('hidden', true);
			$('#uep_guardar_tarifa').text(uep_admin.i18n.guardar);
			precargar(costo, '');
			precargar(costoFlash, '');
			return;
		}

		aviso.text(
			uep_admin.i18n.existe
				.replace('%1$s', textoMonto(previo.costo))
				.replace('%2$s', previo.flash ? textoMonto(previo.flash) : uep_admin.i18n.por_defecto)
		).prop('hidden', false);
		$('#uep_guardar_tarifa').text(uep_admin.i18n.actualizar);
		precargar(costo, previo.costo);
		precargar(costoFlash, previo.flash);
	}

	// Si la persona escribe un monto, deja de ser una precarga.
	costo.add(costoFlash).on('input', function (e) {
		if (e.originalEvent) {
			$(this).data('uep-precarga', false);
		}
	});

	depa.on('change', revisarExistente);
	prov.on('change', revisarExistente);
	dist.on('change', revisarExistente);

	// "Distritos sin precio propio": un clic llena el formulario.
	function esperarOpcion(select, valor, listo) {
		var intentos = 0;

		(function revisar() {
			if (select.find('option[value="' + valor + '"]').length) {
				listo();
			} else if (++intentos < 60) {
				setTimeout(revisar, 100);
			}
		})();
	}

	$(document).on('click', '.uep-agregar-dist', function () {
		var boton = $(this);

		depa.val(String(boton.data('depa'))).trigger('change');
		esperarOpcion(prov, boton.data('prov'), function () {
			prov.val(String(boton.data('prov'))).trigger('change');
			esperarOpcion(dist, boton.data('dist'), function () {
				dist.val(String(boton.data('dist'))).trigger('change');
				$('html, body').animate({ scrollTop: $('#uep-form-tarifa').offset().top - 80 }, 200);
				costo.trigger('focus');
			});
		});
	});

	/* ------------------------------------------------------------------
	 * Ajustes: listas editables (agencias, métodos internos, feriados).
	 * El textarea original sigue siendo el campo que se guarda.
	 * ---------------------------------------------------------------- */
	function diaDeFecha(fila, textoPasado) {
		var valor = fila.find('.uep-rep-valor').val();
		var dia = fila.find('.uep-rep-dia');

		if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) {
			dia.text('');
			return;
		}

		var fecha = new Date(valor + 'T12:00:00');
		var hoy = new Date();
		hoy.setHours(0, 0, 0, 0);

		var texto = fecha.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
		dia.text(fecha < hoy ? texto + ' (' + textoPasado + ')' : texto).toggleClass('uep-rep-pasado', fecha < hoy);
	}

	$('.uep-repetidor').each(function () {
		var cont = $(this);
		var tipo = cont.data('tipo');
		var fuente = cont.find('textarea');
		var lista = $('<div class="uep-rep-lista">');
		var vacio = $('<p class="description uep-rep-vacio">').text(cont.data('vacio'));
		var agregar = $('<button type="button" class="button uep-rep-agregar">').text(cont.data('agregar'));

		function valores() {
			var texto = fuente.val() || '';

			if (tipo === 'fechas') {
				return texto.split(/[\s,;]+/).filter(function (f) {
					return /^\d{4}-\d{2}-\d{2}$/.test(f);
				});
			}

			return texto.split(/\r?\n/).map($.trim).filter(Boolean);
		}

		function nuevaFila(valor) {
			var fila = $('<div class="uep-rep-fila">');

			if (tipo === 'metodos') {
				var partes = (valor || '').split('|');
				var costoInput = $('<input type="number" step="0.01" min="0" class="small-text uep-rep-costo" data-uep-equiv>')
					.attr({ placeholder: cont.data('costo'), 'aria-label': cont.data('costo') })
					.val(partes.length > 1 ? $.trim(partes[1]).replace(',', '.') : '');

				fila.append(
					$('<input type="text" class="regular-text uep-rep-nombre">')
						.attr({ placeholder: cont.data('placeholder'), 'aria-label': cont.data('placeholder') })
						.val($.trim(partes[0])),
					costoInput
				);
				prepararEquiv(costoInput);
			} else if (tipo === 'fechas') {
				fila.append(
					$('<input type="date" class="uep-rep-valor">').attr('aria-label', cont.data('placeholder')).val(valor),
					$('<span class="uep-rep-dia">')
				);
			} else {
				fila.append(
					$('<input type="text" class="regular-text uep-rep-valor">')
						.attr({ placeholder: cont.data('placeholder'), 'aria-label': cont.data('placeholder') })
						.val(valor)
				);
			}

			fila.append($('<button type="button" class="button-link uep-rep-quitar">').text(cont.data('quitar')));

			if (tipo === 'fechas') {
				diaDeFecha(fila, cont.data('pasado'));
			}

			return fila;
		}

		function serializar() {
			var lineas = [];

			lista.children().each(function () {
				var fila = $(this);

				if (tipo === 'metodos') {
					var nombre = $.trim(fila.find('.uep-rep-nombre').val()).replace(/\|/g, '/');
					var monto = $.trim(fila.find('.uep-rep-costo').val());

					if (nombre) {
						lineas.push(monto !== '' ? nombre + ' | ' + monto : nombre);
					}
				} else {
					var valor = $.trim(fila.find('.uep-rep-valor').val());

					if (valor) {
						lineas.push(valor);
					}
				}
			});

			if (tipo === 'fechas') {
				lineas.sort();
			}

			fuente.val(lineas.join('\n'));
			vacio.toggle(!lista.children().length);
		}

		fuente.hide();
		cont.append(lista, vacio, agregar);

		$.each(valores(), function (_, valor) {
			lista.append(nuevaFila(valor));
		});
		vacio.toggle(!lista.children().length);

		agregar.on('click', function () {
			var fila = nuevaFila('');
			lista.append(fila);
			fila.find('input').first().trigger('focus');
			serializar();
			fuente.trigger('change');
		});

		lista.on('input change', 'input', function () {
			if (tipo === 'fechas') {
				diaDeFecha($(this).closest('.uep-rep-fila'), cont.data('pasado'));
			}
			serializar();
		});

		lista.on('click', '.uep-rep-quitar', function (e) {
			e.preventDefault();
			$(this).closest('.uep-rep-fila').remove();
			serializar();
			fuente.trigger('change');
		});
	});

	/* ------------------------------------------------------------------
	 * Ajustes: "Así lo verá el cliente"
	 * ---------------------------------------------------------------- */
	function horaTexto(hhmm) {
		var partes = hhmm.split(':');
		var h = parseInt(partes[0], 10);
		var sufijo = h < 12 ? 'am' : 'pm';

		h = h % 12 || 12;

		return h + ':' + partes[1] + ' ' + sufijo;
	}

	function actualizarVista(campo) {
		var destino = $('[data-uep-vista="' + campo + '"]');
		var valor = $.trim($('[name="' + campo + '"]').val() || '');

		if (destino.hasClass('uep-vista-nota')) {
			destino.text(valor).prop('hidden', !valor);
		} else {
			destino.text(valor || destino.data('defecto'));
		}
	}

	function vistaFlash() {
		var linea = $('[data-uep-tarde]');
		var hora = $('[name="express_cutoff"]').val() || '';

		if (!linea.length) {
			return;
		}

		if (!/^\d{2}:\d{2}$/.test(hora)) {
			linea.hide();
			return;
		}

		var titulo = $.trim($('[name="express_title"]').val() || '') || $('[data-uep-vista="express_title"]').data('defecto');
		var texto = horaTexto(hora);

		linea.show();
		linea.find('.uep-vista-cuando').text(linea.data('antes').replace('%s', texto));
		linea.find('[data-uep-vista="express_title_tarde"]').text(titulo + ' — ' + linea.data('aviso').replace('%s', texto));
	}

	$.each(['method_title', 'express_title', 'pickup_title', 'agencia_title', 'pickup_note', 'agencia_note'], function (_, campo) {
		$(document).on('input', '[name="' + campo + '"]', function () {
			actualizarVista(campo);
			vistaFlash();
		});
	});
	$(document).on('input change', '[name="express_cutoff"]', vistaFlash);
	vistaFlash();

	/* ------------------------------------------------------------------
	 * Ajustes: tabla de métodos de pago
	 * ---------------------------------------------------------------- */
	function revisarMatriz() {
		$('.uep-matriz-pagos tbody tr').each(function () {
			$(this).toggleClass('uep-solo-equipo', $(this).find('.uep-check-equipo').is(':checked'));
		});
		$('.uep-aviso-recojo').prop('hidden', $('.uep-check-recojo:checked').length > 0);
	}

	$(document).on('change', '.uep-matriz-pagos input', revisarMatriz);
	revisarMatriz();

	/* ------------------------------------------------------------------
	 * Ajustes: aviso de cambios sin guardar
	 * ---------------------------------------------------------------- */
	var formAjustes = $('#uep-form-ajustes');
	var sinGuardar = false;

	function marcarCambios() {
		if (sinGuardar) {
			return;
		}
		sinGuardar = true;

		var estado = $('.uep-estado-guardado');
		estado.text(estado.data('con-cambios')).addClass('uep-sin-guardar');
	}

	formAjustes.on('input change', marcarCambios);
	formAjustes.on('click', '.uep-zona-agregar, .uep-zona-quitar', marcarCambios);
	formAjustes.on('submit', function () {
		sinGuardar = false;
	});

	$(window).on('beforeunload', function (e) {
		if (sinGuardar) {
			e.preventDefault();
			return uep_admin.i18n.salir;
		}
	});
})(jQuery);
