<div class="card xnova-battle-side border-0">
	<div class="card-header py-2">
		<span class="fw-semibold">{side_title}</span>
		<span class="d-block small text-body-secondary">{side_tech}</span>
	</div>
	<div class="table-responsive{side_units_class}">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th scope="col">{sys_ship_type}</th>
					<th scope="col" class="text-end">{sys_ship_count}</th>
					<th scope="col" class="text-end">{sys_ship_weapon}</th>
					<th scope="col" class="text-end">{sys_ship_shield}</th>
					<th scope="col" class="text-end">{sys_ship_armour}</th>
				</tr>
			</thead>
			<tbody>{side_units}</tbody>
		</table>
	</div>
	<p class="small text-danger fw-semibold m-3{side_destroyed_class}"><i class="bi bi-x-octagon" aria-hidden="true"></i> {sys_destroyed}</p>
</div>
