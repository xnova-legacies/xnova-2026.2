/**
 * Files d'attente : décompte des temps et réordonnancement par glisser-déposer.
 *
 * Les lignes sont rendues par le serveur (`App\Core\QueueRenderer`) : le client
 * ne fait plus que réécrire le texte des compteurs (`data-end-time`, recalculé
 * à partir de l'horloge serveur) et signaler un déplacement à
 * /game/api/queues/reorder. Après un déplacement il recharge le contenu, le
 * serveur restant la source de vérité pour l'ordre et la numérotation.
 */
(function (window, document) {
	'use strict';

	var TICK_MS = 1000;

	var state = null;
	var clockOffset = 0;   // décalage horloge serveur / client, en secondes
	var reloadedFor = 0;   // échéance déjà signalée au navigateur (voir watchSidebarDeadlines)

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

	function setState(next) {
		state = next;
		clockOffset = (next.server_time || 0) - Math.floor(Date.now() / 1000);
	}

	function tick() {
		if (!state) {
			return;
		}

		document.querySelectorAll('[data-end-time]').forEach(function (node) {
			var endTime = parseInt(node.getAttribute('data-end-time'), 10) || 0;

			node.textContent = formatCountdown(endTime - serverNow());
		});

		watchSidebarDeadlines();
	}

	/** Une saisie en cours ne doit pas etre interrompue par un rechargement. */
	function isEditing() {
		var active = document.activeElement;

		if (!active || !active.tagName) {
			return false;
		}

		var tag = active.tagName.toLowerCase();

		return (tag === 'input' || tag === 'select' || tag === 'textarea') && !active.disabled;
	}

	/**
	 * Le panneau laterál des files (App\Core\QueueBar, id xnova-queues) vit HORS de
	 * .xnova-content : le rafraichissement a chaud ne le touche pas. Or c'est le
	 * CHARGEMENT de la page qui fait avancer les files (PlanetStateService) : quand
	 * une echeance tombe, on recharge donc la page pour que la file soit reellement
	 * traitee.
	 *
	 * Une seule fois par echeance — une file que le serveur ne termine pas ferait
	 * sinon boucler la page — et jamais pendant une saisie.
	 */
	function watchSidebarDeadlines() {
		var panneau = document.getElementById('xnova-queues');

		if (!panneau || isEditing()) {
			return;
		}

		var echeance = 0;

		panneau.querySelectorAll('[data-end-time]').forEach(function (node) {
			var endTime = parseInt(node.getAttribute('data-end-time'), 10) || 0;

			// Marge d'une seconde : le serveur ne termine un chantier qu'une fois
			// l'heure reellement atteinte.
			if (endTime > 0 && endTime <= serverNow() - 1 && (echeance === 0 || endTime < echeance)) {
				echeance = endTime;
			}
		});

		if (echeance === 0 || echeance === reloadedFor) {
			return;
		}

		reloadedFor = echeance;
		window.location.reload();
	}

	function reorder(domain, from, to) {
		if (from === to || to < 2) {
			return;
		}

		window.XNova.post('/game/api/queues/reorder', { domain: domain, from: from, to: to })
			.then(function () {
				// Le serveur renumérote les positions : on reprend son rendu.
				if (window.XNova.softReload) {
					window.XNova.softReload();
				}
			})
			.catch(function (error) {
				window.XNova.notify('error', (error && error.message) || 'Déplacement impossible.');

				// Le déplacement a pu être appliqué malgré la réponse perdue : on
				// réaffiche l'état réel plutôt que de laisser l'ancienne liste.
				if (window.XNova.softReload) {
					window.XNova.softReload();
				}
			});
	}

	function bindDragAndDrop() {
		document.addEventListener('dragstart', function (event) {
			var item = event.target.closest ? event.target.closest('[data-movable="1"]') : null;

			if (!item) {
				// Un élément non déplaçable ne doit pas pouvoir être saisi.
				if (event.target.closest && event.target.closest('[data-position]')) {
					event.preventDefault();
				}

				return;
			}

			item.classList.add('xnova-queue-dragging');
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', item.getAttribute('data-position'));
		});

		document.addEventListener('dragend', function (event) {
			var item = event.target.closest ? event.target.closest('[data-position]') : null;

			if (item) {
				item.classList.remove('xnova-queue-dragging');
			}
		});

		document.addEventListener('dragover', function (event) {
			if (event.target.closest && event.target.closest('[data-xnova-queue]')) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
			}
		});

		document.addEventListener('drop', function (event) {
			var container = event.target.closest ? event.target.closest('[data-xnova-queue]') : null;
			var target = event.target.closest ? event.target.closest('[data-position]') : null;

			if (!container || !target) {
				return;
			}

			event.preventDefault();

			var from = parseInt(event.dataTransfer.getData('text/plain'), 10);
			var to = parseInt(target.getAttribute('data-position'), 10);

			// La ligne entière sert de cible : exiger la poignée obligeait à viser
			// un repère de quelques pixels, et le dépôt ne faisait alors rien.
			reorder(container.getAttribute('data-xnova-queue'), from, to);
		});
	}

	function boot() {
		// Le script sert aussi au panneau laterál des files, visible sur TOUTES les
		// pages de jeu : il demarre des qu'une echeance est affichee, meme sans file
		// editable sur la page courante.
		if (!document.querySelector('[data-xnova-queue]') && !document.querySelector('[data-end-time]')) {
			return;
		}

		document.addEventListener('xnova:state', function (event) {
			setState(event.detail);
			tick();
		});

		bindDragAndDrop();
		window.setInterval(tick, TICK_MS);

		if (window.XNova.live && window.XNova.live.getState()) {
			setState(window.XNova.live.getState());
			tick();
		}
	}

	document.addEventListener('DOMContentLoaded', boot);
})(window, document);
