<form method="post" class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-body d-flex flex-wrap align-items-center gap-2">
		<span class="fw-semibold">{stat_title}</span>
		<span class="small text-body-secondary">{stat_date}</span>
		<span class="ms-auto d-flex flex-wrap align-items-center gap-2">
			<span class="small text-body-secondary">{stat_show}</span>
			<select class="form-select form-select-sm xnova-stat-select" name="who" aria-label="{stat_show}" onchange="this.form.submit()">{who}</select>
			<span class="small text-body-secondary">{stat_by}</span>
			<select class="form-select form-select-sm xnova-stat-select" name="type" aria-label="{stat_by}" onchange="this.form.submit()">{type}</select>
		</span>
	</div>
</form>

<div class="card xnova-panel border-0 shadow-sm">
	{stat_page_bar}
	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle mb-0">
			{stat_header}
			{stat_values}
		</table>
	</div>
	{stat_pagination}
</div>