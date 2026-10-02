/**
 * Case à cocher globale d'un tableau : elle coche ou décoche toutes les cases du
 * même groupe, et se remet à jour quand une ligne change (elle passe en état
 * indéterminé tant que la sélection est partielle).
 *
 * Le balisage suffit à l'activer : `data-xnova-check-all="groupe"` sur la case
 * de l'en-tête, `data-xnova-check="groupe"` sur chaque case de ligne. La case
 * globale ne porte **pas** de `name` : elle n'est jamais envoyée au serveur.
 *
 * Amélioration progressive : sans JavaScript, la case globale ne fait rien et le
 * formulaire continue de fonctionner ligne par ligne. Le groupe est cherché dans
 * le tableau qui contient la case, donc deux tableaux d'une même page ne se
 * mélangent pas.
 */
(function (window, document) {
	'use strict';

	/** Cases de ligne du groupe, dans le tableau qui porte la case globale. */
	function boxes(master) {
		var group = master.getAttribute('data-xnova-check-all');
		var scope = master.closest('table') || document;

		return Array.prototype.slice.call(scope.querySelectorAll('[data-xnova-check="' + group + '"]'));
	}

	/** Recale la case globale sur l'état des lignes. */
	function sync(master) {
		var list = boxes(master);
		var checked = list.filter(function (box) {
			return box.checked;
		}).length;

		master.checked = list.length > 0 && checked === list.length;
		master.indeterminate = checked > 0 && checked < list.length;
		master.disabled = list.length === 0;
	}

	function bind(root) {
		root.querySelectorAll('[data-xnova-check-all]').forEach(function (master) {
			if (master.getAttribute('data-checkall-ready') === '1') {
				return;
			}

			master.setAttribute('data-checkall-ready', '1');
			sync(master);

			master.addEventListener('change', function () {
				boxes(master).forEach(function (box) {
					box.checked = master.checked;
				});

				sync(master);
			});

			boxes(master).forEach(function (box) {
				box.addEventListener('change', function () {
					sync(master);
				});
			});
		});
	}

	function start() {
		bind(document);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}

	// Le contenu remplacé par softReload() (xnova-ajax.js) contient ses propres
	// cases : on les relie à leur case globale après chaque remplacement.
	document.addEventListener('xnova:reloaded', start);
}(window, document));
