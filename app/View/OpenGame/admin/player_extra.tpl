<div class="alert alert-warning{extra_empty_class} mb-0">{pal_no_planet}</div>

<div class="card border-0 shadow-sm mt-3{player_worlds_class}">
	<div class="card-header fw-semibold">{pal_planet_abandoned_title}</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
				<thead>
					<tr>
						<th>{pal_moon_th_id}</th>
						<th>{pal_moon_th_name}</th>
						<th>{pal_moon_th_position}</th>
						<th class="text-end">{pal_moon_th_action}</th>
					</tr>
				</thead>
				<tbody>
					{player_world_rows}
					<tr class="{pal_world_empty_class}">
						<td colspan="4" class="text-body-secondary">{pal_planet_abandoned_empty}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="card border-0 shadow-sm mt-3{player_moons_class}">
	<div class="card-header fw-semibold">{pal_moon_deleted_title}</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
				<thead>
					<tr>
						<th>{pal_moon_th_id}</th>
						<th>{pal_moon_th_name}</th>
						<th>{pal_moon_th_position}</th>
						<th class="text-end">{pal_moon_th_action}</th>
					</tr>
				</thead>
				<tbody>
					{player_moon_rows}
					<tr class="{pal_moon_empty_class}">
						<td colspan="4" class="text-body-secondary">{pal_moon_deleted_empty}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="row g-3{extra_forms_class}">
	<div class="col-lg-6">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header fw-semibold">{pal_moon_title}</div>
			<div class="card-body">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<div class="mb-2">
					<label class="form-label small" for="moon_planet">{pal_target}</label>
					<select class="form-select form-select-sm" id="moon_planet" name="planet">{planet_options}</select>
				</div>
				<div class="mb-2">
					<label class="form-label small" for="moon_name">{pal_moon_name}</label>
					<input class="form-control form-control-sm" id="moon_name" name="moon_name" type="text" maxlength="11" value="">
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-outline-primary" type="submit" name="do" value="moon_add">{pal_moon_add}</button>
					<button class="btn btn-sm btn-outline-danger" type="submit" name="do" value="moon_remove">{pal_moon_remove}</button>
				</div>
				<p class="small text-body-secondary mt-2 mb-0">{pal_moon_hint}</p>
			</div>
		</form>
	</div>

	<div class="col-lg-6">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_move_title}</span>
				<button class="btn btn-sm btn-outline-primary" type="submit">{pal_move_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="planet_move">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<div class="mb-2">
					<label class="form-label small" for="mv_planet">{pal_target}</label>
					<select class="form-select form-select-sm" id="mv_planet" name="planet">{planet_options}</select>
				</div>
				<div class="row g-2">
					<div class="col-4">
						<label class="form-label small" for="mv_galaxy">{pal_move_galaxy}</label>
						<input class="form-control form-control-sm" id="mv_galaxy" name="galaxy" type="number" min="1" value="">
					</div>
					<div class="col-4">
						<label class="form-label small" for="mv_system">{pal_move_system}</label>
						<input class="form-control form-control-sm" id="mv_system" name="system" type="number" min="1" value="">
					</div>
					<div class="col-4">
						<label class="form-label small" for="mv_position">{pal_move_position}</label>
						<input class="form-control form-control-sm" id="mv_position" name="position" type="number" min="1" value="">
					</div>
				</div>
				<p class="small text-body-secondary mt-2 mb-0">{pal_move_hint}</p>
			</div>
		</form>
	</div>
</div>
