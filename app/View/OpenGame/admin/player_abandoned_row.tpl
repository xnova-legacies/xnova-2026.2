<tr class="table-warning">
	<td>{world_id}</td>
	<td>{world_name}</td>
	<td class="small text-nowrap">{world_position}</td>
	<td class="text-end">
		<form method="post" action="{player_action}">
			<input type="hidden" name="do" value="planet_restore">
			<input type="hidden" name="player" value="{player_id}">
			<input type="hidden" name="tab" value="{player_return_tab}">
			<input type="hidden" name="_token" value="{player_token}">
			<input type="hidden" name="world" value="{world_id}">
			<button class="btn btn-sm btn-outline-success py-0 px-1" type="submit" title="{pal_planet_restore}">
				<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
				<span class="visually-hidden">{pal_planet_restore}</span>
			</button>
		</form>
	</td>
</tr>
