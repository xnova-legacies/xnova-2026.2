{player_fleet_rows}

<div class="alert alert-info{player_fleet_empty_class} mb-0">{pal_fleet_empty}</div>

<div class="card border-0 shadow-sm mt-3{player_fleet_removed_class}">
	<div class="card-header fw-semibold">{pal_fleet_removed_title}</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
				<thead>
					<tr>
						<th>{pal_fleet_th_id}</th>
						<th>{pal_fleet_th_mission}</th>
						<th>{pal_fleet_th_route}</th>
						<th>{pal_fleet_th_end}</th>
						<th class="text-end">{pal_fleet_th_action}</th>
					</tr>
				</thead>
				<tbody>
					{player_fleet_removed_rows}
				</tbody>
			</table>
		</div>
	</div>
</div>
