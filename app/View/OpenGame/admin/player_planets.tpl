<div class="table-responsive">
	<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
		<thead>
			<tr>
				<th>{pal_planet_name}</th>
				<th>{pal_coords}</th>
				<th>{pal_fields}</th>
				<th>{pal_metal}</th>
				<th>{pal_crystal}</th>
				<th>{pal_deuterium}</th>
			</tr>
		</thead>
		<tbody>{planet_rows}</tbody>
	</table>
</div>

<div class="alert alert-warning{planet_empty_class} mt-3 mb-0">{pal_no_planet}</div>

<div class="row g-3 mt-1{planet_forms_class}">
	<div class="col-lg-6">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_res_title}</span>
				<button class="btn btn-sm btn-outline-primary" type="submit">{pal_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="resources">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<div class="mb-2">
					<label class="form-label small" for="res_planet">{pal_target}</label>
					<select class="form-select form-select-sm" id="res_planet" name="planet">{planet_options}</select>
				</div>
				<div class="row g-2">
					<div class="col-4">
						<label class="form-label small" for="res_metal">{pal_metal}</label>
						<input class="form-control form-control-sm" id="res_metal" name="metal" type="text" value="0">
					</div>
					<div class="col-4">
						<label class="form-label small" for="res_crystal">{pal_crystal}</label>
						<input class="form-control form-control-sm" id="res_crystal" name="crystal" type="text" value="0">
					</div>
					<div class="col-4">
						<label class="form-label small" for="res_deuterium">{pal_deuterium}</label>
						<input class="form-control form-control-sm" id="res_deuterium" name="deuterium" type="text" value="0">
					</div>
				</div>
				<div class="form-text small">{pal_res_hint}</div>
				<div class="row g-2 mt-1">
					<div class="col-6">
						<label class="form-label small" for="res_fields">{pal_fields_max}</label>
						<input class="form-control form-control-sm" id="res_fields" name="field_max" type="number" min="0" value="" placeholder="{pal_unchanged}">
						<div class="form-text small">{pal_fields_max_hint}</div>
					</div>
					<div class="col-6">
						<label class="form-label small" for="res_diameter">{pal_diameter}</label>
						<input class="form-control form-control-sm" id="res_diameter" name="diameter" type="number" min="0" value="" placeholder="{pal_unchanged}">
						<div class="form-text small">{pal_diameter_hint}</div>
					</div>
				</div>
				<div class="mt-2">
					<label class="form-label small" for="res_name">{pal_rename}</label>
					<input class="form-control form-control-sm" id="res_name" name="planet_name" type="text" maxlength="20" value="" placeholder="{pal_unchanged}">
					<div class="form-text small">{pal_rename_hint}</div>
				</div>
				<div class="row g-2 mt-1 align-items-start">
					<div class="col-6">
						<button class="btn btn-sm btn-outline-warning" type="submit" name="empty_resources" value="1">{pal_empty_resources}</button>
						<div class="form-text small">{pal_empty_resources_hint}</div>
					</div>
					<div class="col-6">
						<button class="btn btn-sm btn-outline-danger" type="submit" name="clear_debris" value="1">{pal_clear_debris}</button>
						<div class="form-text small">{pal_debris_hint}</div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<div class="col-lg-6">
		{element_card}
	</div>
</div>
