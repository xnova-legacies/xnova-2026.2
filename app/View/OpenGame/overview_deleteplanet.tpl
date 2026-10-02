<h1 class="h4 mb-3">{ov_rena_dele}</h1>

<form action="/game/overview?mode=renameplanet&pl={planet_id}" method="post" class="xnova-planet-form">
	<div class="card border-0 shadow-sm">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th colspan="3" class="xnova-section">{security_query}</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="3">{confirm_planet_delete} <strong>{galaxy_galaxy}:{galaxy_system}:{galaxy_planet}</strong> {confirmed_with_password}</td>
				</tr>
				<tr>
					<td class="xnova-label">{password}</td>
					<td>
						<label class="visually-hidden" for="pw">{password}</label>
						<input type="password" class="form-control form-control-sm" id="pw" name="pw" autocomplete="current-password">
					</td>
					<td>
						<button type="submit" class="btn btn-sm btn-danger" name="action" value="{ov_delete_confirm}">{ov_delete_confirm}</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<input type="hidden" name="delete_colony" value="1">
	<input type="hidden" name="deleteid" value="{planet_id}">
</form>