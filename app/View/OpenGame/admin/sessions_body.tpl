<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
	<form class="row g-2 align-items-end" method="get" action="/back/overview">
		<input type="hidden" name="range" value="{ses_range_value}">
		<input type="hidden" name="type" value="{ses_type_value}">
		<div class="col-auto">
			<label class="form-label small text-body-secondary mb-0" for="ses_player">{ses_filter_player}</label>
			<input class="form-control form-control-sm" id="ses_player" name="player" list="ses_players"
				value="{ses_player_value}" placeholder="{ses_filter_player_hint}" autocomplete="off">
			<datalist id="ses_players">{ses_options}</datalist>
		</div>
		<div class="col-auto">
			<button class="btn btn-sm btn-outline-primary" type="submit">{ses_filter_apply}</button>
			<a class="btn btn-sm btn-outline-secondary{ses_filter_clear_class}" href="{ses_filter_href}">{ses_filter_clear}</a>
		</div>
	</form>
	<div class="d-flex flex-wrap gap-2">{ses_periods}</div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<div class="btn-group" role="group" aria-label="{ses_filter_type}">{ses_types}</div>
	<span class="badge text-bg-light border{ses_filtered_class}">{ses_filtered} :<span class="ms-0{ses_player_label_class}"> {ses_player_label} &middot;</span> {ses_type_label}</span>
</div>

<div class="row g-3">{ses_charts}</div>

<div class="card border-0 shadow-sm mt-3">
	<div class="card-header fw-semibold">{ses_recent_title}</div>
	{ses_page_bar}
	<div class="table-responsive">
		<table class="table table-sm table-hover xnova-overview-table mb-0">
			<thead>
				<tr>{ses_head}</tr>
			</thead>
			<tbody>
				<tr class="{ses_rows_empty}">
					<td colspan="7" class="text-body-secondary">{ses_no_session}</td>
				</tr>
				{ses_rows}
			</tbody>
		</table>
	</div>
	{ses_pagination}
</div>

<div class="card border-0 shadow-sm mt-3">
	<div class="card-header fw-semibold">
		{ses_totals_title}
		<span class="text-body-secondary small">&middot; {ses_range_label} &middot; {ses_summary_sessions} &middot; {ses_summary_time}</span>
	</div>
	<div class="table-responsive">
		<table class="table table-sm table-hover xnova-overview-table mb-0">
			<thead>
				<tr>
					<th>{ses_total_label_player}</th>
					<th>{ses_total_label_sessions}</th>
					<th>{ses_total_label_time}</th>
				</tr>
			</thead>
			<tbody>
				<tr class="{ses_totals_empty}">
					<td colspan="3" class="text-body-secondary">{ses_no_session}</td>
				</tr>
				{ses_totals}
			</tbody>
		</table>
	</div>
</div>
