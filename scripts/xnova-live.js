/**
 * Affichage temps réel des ressources.
 *
 * Le serveur reste la source de vérité : /game/api/state est appelé toutes les
 * POLL_MS pour resynchroniser, et entre deux appels le navigateur interpole
 * localement avec le débit horaire fourni (planets.rates), sans requête.
 *
 * Les valeurs affichées suivent les mêmes règles que la barre de navigation
 * legacy : séparateur de milliers ".", rouge au-delà du stockage. Le stockage
 * étendu (`resources.*_cap` = `*_max` x `MAX_OVERFLOW`, comme dans
 * ProductionService) ne plafonne que la PRODUCTION interpolée : quand le
 * serveur détient davantage (pillage, prime du marchand), c'est sa valeur qui
 * s'affiche, exactement comme la page rendue par le serveur.
 */
(function (window, document) {
	'use strict';

	var POLL_MS = 1000;
	var TICK_MS = 1000;

	var state = null;   // dernier etat recu du serveur
	var baseTs = 0;     // horodatage client au moment de la synchro
	var lastRevision = null;   // empreinte du contenu rendu par le serveur

	function format(value) {
		var rounded = Math.floor(value);

		return String(rounded).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function setPill(name, value, max) {
		var element = document.getElementById('xnova-res-' + name);

		if (!element) {
			return;
		}

		var text = format(value);
		element.innerHTML = value > max ? '<span class="text-danger">' + text + '</span>' : text;
	}

	/**
	 * Valeur affichée entre deux synchronisations : la production est interpolée
	 * depuis la dernière valeur reçue, sans dépasser le stockage.
	 *
	 * Le plafond ne s'applique qu'à cette production : il ne doit jamais faire
	 * afficher MOINS que la valeur stockée par le serveur (un pillage ou une prime
	 * du marchand déposent des ressources au-delà du stockage, et la barre de
	 * navigation les affiche en rouge).
	 */
	function interpolated(base, rate, seconds, cap) {
		var ceiling = cap > base ? cap : base;
		var value = base + rate * seconds;

		return value > ceiling ? ceiling : value;
	}

	function setEnergy(energy) {
		var element = document.getElementById('xnova-res-energy');

		if (!element) {
			return;
		}

		var total = energy.max + energy.used;
		var text = format(total) + '/' + format(energy.max);
		element.innerHTML = total < 0 ? '<span class="text-danger">' + text + '</span>' : text;
	}

	function setNotification(id, count) {
		var badge = document.getElementById(id);

		if (!badge) {
			return;
		}

		badge.textContent = count > 0 ? String(count) : '';
		badge.classList.toggle('d-none', count <= 0);
	}

	function render() {
		if (!state || !state.planet) {
			return;
		}

		var planet = state.planet;
		var resources = planet.resources;
		var rates = planet.rates;
		// Le serveur fournit un debit horaire deja multiplie par resource_multiplier :
		// l'interpolation reste juste entre deux synchros.
		var elapsed = Math.max(0, (Date.now() - baseTs) / 1000) / 3600;

		// Le stockage plafonne la production (ProductionService) : le compteur
		// s'arrete au plafond, sans jamais redescendre sous la valeur du serveur.
		setPill('metal', interpolated(resources.metal, rates.metal, elapsed, resources.metal_cap), resources.metal_max);
		setPill('crystal', interpolated(resources.crystal, rates.crystal, elapsed, resources.crystal_cap), resources.crystal_max);
		setPill('deuterium', interpolated(resources.deuterium, rates.deuterium, elapsed, resources.deuterium_cap), resources.deuterium_max);
		setEnergy(planet.energy);
		var user = state.user || {};
		setNotification('xnova-notif-message', user.new_message || 0);
		setNotification('xnova-notif-buddy', user.buddy_requests || 0);
	}

	/** Une saisie en cours ne doit pas etre interrompue par un rafraichissement. */
	function isEditing() {
		var active = document.activeElement;

		if (!active || !active.tagName) {
			return false;
		}

		var tag = active.tagName.toLowerCase();

		return (tag === 'input' || tag === 'select' || tag === 'textarea') && !active.disabled;
	}

	/**
	 * Les rendus de construction sont rendues par le serveur (prix, boutons) :
	 * quand ses capacites changent (une ressource vient d'atteindre le prix),
	 * on recharge le contenu pour que les boutons s'activent sans F5.
	 */
	function maybeRefreshContent(revision) {
		if (!revision || !document.querySelector('[data-xnova-autorefresh]')) {
			return;
		}

		if (lastRevision === null) {
			lastRevision = revision;

			return;
		}

		if (lastRevision === revision || isEditing()) {
			return;
		}

		lastRevision = revision;

		if (window.XNova.softReload) {
			window.XNova.softReload();
		}
	}

	function setState(next) {
		state = next;
		baseTs = Date.now();
		render();

		document.dispatchEvent(new CustomEvent('xnova:state', { detail: state }));
		maybeRefreshContent(state.revision);
	}

	/** Le canal temps réel est-il activé par le serveur (méta ws-enabled) ? */
	function channelEnabled() {
		return !!(window.XNova.ws && window.XNova.ws.enabled && window.XNova.ws.enabled());
	}

	/**
	 * Sondage HTTP périodique : uniquement quand le canal n'est pas activé.
	 * Avec WS_ENABLED=1, le navigateur ne fait plus aucun appel périodique à
	 * /game/api/state : l'état arrive par les événements poussés.
	 */
	function poll() {
		if (document.hidden || channelEnabled()) {
			return;
		}

		window.XNova.get('/state').then(function (json) {
			setState(json.data);
		}).catch(function () {
			// On conserve le dernier etat connu et on retente au cycle suivant.
		});
	}

	/**
	 * Resynchronisation ponctuelle (retour sur l'onglet) : elle passe par le canal
	 * temps réel quand il est connecté, sinon par l'API JSON.
	 */
	function sync() {
		if (document.hidden) {
			return;
		}

		window.XNova.get('/state').then(function (json) {
			setState(json.data);
		}).catch(function () {
			// On conserve le dernier etat connu.
		});
	}

	function boot() {
		if (!document.getElementById('xnova-res-metal') || !window.XNova) {
			return;
		}

		sync();
		window.setInterval(poll, POLL_MS);
		window.setInterval(render, TICK_MS);

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				sync();
			}
		});
	}

	var XNova = window.XNova = window.XNova || {};
	XNova.live = {
		setState: setState,
		sync: sync,
		poll: poll,
		getState: function () {
			return state;
		}
	};

	document.addEventListener('DOMContentLoaded', boot);
})(window, document);
