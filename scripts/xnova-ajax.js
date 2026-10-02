/**
 * Coeur d'application AJAX de XNova.
 *
 * - XNova.get(path) / XNova.post(path, payload) vers /game/api/...
 * - en-tete X-CSRF-Token a partir de <meta name="csrf-token">
 * - tableau { ok, data, state, messages } / { ok:false, error } normalisee
 * - XNova.notify(type, texte) : messages flottants (Bootstrap)
 * - amelioration progressive des <form data-ajax="..."> (le POST classique
 *   reste le comportement de repli si JavaScript est indisponible)
 */
(function (window, document) {
	'use strict';

	var API_BASE = '/game/api';

	/** Délai au-delà duquel un rafraîchissement sans réponse déclenche un rechargement. */
	var SOFT_RELOAD_TIMEOUT_MS = 4000;

	/** Rechargement en cours (voir softReload) : un seul à la fois. */
	var softReloadInFlight = false;

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');

		return meta ? meta.getAttribute('content') : '';
	}

	function parseJson(text) {
		try {
			return JSON.parse(text);
		} catch (e) {
			return null;
		}
	}

	/**
	 * Resout un chemin d'API : '/state', 'state' et '/game/api/state' designent
	 * tous /game/api/state. Les URL absolues (http...) sont conservees telles quelles.
	 */
	function apiUrl(path) {
		if (/^https?:\/\//i.test(path) || path === API_BASE || path.indexOf(API_BASE + '/') === 0) {
			return path;
		}

		return API_BASE + (path.charAt(0) === '/' ? path : '/' + path);
	}

	function request(method, path, payload) {
		var url = apiUrl(path);

		// Le canal temps réel relaie les actions vers l'API : quand il est
		// connecté, le navigateur ne fait plus d'appel HTTP direct.
		if (window.XNova && window.XNova.ws && window.XNova.ws.isLive()) {
			return method === 'GET' ? window.XNova.ws.get(url) : window.XNova.ws.post(url, payload);
		}

		var options = {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-CSRF-Token': csrfToken()
			}
		};

		if (method !== 'GET') {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(payload || {});
		}

		return window.fetch(url, options).then(function (response) {
			return response.text().then(function (text) {
				var json = parseJson(text);

				if (!json) {
					// Redirection vers la connexion ou reponse HTML inattendue.
					if (response.status === 401 || response.status === 403 || response.redirected) {
						window.location.reload();

						return Promise.reject({ code: 'unauthenticated', message: 'Session expirée.' });
					}

					return Promise.reject({ code: 'bad_response', message: 'Réponse serveur invalide.' });
				}

				if (!json.ok) {
					return Promise.reject(json.error || { code: 'error', message: 'Erreur inconnue.' });
				}

				return json;
			});
		});
	}

	function applyMessages(messages) {
		(messages || []).forEach(function (message) {
			notify(message.type, message.text);
		});
	}

	function applyResponse(json) {
		applyMessages(json.messages);

		if (json.state && window.XNova.live) {
			window.XNova.live.setState(json.state);
		}

		// Certaines actions invalident la session (mot de passe, pseudo) : le
		// serveur indique alors la page vers laquelle revenir.
		if (json.data && json.data.redirect) {
			window.location.href = json.data.redirect;
		}

		return json;
	}

	function notify(type, text) {
		if (!text) {
			return;
		}

		var host = document.getElementById('xnova-toasts');

		if (!host) {
			host = document.createElement('div');
			host.id = 'xnova-toasts';
			host.className = 'position-fixed top-0 end-0 p-3';
			host.style.zIndex = '1080';
			document.body.appendChild(host);
		}

		var variants = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
		var alert = document.createElement('div');
		alert.className = 'alert alert-' + (variants[type] || 'secondary') + ' shadow-sm py-2 px-3 mb-2 small';
		alert.setAttribute('role', 'alert');
		alert.textContent = text;
		host.appendChild(alert);

		window.setTimeout(function () {
			alert.remove();
		}, 5000);
	}

	function clearFieldErrors(form) {
		form.querySelectorAll('.is-invalid').forEach(function (field) {
			field.classList.remove('is-invalid');
		});
		form.querySelectorAll('.invalid-feedback.xnova-ajax').forEach(function (node) {
			node.remove();
		});
	}

	function showFieldErrors(form, error) {
		var fields = (error && error.fields) || {};

		Object.keys(fields).forEach(function (name) {
			var field = form.querySelector('[name="' + name + '"]');

			if (!field) {
				return;
			}

			field.classList.add('is-invalid');
			var feedback = document.createElement('div');
			feedback.className = 'invalid-feedback xnova-ajax';
			feedback.textContent = fields[name];
			field.parentNode.appendChild(feedback);
		});
	}

	function formPayload(form) {
		var payload = {};
		var action = form.getAttribute('action') || '';
		var separator = action.indexOf('?');

		// Les parametres de l'action font partie du contexte de la page
		// (ex. ?mode=write&id=5) : on les transmet avec les champs du formulaire.
		if (separator !== -1) {
			new window.URLSearchParams(action.slice(separator + 1)).forEach(function (value, key) {
				payload[key] = value;
			});
		}

		new window.FormData(form).forEach(function (value, key) {
			payload[key] = typeof value === 'string' ? value : value.name;
		});

		return payload;
	}

	/**
	 * Charge utile d'un formulaire d'envoi de flotte : les champs du jeu
	 * (ship203, resource1, planettype, holdingtime...) sont traduits dans le
	 * vocabulaire de l'API. Les valeurs de calcul du formulaire historique
	 * (capacity*, consumption*, maxship*, dist, speedfactor) sont ignorées :
	 * le serveur les recalcule a partir de la base.
	 */
	function fleetPayload(form) {
		var payload = {};
		var ships = {};

		form.querySelectorAll('input, select').forEach(function (field) {
			var name = field.name || '';
			var ship = /^ship(\d+)$/.exec(name);

			if (ship) {
				var count = parseInt(field.value, 10) || 0;

				if (count > 0) {
					ships[ship[1]] = count;
				}

				return;
			}

			if (!name || /^(capacity|consumption|maxship|speed)\d+$/.test(name)) {
				return;
			}

			if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) {
				return;
			}

			payload[name] = field.value;
		});

		payload.ships = ships;
		payload.planet_type = payload.planettype || 1;
		payload.metal = parseInt(payload.resource1, 10) || 0;
		payload.crystal = parseInt(payload.resource2, 10) || 0;
		payload.deuterium = parseInt(payload.resource3, 10) || 0;
		payload.stay = parseInt(payload.holdingtime || payload.expeditiontime, 10) || 0;

		return payload;
	}

	/**
	 * Charge utile du hangar : les champs amounts[203] du formulaire historique
	 * deviennent l'objet `units` attendu par /game/api/shipyard/add.
	 */
	function unitPayload(form) {
		var units = {};

		new window.FormData(form).forEach(function (value, key) {
			var match = /^amounts\[(\d+)\]$/.exec(key);

			if (!match) {
				return;
			}

			var count = parseInt(value, 10) || 0;

			if (count > 0) {
				units[match[1]] = count;
			}
		});

		return { kind: form.getAttribute('data-ajax-units') || 'fleet', units: units };
	}

	function enhanceForm(form) {
		form.addEventListener('submit', function (event) {
			// Un bouton peut viser une autre page (formaction) : c'est le cas du
			// bouton « Retour » des etapes d'envoi de flotte, qui doit repartir en
			// POST classique vers l'etape precedente, avec les memes donnees.
			var submitter = event.submitter;

			if (submitter && submitter.hasAttribute('data-ajax-skip')) {
				return;
			}

			event.preventDefault();
			clearFieldErrors(form);

			var url = form.getAttribute('data-ajax') || form.getAttribute('action') || '';
			var payload = formPayload(form);

			if (form.hasAttribute('data-ajax-units')) {
				payload = unitPayload(form);
			} else if (form.hasAttribute('data-ajax-fleet')) {
				payload = fleetPayload(form);
			}

			var redirect = form.getAttribute('data-ajax-redirect');

			request('POST', url, payload).then(function (json) {
				applyResponse(json);

				// Envoi de flotte : l'ecran de confirmation HTML n'a plus lieu d'etre,
				// on revient sur la vue des flottes.
				if (redirect) {
					window.location.href = redirect;

					return;
				}

				// Les listes rendues par le serveur (notes, files...) doivent être
				// réaffichées : le rechargement est demandé par le formulaire.
				if (form.hasAttribute('data-ajax-reload')) {
					softReload();
				}
			}).catch(function (error) {
				showFieldErrors(form, error);
				notify('error', (error && error.message) || 'Action impossible.');
			});
		});
	}

	/**
	 * Commandes de file envoyees aujourd'hui par des liens legacy
	 * (?cmd=insert&building=3). C'est L'ADRESSE DU LIEN qui determine le domaine :
	 * les commandes du laboratoire (cmd=search / cmd=cancel) n'ont pas le meme sens
	 * que celles des batiments, et les liens du panneau de droite sont cliques
	 * depuis n'importe quelle page.
	 */
	var QUEUE_ROUTES = {
		buildings: {
			insert: '/game/api/buildings/add',
			destroy: '/game/api/buildings/destroy',
			cancel: '/game/api/buildings/cancel',
			remove: '/game/api/buildings/remove'
		},
		research: {
			search: '/game/api/research/start',
			cancel: '/game/api/research/cancel'
		}
	};

	/**
	 * Domaine d'une commande de file, lu DANS SON ADRESSE et non dans la page.
	 *
	 * Les liens du panneau de droite (App\Core\QueueBar) sont cliques depuis
	 * n'importe quelle page : se fier au `mode` de la page courante enverrait une
	 * commande du laboratoire (cmd=search&tech=106) au controleur des batiments,
	 * avec l'identifiant d'une technologie pour un batiment. L'adresse du lien,
	 * elle, porte toujours le bon mode.
	 */
	function queueDomainOf(url) {
		return url.searchParams.get('mode') === 'research' ? 'research' : 'buildings';
	}

	function queuePayload(command, params) {
		if (command === 'remove') {
			return { position: parseInt(params.get('listid') || '0', 10) };
		}

		if (command === 'cancel' && params.has('tech')) {
			return { element: parseInt(params.get('tech') || '0', 10) };
		}

		if (command === 'search') {
			return { element: parseInt(params.get('tech') || '0', 10) };
		}

		if (command === 'cancel') {
			return {};
		}

		return { element: parseInt(params.get('building') || '0', 10) };
	}

	function interceptQueueCommands() {
		document.addEventListener('click', function (event) {
			if (event.defaultPrevented || event.button !== 0
				|| event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			var link = event.target && event.target.closest ? event.target.closest('a[href*="cmd="]') : null;

			if (!link) {
				return;
			}

			var url = new URL(link.getAttribute('href'), window.location.origin);
			var command = url.searchParams.get('cmd');
			var routes = QUEUE_ROUTES[queueDomainOf(url)];
			var route = routes ? routes[command] : null;

			if (!route) {
				return;
			}

			event.preventDefault();

			XNova.post(route, queuePayload(command, url.searchParams)).then(function () {
				// Les rendus (prix, boutons) restent rendues par le serveur :
				// on rafraichit le contenu sans quitter la page.
				softReload();
			}).catch(function (error) {
				notify('error', (error && error.message) || 'Action impossible.');

				// Le canal temps réel peut perdre sa réponse alors que l'écriture a
				// bien été appliquée (le service ws abandonne au bout de quelques
				// secondes). Sans ce rafraîchissement, la file affichait encore
				// l'élément annulé et le bouton semblait sans effet.
				softReload();
			});
		});
	}

	function softReload() {
		// Un seul rechargement à la fois : l'action et la synchronisation temps réel
		// peuvent le demander en même temps, et deux remplacements concurrents du
		// contenu laisseraient la page dans un état incohérent.
		if (softReloadInFlight) {
			return;
		}

		softReloadInFlight = true;
		var settled = false;

		function release() {
			settled = true;
			softReloadInFlight = false;
			window.clearTimeout(watchdog);
		}

		// Filet de sécurité : une réponse perdue (service ws qui abandonne, requête
		// bloquée par une autre) ne doit pas laisser la page afficher un état périmé.
		var watchdog = window.setTimeout(function () {
			if (!settled) {
				window.location.reload();
			}
		}, SOFT_RELOAD_TIMEOUT_MS);

		var controller = window.AbortController ? new window.AbortController() : null;
		var options = { credentials: 'same-origin', cache: 'no-store' };

		if (controller) {
			options.signal = controller.signal;
		}

		// `no-store` : la page reflète un état qui vient de changer, elle ne doit
		// pas être servie depuis le cache du navigateur.
		window.fetch(window.location.href, options).then(function (response) {
			return response.text();
		}).then(function (html) {
			var doc = new window.DOMParser().parseFromString(html, 'text/html');
			var fresh = doc.querySelector('.xnova-content');
			var current = document.querySelector('.xnova-content');

			if (!fresh || !current) {
				window.location.reload();

				return;
			}

			current.innerHTML = fresh.innerHTML;

			// Le panneau des files d'attente (App\Core\QueueBar, colonne de droite)
			// vit HORS de .xnova-content : sans ce remplacement, une construction
			// lancée n'y apparaîtrait qu'au chargement suivant — l'action semblait
			// donc sans effet dans le panneau.
			// S'il apparaît ou disparaît (la file se garnit ou se vide), la largeur
			// de la colonne principale change aussi : on recharge alors la page
			// entière, plutôt que de laisser une grille fausse.
			var freshBar = doc.querySelector('#xnova-queues');
			var currentBar = document.querySelector('#xnova-queues');

			if (!!freshBar !== !!currentBar) {
				window.location.reload();

				return;
			}

			if (freshBar && currentBar) {
				// Le contenu est rendu par le serveur, y compris les échéances : le
				// décompte du client reprend ces valeurs neuves au tic suivant.
				currentBar.innerHTML = freshBar.innerHTML;
			}

			// Les <script> insérés par innerHTML ne s'exécutent pas : on les recrée.
			current.querySelectorAll('script').forEach(function (old) {
				var script = document.createElement('script');
				Array.prototype.forEach.call(old.attributes, function (attribute) {
					script.setAttribute(attribute.name, attribute.value);
				});
				script.textContent = old.textContent;
				old.replaceWith(script);
			});

			// Les files d'attente sont rendues par le client : on resynchronise
			// aussitôt pour que le rendu dynamique reprenne la main.
			if (window.XNova.live) {
				window.XNova.live.sync();
			}

			// Les scripts contenus dans la page viennent d'être recréés, mais pas ceux
			// de l'en-tête (xnova-market.js par exemple) : on les prévient que le
			// contenu a été remplacé.
			document.dispatchEvent(new CustomEvent('xnova:reloaded'));

			release();
		}).catch(function () {
			release();
			window.location.reload();
		});
	}

	/**
	 * Page de message (renderMessage) : le texte part aussi en notification
	 * flottante, la redirection automatique devient annulable et un bouton
	 * permet de revenir à la page précédente.
	 */
	function enhanceMessagePage() {
		var card = document.querySelector('[data-xnova-message]');

		if (!card) {
			return;
		}

		var header = card.querySelector('.card-header');
		var body = card.querySelector('.card-body');
		var found = header ? /text-bg-([a-z]+)/.exec(header.className) : null;
		var types = { danger: 'error', warning: 'warning', success: 'success', info: 'info' };

		notify(types[found ? found[1] : ''] || 'info', body ? body.textContent.trim() : '');

		var meta = document.querySelector('meta[http-equiv="refresh"]');
		var dest = '';
		var delay = 0;

		if (meta) {
			var parts = /^\s*(\d+)\s*;\s*url=(.+)$/i.exec(meta.getAttribute('content') || '');

			if (parts) {
				delay = parseInt(parts[1], 10) || 0;
				dest = parts[2].trim();
			}

			// La balise est désarmée : la redirection est désormais pilotée ici,
			// donc annulable par l'utilisateur.
			meta.parentNode.removeChild(meta);
		}

		var status = card.querySelector('[data-xnova-message-status]');
		var continueLink = card.querySelector('[data-xnova-message-continue]');
		var stayButton = card.querySelector('[data-xnova-message-stay]');
		var timer = null;
		var counter = null;

		function cancel() {
			window.clearTimeout(timer);
			window.clearInterval(counter);
			timer = null;
			counter = null;

			if (status) {
				status.textContent = '';
			}

			if (stayButton) {
				stayButton.classList.add('d-none');
			}
		}

		if (dest !== '' && delay > 0) {
			if (continueLink) {
				continueLink.setAttribute('href', dest);
				continueLink.classList.remove('d-none');
			}

			if (stayButton) {
				stayButton.classList.remove('d-none');
				stayButton.addEventListener('click', cancel);
			}

			var remaining = delay;

			function refreshStatus() {
				if (status) {
					status.textContent = 'Redirection dans ' + remaining + ' s';
				}
			}

			refreshStatus();
			counter = window.setInterval(function () {
				remaining = Math.max(0, remaining - 1);
				refreshStatus();
			}, 1000);
			timer = window.setTimeout(function () {
				window.location.href = dest;
			}, delay * 1000);
		}

		var back = card.querySelector('[data-xnova-message-back]');

		if (back && window.history.length > 1) {
			back.classList.remove('d-none');
			back.addEventListener('click', function () {
				cancel();
				window.history.back();
			});
		}
	}

	function boot() {
		document.querySelectorAll('form[data-ajax]').forEach(enhanceForm);
		interceptQueueCommands();
		enhanceMessagePage();
	}

	var XNova = window.XNova = window.XNova || {};
	XNova.api = API_BASE;
	XNova.apiUrl = apiUrl;
	XNova.get = function (path) {
		return request('GET', path).then(function (json) {
			return applyResponse(json);
		});
	};
	XNova.post = function (path, payload) {
		return request('POST', path, payload).then(applyResponse);
	};
	XNova.notify = notify;
	XNova.enhance = enhanceForm;
	XNova.softReload = softReload;

	document.addEventListener('DOMContentLoaded', boot);
})(window, document);
