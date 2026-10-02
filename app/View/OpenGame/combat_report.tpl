<div class="card xnova-battle-report border-0 shadow-sm">
	<div class="card-header fw-semibold d-flex align-items-center gap-2">
		<i class="bi bi-crosshair" aria-hidden="true"></i>
		<span>{report_title}</span>
		<span class="badge {report_result_class} ms-auto">{report_result}</span>
	</div>
	<div class="card-body">
		{report_rounds}

		<div class="{report_rapidfire_class}">
			<h3 class="xnova-form-section">{sys_rf_title}</h3>
			{report_rapidfire_list}
		</div>

		<div class="alert {report_result_alert} xnova-battle-conclusion mb-3">
			<strong>{report_conclusion}</strong>
		</div>

		{report_forces}

		<h3 class="xnova-form-section">{sys_battle_summary}</h3>
		<ul class="list-unstyled xnova-battle-summary mb-0">
			<li class="text-danger"><i class="bi bi-dash-circle" aria-hidden="true"></i> {report_loss_attacker}</li>
			<li class="text-success"><i class="bi bi-dash-circle" aria-hidden="true"></i> {report_loss_defender}</li>
			<li class="xnova-battle-plunder{report_plunder_class}"><i class="bi bi-box-seam" aria-hidden="true"></i> {report_plunder}</li>
			<li><i class="bi bi-gem" aria-hidden="true"></i> {report_debris}</li>
			<li class="{report_moon_class}"><i class="bi bi-moon-stars" aria-hidden="true"></i> {report_moon}</li>
			<li class="text-success {report_moon_built_class}"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i> <strong>{report_moon_built}</strong></li>
		</ul>

		<p class="small text-body-secondary mb-0 mt-3">{report_sim}</p>
	</div>
</div>
