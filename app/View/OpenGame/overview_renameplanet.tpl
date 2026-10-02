<h1 class="h4 mb-3">{rename_and_abandon_planet}</h1>

<form action="/game/overview?mode=renameplanet&pl={planet_id}" method="post" class="xnova-planet-form" data-ajax="/game/api/profil/rename" data-ajax-reload="1">
	<div class="card border-0 shadow-sm">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th colspan="3" class="xnova-section">{your_planet}</th>
				</tr>
				<tr>
					<th scope="col">{coords}</th>
					<th scope="col">{name}</th>
					<th scope="col">{functions}</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>{galaxy_galaxy}:{galaxy_system}:{galaxy_planet}</td>
					<td>{planet_name}</td>
					<td>
						<button type="submit" class="btn btn-sm btn-outline-danger" name="action" value="{colony_abandon}" data-ajax-skip>{colony_abandon}</button>
					</td>
				</tr>
				<tr>
					<td>{namer}</td>
					<td>
						<label class="visually-hidden" for="newname">{namer}</label>
						<input type="text" class="form-control form-control-sm" id="newname" name="newname" maxlength="20">
					</td>
					<td>
						<button type="submit" class="btn btn-sm btn-primary" name="action" value="{namer}">{namer}</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</form>