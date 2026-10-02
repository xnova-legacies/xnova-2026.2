<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
	<form class="row g-2 align-items-end" method="get" action="/back/actions">
		<input type="hidden" name="days" value="{act_days_value}">
		<input type="hidden" name="kind" value="{act_kind_value}">
		<div class="col-auto">
			<label class="form-label small text-body-secondary mb-0" for="act_player">{act_filter_player}</label>
			<input class="form-control form-control-sm" id="act_player" name="player" list="act_players"
				value="{act_player_value}" placeholder="{act_filter_player_hint}" autocomplete="off">
			<datalist id="act_players">{act_options}</datalist>
		</div>
		<div class="col-auto">
			<button class="btn btn-sm btn-outline-primary" type="submit">{act_filter_apply}</button>
			<a class="btn btn-sm btn-outline-secondary{act_filter_clear_class}" href="{act_filter_href}">{act_filter_clear}</a>
		</div>
	</form>
	<div class="d-flex flex-wrap gap-2">{act_days}</div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<div class="btn-group" role="group" aria-label="{act_filter_kind}">{act_kinds}</div>
	<span class="badge text-bg-light border{act_filtered_class}">{act_filtered} :<span class="ms-0{act_player_label_class}"> {act_player_label} &middot;</span> {act_kind_label} &middot; {act_days_label}</span>
</div>

<div class="card border-0 shadow-sm">
	<div class="card-header fw-semibold">{act_recent_title}</div>
	{act_page_bar}
	<div class="table-responsive">
		<table class="table table-sm table-hover xnova-overview-table mb-0">
			<thead>
				<tr>{act_head}</tr>
			</thead>
			<tbody>
				<tr class="{act_rows_empty}">
					<td colspan="5" class="text-body-secondary">{act_no_action}</td>
				</tr>
				{act_rows}
			</tbody>
		</table>
	</div>
	{act_pagination}
</div>

<div class="card border-0 shadow-sm mt-3">
	<div class="card-header fw-semibold">{act_totals_title}</div>
	<div class="table-responsive">
		<table class="table table-sm table-hover xnova-overview-table mb-0">
			<thead>
				<tr>
					<th>{act_col_action}</th>
					<th>{act_col_kind}</th>
					<th>{act_col_nb}</th>
					<th>{act_col_last}</th>
				</tr>
			</thead>
			<tbody>
				<tr class="{act_totals_empty}">
					<td colspan="4" class="text-body-secondary">{act_no_action}</td>
				</tr>
				{act_totals}
			</tbody>
		</table>
	</div>
</div>
