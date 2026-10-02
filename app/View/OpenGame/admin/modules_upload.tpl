<!-- Téléversement d'un module : le formulaire, puis le rapport d'analyse.
	 Rendu par `ModulesController` dans le corps de la page des modules (marqueur
	 `{module_upload}`). Aucun balisage dans le contrôleur : il ne passe que des
	 marqueurs, et une ligne de rapport = `modules_report_row.tpl` rempli en boucle. -->
<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header d-flex align-items-center justify-content-between">
		<span class="fw-semibold">{mod_upload_title}</span>
		<span class="small text-body-secondary">{mod_upload_hint}</span>
	</div>
	<div class="card-body">
		<form class="row g-2 align-items-end" action="{module_action}" method="post" enctype="multipart/form-data">
			<input type="hidden" name="_token" value="{csrf_token}">
			<div class="col-12 col-md">
				<label class="form-label small text-body-secondary mb-0" for="mod_package">{mod_upload_title}</label>
				<input class="form-control form-control-sm" type="file" id="mod_package" name="package" accept=".zip,.tar,.gz,.tgz">
			</div>
			<div class="col-auto">
				<button class="btn btn-sm btn-outline-primary" type="submit" name="upload" value="1">{mod_upload_send}</button>
			</div>
		</form>

		<!-- Rapport d'analyse : montré après un téléversement, masqué autrement. -->
		<div class="mt-3 {report_class}" id="mod_report">
			<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
				<span class="fw-semibold">{mod_upload_report}</span>
				<span class="badge text-bg-secondary">{report_files} {mod_upload_files}</span>
				<span class="badge text-bg-light font-monospace" title="{mod_upload_fingerprint}">{report_sha256}</span>
			</div>

			<div class="alert alert-success py-2 mb-2 {report_clean_class}">
				<i class="bi bi-check2-circle" aria-hidden="true"></i> {mod_upload_clean}
			</div>
			<div class="alert alert-danger py-2 mb-2 {report_errors_class}">
				<i class="bi bi-x-octagon" aria-hidden="true"></i> {mod_upload_refusals}
			</div>
			<div class="alert alert-warning py-2 mb-2 {report_warnings_class}">
				<i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {mod_upload_alerts}
				<span class="d-block small mt-1">{mod_upload_warning_note}</span>
			</div>

			<ul class="list-group list-group-flush small mb-3">{report_rows}</ul>

			<form action="{module_action}" method="post">
				<input type="hidden" name="_token" value="{csrf_token}">
				<input type="hidden" name="name" value="{report_name}">
				<button class="btn btn-sm btn-primary {report_install_class}" type="submit" name="install" value="1">{mod_upload_install}</button>
				<button class="btn btn-sm btn-outline-warning {report_force_class}" type="submit" name="install" value="force">{mod_upload_force}</button>
			</form>
		</div>
	</div>
</div>
