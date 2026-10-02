<tr>
	<td class="fw-semibold">{username}</td>
	<td class="text-nowrap">
		<a class="btn btn-sm btn-outline-secondary" href="/game/profil/messages?mode=write&amp;id={id}" title="{write_a_messege}">
			<i class="bi bi-envelope" aria-hidden="true"></i> <span class="visually-hidden">{write_a_messege}</span>
		</a>
		<a class="btn btn-sm btn-outline-secondary" href="/game/buddy?a=2&amp;u={id}" title="{buddy_request}" onclick="var w=window.open(this.href,'Buddy','resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=550,height=360,top=0,left=0'); if(w){w.focus();} return false;">
			<i class="bi bi-person-plus" aria-hidden="true"></i> <span class="visually-hidden">{buddy_request}</span>
		</a>
	</td>
	<td>{ally_name}</td>
	<td>{planet_name}</td>
	<td class="text-nowrap"><a href="/game/galaxy?mode=3&amp;galaxy={galaxy}&amp;system={system}">{coordinated}</a></td>
	<td class="text-end"><a href="/game/stat?start={rank}">{rank}</a></td>
</tr>
