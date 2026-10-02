/**
 * Canal temps réel de XNova (WebSocket).
 *
 * Le navigateur ne s'adresse plus directement à /game/api : il envoie des
 * requêtes {id, action, path, payload} et reçoit soit une réponse (même
 * tableau que l'API JSON : ok/data/state/error), soit un événement poussé
 * par le serveur ({event:'state'} pour l'état de la planète).
 *
 * Le Coeur d'application AJAX (xnova-ajax.js) passe par ce canal dès qu'il est connecté et
 * retombe sur fetch() sinon : l'application reste utilisable si le service ws
 * est arrêté, et le POST classique des formulaires reste le repli sans JS.
 */
(function (window, document) {
	'use strict';

	var RECONNECT_MIN_MS = 1000;
	var RECONNECT_MAX_MS = 10000;
	// Le service ws abandonne lui-même un échange au bout de quelques secondes
	// (WS_API_TIMEOUT) : ce délai n'est qu'un dernier filet de sécurité, pour qu'une
	// réponse perdue libère la promesse au lieu de la laisser en attente à jamais.
	var REQUEST_TIMEOUT_MS = 20000;

	var socket = null;
	var connected = false;
	var attempt = 0;
	var nextId = 1;
	var pending = {};
	var banner = null;

	/** Adresse du canal : méta ws-url, sinon même hôte sur le port 8081. */
	function config() {
		var meta = document.querySelector('meta[name="ws-url"]');

		if (meta && meta.getAttribute('content')) {
			return meta.getAttribute('content');
		}

		var scheme = window.location.protocol === 'https:' ? 'wss:' : 'ws:';

		return scheme + '//' + window.location.hostname + ':8081/';
	}

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');

		return meta ? meta.getAttribute('content') : '';
	}

	/** Cliché d'état d'une réponse : `state` (écriture) ou `data` (GET /state). */
	function snapshotOf(json) {
		if (json.state && Object.keys(json.state).length > 0) {
			return json.state;
		}

		return json.data && typeof json.data === 'object' ? json.data : null;
	}

	function applyState(state) {
		if (state && window.XNova && window.XNova.live) {
			window.XNova.live.setState(state);
		}
	}

	function applyMessages(messages) {
		if (!window.XNova || !window.XNova.notify) {
			return;
		}

		(messages || []).forEach(function (message) {
			window.XNova.notify(message.type, message.text);
		});
	}

	/** Envoie une requête et renvoie une promesse sur la réponse de l'API. */
	function send(action, path, payload) {
		return new Promise(function (resolve, reject) {
			if (!connected || !socket) {
				reject({ code: 'ws_unavailable', message: 'Canal temps réel indisponible.' });

				return;
			}

			var id = nextId++;
			var entry = { resolve: resolve, reject: reject, timer: 0 };

			entry.timer = window.setTimeout(function () {
				if (pending[id] === entry) {
					delete pending[id];
					reject({ code: 'ws_timeout', message: 'Le serveur n\'a pas répondu à temps.' });
				}
			}, REQUEST_TIMEOUT_MS);

			pending[id] = entry;
			socket.send(JSON.stringify({ id: id, action: action, path: path || '', payload: payload || {} }));
		});
	}

	function handleEvent(json) {
		if (json.event === 'state') {
			applyState(snapshotOf(json));
			applyMessages(json.messages);

			return true;
		}

		if (json.event === 'chat') {
			document.dispatchEvent(new CustomEvent('xnova:chat', { detail: json }));

			return true;
		}

		if (json.event === 'expired') {
			window.location.reload();

			return true;
		}

		return false;
	}

	function handleMessage(raw) {
		var json = null;

		try {
			json = JSON.parse(raw);
		} catch (error) {
			return;
		}

		if (!json || handleEvent(json)) {
			return;
		}

		var waiter = pending[json.id];

		if (!waiter) {
			return;
		}

		delete pending[json.id];
		window.clearTimeout(waiter.timer);

		if (json.ok) {
			waiter.resolve(json);
		} else {
			waiter.reject(json.error || { code: 'error', message: 'Erreur inconnue.' });
		}
	}

	function showBanner(text) {
		if (!banner) {
			banner = document.createElement('div');
			banner.id = 'xnova-ws-banner';
			banner.className = 'alert alert-warning shadow-sm py-2 px-3 small position-fixed start-50 translate-middle-x';
			banner.style.top = '4.5rem';
			banner.style.zIndex = '1075';
			banner.setAttribute('role', 'status');
			document.body.appendChild(banner);
		}

		banner.textContent = text;
		banner.classList.remove('d-none');
	}

	function hideBanner() {
		if (banner) {
			banner.classList.add('d-none');
		}
	}

	function failPending(reason) {
		Object.keys(pending).forEach(function (id) {
			window.clearTimeout(pending[id].timer);
			pending[id].reject(reason);
			delete pending[id];
		});
	}

	function scheduleReconnect() {
		attempt++;
		var delay = Math.min(RECONNECT_MAX_MS, RECONNECT_MIN_MS * attempt);

		window.setTimeout(connect, delay);
	}

	function connect() {
		if (socket) {
			return;
		}

		try {
			socket = new window.WebSocket(config());
		} catch (error) {
			socket = null;
			scheduleReconnect();

			return;
		}

		socket.addEventListener('open', function () {
			connected = true;
			attempt = 0;
			hideBanner();
			document.body.setAttribute('data-xnova-ws', 'live');

			// Le jeton CSRF accompagne la session : il sert aux écritures relayées.
			var id = nextId++;
			pending[id] = {
				resolve: function (json) {
					applyState(snapshotOf(json));
				},
				reject: function () {}
			};
			socket.send(JSON.stringify({ id: id, action: 'hello', token: csrfToken() }));

			document.dispatchEvent(new CustomEvent('xnova:ws', { detail: { connected: true } }));
		});

		socket.addEventListener('message', function (event) {
			handleMessage(event.data);
		});

		socket.addEventListener('close', function () {
			connected = false;
			socket = null;
			document.body.setAttribute('data-xnova-ws', 'down');
			showBanner('Connexion temps réel interrompue — nouvelle tentative en cours…');
			failPending({ code: 'ws_closed', message: 'Connexion temps réel interrompue.' });
			document.dispatchEvent(new CustomEvent('xnova:ws', { detail: { connected: false } }));
			scheduleReconnect();
		});

		socket.addEventListener('error', function () {
			// La fermeture qui suit déclenche la reconnexion.
		});
	}

	/** Le canal n'est tenté que si la page l'autorise (WS_ENABLED côté serveur). */
	function enabled() {
		var meta = document.querySelector('meta[name="ws-enabled"]');

		return !!meta && meta.getAttribute('content') === '1';
	}

	function boot() {
		if (!document.body || !window.WebSocket || !enabled()) {
			return;
		}

		connect();
	}

	var XNova = window.XNova = window.XNova || {};
	XNova.ws = {
		connect: connect,
		url: config,
		enabled: enabled,
		isLive: function () {
			return connected;
		},
		get: function (path) {
			return send('get', path);
		},
		post: function (path, payload) {
			return send('post', path, payload);
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
