<div class="container-fluid xnova-shell">
	<div class="row g-3 g-xl-4 text-start">
		<aside class="col-12 col-lg-3 col-xxl-2 xnova-sidebar">{admin_menu}</aside>
		<!-- `col-lg` (et non `col-lg-9`) : le corps prend toute la largeur qui reste.
		     L'écart avec le menu vient de la gouttière de la ligne (`g-3 g-xl-4`), pas
		     d'une largeur fixe — le panneau respire sans coller au menu. -->
		<main class="col-12 col-lg xnova-content xnova-admin">
			<div class="d-flex flex-column gap-3">{admin_body}</div>
		</main>
	</div>
</div>
