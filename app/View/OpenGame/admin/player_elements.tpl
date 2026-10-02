<div class="card border-0 shadow-sm h-100">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<span class="fw-semibold">{pal_element_title}</span>
		<button class="btn btn-sm btn-outline-primary" type="submit" form="el_form">{pal_apply}</button>
	</div>
	<div class="card-body">
		<div class="mb-3">{element_tabs}</div>
		<form class="d-flex flex-wrap align-items-end gap-2 mb-2{element_planet_class}" method="get" action="{player_action}">
			<input type="hidden" name="player" value="{player_id}">
			<input type="hidden" name="tab" value="{player_return_tab}">
			<input type="hidden" name="kind" value="{element_kind}">
			<div>
				<label class="form-label small mb-0" for="el_planet">{pal_target}</label>
				<select class="form-select form-select-sm" id="el_planet" name="planet">{planet_options}</select>
			</div>
			<button class="btn btn-sm btn-outline-secondary" type="submit">{pal_display}</button>
		</form>
		<form id="el_form" method="post" action="{player_action}">
			<input type="hidden" name="do" value="element">
			<input type="hidden" name="player" value="{player_id}">
			<input type="hidden" name="tab" value="{player_return_tab}">
			<input type="hidden" name="kind" value="{element_kind}">
			<input type="hidden" name="planet" value="{element_planet_value}">
			<input type="hidden" name="_token" value="{player_token}">
			<div class="table-responsive">
				<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
					<thead>
						<tr>
							<th>{pal_el_name}</th>
							<th class="text-end">{pal_el_current}</th>
							<th class="text-end">{pal_el_delta}</th>
						</tr>
					</thead>
					<tbody>{element_rows}</tbody>
				</table>
			</div>
			<div class="alert alert-warning{element_empty_class} mt-3 mb-0">{pal_no_element}</div>
			<p class="small text-body-secondary mt-2 mb-0">{pal_element_hint}</p>
		</form>
	</div>
</div>
