<div class="card border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">
		<i class="bi bi-diagram-3" aria-hidden="true"></i> {imperium_vision}
	</div>
	<div class="table-responsive">
		<table class="table table-sm table-bordered align-middle mb-0 xnova-imperium">
			<tbody>
				<tr>
					<th class="xnova-label"></th>
					{file_images}
				</tr>
				<tr>
					<th class="xnova-label">{name}</th>
					{file_names}
				</tr>
				<tr>
					<th class="xnova-label">{coordinates}</th>
					{file_coordinates}
				</tr>
				<tr>
					<th class="xnova-label">{fields}</th>
					{file_fields}
				</tr>
				<tr>
					<td class="xnova-section" colspan="{mount}">{resources}</td>
				</tr>
				<tr>
					<th class="xnova-label">{metal}</th>
					{file_metal}
				</tr>
				<tr>
					<th class="xnova-label">{crystal}</th>
					{file_crystal}
				</tr>
				<tr>
					<th class="xnova-label">{deuterium}</th>
					{file_deuterium}
				</tr>
				<tr>
					<th class="xnova-label">{energy}</th>
					{file_energy}
				</tr>
				<tr>
					<td class="xnova-section" colspan="{mount}">{buildings}</td>
				</tr>
				{building_row}
				<tr>
					<td class="xnova-section" colspan="{mount}">{investigation}</td>
				</tr>
				{technology_row}
				<tr>
					<td class="xnova-section" colspan="{mount}">{ships}</td>
				</tr>
				{fleet_row}
				<tr>
					<td class="xnova-section" colspan="{mount}">{defense}</td>
				</tr>
				{defense_row}
			</tbody>
		</table>
	</div>
</div>
