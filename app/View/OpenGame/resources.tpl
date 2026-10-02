<div class="card xnova-panel border-0 shadow-sm mb-3">
	<form action="" method="post" data-ajax="/game/api/resources/percent">
		<div class="card-header fw-semibold">{Production_of_resources_in_the_planet}</div>
		<div class="table-responsive">
			<table class="table table-sm align-middle mb-0">
				<thead>
					<tr>
						<th scope="col"></th>
						<th scope="col">{Metal}</th>
						<th scope="col">{Crystal}</th>
						<th scope="col">{Deuterium}</th>
						<th scope="col">{Energy}</th>
						<th scope="col"></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row">{Basic_income}</th>
						<td>{metal_basic_income}</td>
						<td>{crystal_basic_income}</td>
						<td>{deuterium_basic_income}</td>
						<td>{energy_basic_income}</td>
						<td></td>
					</tr>
					{resource_row}
					<tr>
						<th scope="row">{Stores_capacity}</th>
						<td>{metal_max}</td>
						<td>{crystal_max}</td>
						<td>{deuterium_max}</td>
						<td class="text-success">-</td>
						<td></td>
					</tr>
					<tr>
						<th scope="row">Total:</th>
						<td>{metal_total}</td>
						<td>{crystal_total}</td>
						<td>{deuterium_total}</td>
						<td>{energy_total}</td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="card-footer bg-transparent text-end">
			<button type="submit" class="btn btn-sm btn-primary" name="action" value="{Calcule}">{Calcule}</button>
		</div>
	</form>
</div>

{bonus_block}
</div>

<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">{Widespread_production}</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th scope="col"></th>
					<th scope="col">{Daily}</th>
					<th scope="col">{Weekly}</th>
					<th scope="col">{Monthly}</th>
				</tr>
			</thead>
			<tbody>
				<tr><th scope="row">{Metal}</th><td>{daily_metal}</td><td>{weekly_metal}</td><td>{monthly_metal}</td></tr>
				<tr><th scope="row">{Crystal}</th><td>{daily_crystal}</td><td>{weekly_crystal}</td><td>{monthly_crystal}</td></tr>
				<tr><th scope="row">{Deuterium}</th><td>{daily_deuterium}</td><td>{weekly_deuterium}</td><td>{monthly_deuterium}</td></tr>
			</tbody>
		</table>
	</div>
</div>

<div class="card xnova-panel border-0 shadow-sm">
	<div class="card-header fw-semibold">{Storage_state}</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<tbody>
				<tr>
					<th scope="row">{Metal}</th>
					<td class="text-nowrap">{metal_storage}</td>
					<td>
						<div class="progress xnova-progress" role="progressbar" aria-label="{Metal}" aria-valuenow="{metal_storage_pourcent}" aria-valuemin="0" aria-valuemax="100">
							<div class="progress-bar" style="width: {metal_storage_pourcent}%; background-color: {metal_storage_barcolor};"></div>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">{Crystal}</th>
					<td class="text-nowrap">{crystal_storage}</td>
					<td>
						<div class="progress xnova-progress" role="progressbar" aria-label="{Crystal}" aria-valuenow="{crystal_storage_pourcent}" aria-valuemin="0" aria-valuemax="100">
							<div class="progress-bar" style="width: {crystal_storage_pourcent}%; background-color: {crystal_storage_barcolor};"></div>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">{Deuterium}</th>
					<td class="text-nowrap">{deuterium_storage}</td>
					<td>
						<div class="progress xnova-progress" role="progressbar" aria-label="{Deuterium}" aria-valuenow="{deuterium_storage_pourcent}" aria-valuemin="0" aria-valuemax="100">
							<div class="progress-bar" style="width: {deuterium_storage_pourcent}%; background-color: {deuterium_storage_barcolor};"></div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>