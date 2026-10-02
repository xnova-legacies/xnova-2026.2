<div class="row g-2 mb-4">{player_stats}</div>

<form class="row g-2 align-items-end" method="get" action="{player_action}">
	<div class="col-auto">
		<label class="form-label small text-body-secondary mb-0" for="player_pick">{pal_pick}</label>
		<input class="form-control form-control-sm" id="player_pick" name="player" list="player_names"
			value="{player_value}" placeholder="{pal_pick_hint}" autocomplete="off">
		<datalist id="player_names">{player_options}</datalist>
	</div>
	<div class="col-auto">
		<button class="btn btn-sm btn-outline-primary" type="submit">{pal_pick_apply}</button>
	</div>
</form>
