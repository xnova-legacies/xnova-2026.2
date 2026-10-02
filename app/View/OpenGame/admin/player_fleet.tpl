<div class="card border-0 shadow-sm mb-3">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<span class="fw-semibold">{pal_fleet} #{fleet_id} &middot; {fleet_mission}</span>
		<span class="small text-body-secondary">{fleet_start} &rarr; {fleet_end}</span>
	</div>
	<div class="card-body">
		<div class="row g-2 small">
			<div class="col-md-6"><span class="text-body-secondary">{pal_fleet_from} :</span> {fleet_from}</div>
			<div class="col-md-6"><span class="text-body-secondary">{pal_fleet_to} :</span> {fleet_to}</div>
			<div class="col-12"><span class="text-body-secondary">{pal_fleet_units} :</span> {fleet_units}</div>
		</div>

		<div class="row g-2 mt-1">
			<div class="col-lg-4{fleet_recall_class}">
				<form method="post" action="{player_action}">
					<input type="hidden" name="do" value="fleet_recall">
					<input type="hidden" name="player" value="{player_id}">
					<input type="hidden" name="tab" value="{player_return_tab}">
					<input type="hidden" name="_token" value="{player_token}">
					<input type="hidden" name="fleet" value="{fleet_id}">
					<button class="btn btn-sm btn-outline-primary w-100" type="submit">{pal_fleet_recall}</button>
				</form>
			</div>
			<div class="col-lg-4">
				<form class="row g-2" method="post" action="{player_action}">
					<input type="hidden" name="do" value="fleet_units">
					<input type="hidden" name="player" value="{player_id}">
					<input type="hidden" name="tab" value="{player_return_tab}">
					<input type="hidden" name="_token" value="{player_token}">
					<input type="hidden" name="fleet" value="{fleet_id}">
					<div class="col-7">
						<select class="form-select form-select-sm" name="unit">{fleet_unit_options}</select>
					</div>
					<div class="col-3">
						<input class="form-control form-control-sm" name="quantity" type="number" min="1" value="1">
					</div>
					<div class="col-2">
						<button class="btn btn-sm btn-outline-secondary w-100" type="submit" title="{pal_fleet_remove_units}">&minus;</button>
					</div>
				</form>
			</div>
			<div class="col-lg-4">
				<form method="post" action="{player_action}">
					<input type="hidden" name="do" value="fleet_delete">
					<input type="hidden" name="player" value="{player_id}">
					<input type="hidden" name="tab" value="{player_return_tab}">
					<input type="hidden" name="_token" value="{player_token}">
					<input type="hidden" name="fleet" value="{fleet_id}">
					<button class="btn btn-sm btn-outline-danger w-100" type="submit">{pal_fleet_delete}</button>
				</form>
			</div>
		</div>
	</div>
</div>
