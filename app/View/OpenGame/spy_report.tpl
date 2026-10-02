<div class="card xnova-panel border-0 shadow-sm xnova-spy-report">
	<div class="card-header d-flex flex-wrap align-items-center gap-2">
		<span class="fw-semibold"><i class="bi bi-binoculars" aria-hidden="true"></i> {spy_title}</span>
		<span class="badge text-bg-secondary">{spy_target} {spy_coordinates}</span>
		<span class="badge text-bg-secondary ms-auto">{spy_date}</span>
	</div>
	<div class="card-body">
		{spy_sections}
		<p class="small text-body-secondary mb-0 mt-2">{spy_hint}</p>
	</div>
	<div class="card-footer d-flex flex-wrap align-items-center gap-2">
		<span class="badge text-bg-{spy_fate_variant}">{spy_fate}</span>
		<a class="btn btn-sm btn-outline-danger ms-auto" href="{spy_attack_url}">{spy_attack_label}</a>
	</div>
</div>
