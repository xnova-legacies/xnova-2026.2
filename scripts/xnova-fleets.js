/**
 * Bandeau « flottes en vol », présent en bas de toutes les pages.
 *
 * Les lignes sont rendues par le serveur (App\Core\FleetBar, injectées avant
 * </body>) : le client ne fait que
 *   - décompter les échéances (`data-fleet-end`, recalculées à partir de
 *     l'horloge serveur reçue dans l'état temps réel) ;
 *   - replier/déplier le panneau (choix mémorisé dans le navigateur) ;
 *   - redemander le fragment à l'API (`/game/api/fleets`) quand l'empreinte des
 *     vols change — un vol qui part, arrive ou change d'échéance — sans
 *     recharger la page.
 *
 * Les règles d'affichage et de calcul des échéances restent côté serveur : ce
 * script ne connaît que des horodatages et du HTML déjà mis en forme.
 */
(function (window, document) {
	'use strict';

	var TICK_MS = 1000;
	var STORAGE_KEY = 'xnova-fleets-open';

	var bar = null;
	var panel = null;
	var toggle = null;
	var badge = null;
	var clockOffset = 0;
	var revision = '';
	// Échéances déjà atteintes : une échéance qui tombe déclenche une seule
	// demande de fragment (le serveur décide alors de l'événement suivant).
	var expired = {};
	// L'alerte d'attaque ne recharge la page qu'une fois par affichage.
	var alertExpired = false;

	function serverNow() {
		return Math.floor(Date.now() / 1000) + clockOffset;
	}

	function formatCountdown(seconds) {
		if (seconds <= 0) {
			return '0:00:00';
		}

		var hours = Math.floor(seconds / 3600);
		var minutes = Math.floor((seconds % 3600) / 60);
		var rest = seconds % 60;

		return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (rest < 10 ? '0' : '') + rest;
	}

	function tick() {
		// Alerte d'attaque : elle ne dépend pas du bandeau du bas. Quand une
		// échéance hostile tombe, on recharge la page — c'est le chargement qui
		// fait traiter l'arrivée par le serveur.
		document.querySelectorAll('[data-fleet-alert-end]').forEach(function (node) {
			var alertEnd = parseInt(node.getAttribute('data-fleet-alert-end'), 10) || 0;
			var alertLeft = alertEnd - serverNow();
			var alertCountdown = node.querySelector('.xnova-alert-countdown');

			if (alertCountdown) {
				alertCountdown.textContent = formatCountdown(alertLeft);
			}

			if (alertLeft <= 0 && !alertExpired) {
				alertExpired = true;
				window.location.reload();
			}
		});

		if (!bar) {
			return;
		}

		var elapsed = false;

		bar.querySelectorAll('[data-fleet-end]').forEach(function (node) {
			var endTime = parseInt(node.getAttribute('data-fleet-end'), 10) || 0;
			var remaining = endTime - serverNow();
			var countdown = node.querySelector('.xnova-fleets-countdown');

			if (countdown) {
				countdown.textContent = formatCountdown(remaining);
			}

			if (remaining <= 0 && !expired[endTime]) {
				expired[endTime] = true;
				elapsed = true;
			}
		});

		// Une échéance vient de tomber : la ligne concernée change d'événement
		// (arrivée devenue stationnement, retour, disparition du vol).
		if (elapsed) {
			refresh();
		}
	}

	/** Choix mémorisé : null = « ouvre dès qu'il y a des vols ». */
	function openPreference() {
		try {
			var stored = window.localStorage.getItem(STORAGE_KEY);

			return stored === null ? null : stored === '1';
		} catch (error) {
			return null;
		}
	}

	function setOpen(open, remember) {
		if (!panel || !toggle) {
			return;
		}

		panel.classList.toggle('d-none', !open);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		bar.classList.toggle('xnova-fleets-open', open);

		if (remember) {
			try {
				window.localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
			} catch (error) {
				// Stockage indisponible : le bandeau reste utilisable.
			}
		}
	}

	function count() {
		return parseInt(bar ? bar.getAttribute('data-fleet-count') : '0', 10) || 0;
	}

	/**
	 * Panneau ouvert ou replié : la préférence du joueur est respectée, sauf
	 * quand rien ne vole — la ligne seule suffit alors à l'annoncer.
	 */
	function applyPreference() {
		var preference = openPreference();

		if (count() === 0) {
			setOpen(false, false);

			return;
		}

		setOpen(preference === null ? true : preference, false);
	}

	/** Applique le fragment renvoyé par l'API (une seule mise en forme : celle du serveur). */
	function apply(json) {
		var data = (json && json.data) || {};

		if (typeof data.html === 'string' && panel) {
			panel.innerHTML = data.html;
		}

		if (bar) {
			if (typeof data.count === 'number') {
				bar.setAttribute('data-fleet-count', data.count);

				if (badge) {
					badge.textContent = data.count;
				}

				applyPreference();
			}

			if (typeof data.revision === 'string' && data.revision !== '') {
				revision = data.revision;
				bar.setAttribute('data-fleet-fingerprint', data.revision);
			}
		}

		tick();
	}

	function refresh() {
		if (!window.XNova || !window.XNova.get) {
			return;
		}

		window.XNova.get('/fleets').then(apply).catch(function () {
			// Réseau indisponible : le décompte continue sur le rendu connu.
		});
	}

	/** Cale le bandeau juste au-dessus de la barre de debug quand elle est active. */
	function place() {
		// La barre php-debugbar ne porte pas d'identifiant : on la repère par sa
		// classe de conteneur (les éléments internes ont des classes distinctes).
		var debugbar = document.querySelector('.phpdebugbar');

		document.documentElement.style.setProperty(
			'--xnova-fleets-offset',
			(debugbar ? debugbar.offsetHeight : 0) + 'px'
		);
	}

	function boot() {
		bar = document.getElementById('xnova-fleets-bar');

		if (!bar) {
			// Page sans bandeau : l'alerte d'attaque, elle, doit continuer de se
			// décompter (et recharger la page à l'arrivée).
			if (document.querySelector('[data-fleet-alert-end]')) {
				tick();
				window.setInterval(tick, TICK_MS);
			}

			return;
		}

		panel = document.getElementById('xnova-fleets-list');
		toggle = bar.querySelector('[data-fleets-toggle]');
		badge = bar.querySelector('.xnova-fleets-badge');
		revision = bar.getAttribute('data-fleet-fingerprint') || '';

		applyPreference();
		place();
		tick();

		if (toggle) {
			toggle.addEventListener('click', function () {
				setOpen(!bar.classList.contains('xnova-fleets-open'), true);
				place();
			});
		}

		// La barre de debug se dessine après coup : on remesure une fois la page posée.
		window.setTimeout(place, 1000);

		document.addEventListener('xnova:state', function (event) {
			var state = event.detail || {};
			var fleets = state.fleets || {};

			clockOffset = (state.server_time || 0) - Math.floor(Date.now() / 1000);
			tick();

			if (fleets.revision && fleets.revision !== revision) {
				refresh();
			}
		});

		window.addEventListener('resize', place);
		window.setInterval(tick, TICK_MS);
	}

	document.addEventListener('DOMContentLoaded', boot);
})(window, document);
