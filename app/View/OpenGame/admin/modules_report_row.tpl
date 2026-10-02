<!-- Une ligne du rapport d'analyse : un refus (`error`) ou une alerte (`warning`).
	 Le même gabarit sert aux deux, c'est la classe qui décide de la couleur — jamais
	 un `if` dans le gabarit. Les marqueurs viennent de `ModulesController::reportRow()`. -->
<li class="list-group-item d-flex align-items-start gap-2 {report_row_class}">
	<i class="bi {report_row_icon}" aria-hidden="true"></i>
	<span class="flex-grow-1">
		<span class="fw-semibold">{report_row_message}</span>
		<span class="d-block text-body-secondary font-monospace small {report_row_file_class}">{report_row_file}</span>
	</span>
	<span class="badge text-bg-secondary font-monospace">{report_row_code}</span>
</li>
