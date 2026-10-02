<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
	<div class="btn-group" role="group">{module_states}</div>
</div>

{module_page_bar}

{module_upload}

<form class="row g-2 align-items-end mb-3" action="{module_action}" method="get">
	<input type="hidden" name="state" value="{module_state}">
	<input type="hidden" name="per_page" value="{module_per_page}">
	<div class="col-auto">
		<label class="form-label small text-body-secondary mb-0" for="mod_search">{mod_search}</label>
		<input type="search" class="form-control form-control-sm" id="mod_search" name="q" value="{module_search}" placeholder="{mod_search_hint}">
	</div>
	<div class="col-auto">
		<button class="btn btn-sm btn-outline-primary" type="submit">{mod_search_apply}</button>
	</div>
</form>

<form action="{module_action}" method="post">
	<input type="hidden" name="_token" value="{csrf_token}">

	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
			<thead>
				<tr>{module_head}</tr>
			</thead>
			<tbody>
				<tr class="{module_rows_empty_class}">
					<td colspan="7" class="text-body-secondary">{module_rows_empty_label}</td>
				</tr>
				{module_rows}
			</tbody>
		</table>
	</div>

	{module_pagination}
</form>

<div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0" role="alert">
	<i class="bi bi-info-circle" aria-hidden="true"></i>
	<span>{mod_note} {mod_archive_note}</span>
</div>
