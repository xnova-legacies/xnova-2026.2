<!-- Adresse inconnue : page **autonome** (son propre en-tête et ses feuilles de style).
     Elle est servie par le routeur, qui tourne avant le démarrage du jeu : ni les
     constantes ni les fonctions legacy n'existent à ce moment-là. On n'emprunte donc
     pas la coquille des pages du jeu — le routeur avait échoué là (`DEFAULT_SKINPATH`).
     Le Coeur d'application la remplit et la sert sous le code HTTP 404 : la page est jolie, le statut
     reste honnête pour les navigateurs et les robots. `noindex` : une adresse inconnue
     n'a rien à faire dans un moteur de recherche.
     Marqueurs `e404_*` — jamais un nom de clé de langue (le tableau de langue est
     fusionné en premier, et `+` garde la clé de gauche). -->
<!DOCTYPE html>
<html lang="{lang}" data-bs-theme="{theme}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark light">
<meta name="robots" content="noindex">
<title>{e404_title}</title>
<link rel="shortcut icon" href="/favicon.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/css/styles.css">
<link rel="stylesheet" href="/css/bootstrap-compat.css">
<script>
(function () {
	try {
		var theme = localStorage.getItem('xnova-theme');
		if (theme === 'light' || theme === 'dark') {
			document.documentElement.setAttribute('data-bs-theme', theme);
		}
	} catch (e) { /* stockage indisponible */ }
})();
</script>
</head>
<body class="xnova-body">
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-12 col-lg-8 col-xxl-6">
			<div class="card xnova-panel border-0 shadow-sm text-center">
				<div class="card-body py-5">
					<div class="display-5 mb-3 text-body-secondary" aria-hidden="true">
						<i class="bi bi-rocket-takeoff"></i>
					</div>
					<h1 class="h3 mb-3">{e404_title}</h1>
					<p class="text-body-secondary mb-4">{e404_text}</p>
					<a class="btn btn-outline-primary" href="{e404_home}">{e404_home_label}</a>
				</div>
			</div>
		</div>
	</div>
</div>
</body>
</html>
