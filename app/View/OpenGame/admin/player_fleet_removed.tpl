<tr class="table-warning">
	<td>{fleet_id}</td>
	<td>{fleet_mission}</td>
	<td class="small">{fleet_from} &rarr; {fleet_to}</td>
	<td class="small text-nowrap">{fleet_end}</td>
	<td class="text-end">
		<form method="post" action="{player_action}">
			<input type="hidden" name="do" value="fleet_restore">
			<input type="hidden" name="player" value="{player_id}">
			<input type="hidden" name="tab" value="{player_return_tab}">
			<input type="hidden" name="_token" value="{player_token}">
			<input type="hidden" name="fleet" value="{fleet_id}">
			<button class="btn btn-sm btn-outline-success py-0 px-1" type="submit" title="{pal_fleet_restore}">
				<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
				<span class="visually-hidden">{pal_fleet_restore}</span>
			</button>
		</form>
	</td>
</tr>
