<dl class="row mb-3">{player_info_rows}</dl>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<span class="{pal_state_class}">{pal_state_label}</span>
	<span class="text-body-secondary small{pal_ban_until_class}">{pal_ban_until_label} {pal_ban_until}</span>
</div>

<div class="row g-3">
	<div class="col-lg-6{pal_ban_form_class}">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_ban_title}</span>
				<button class="btn btn-sm btn-outline-danger" type="submit">{pal_ban_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="ban">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<div class="row g-2">
					<div class="col-3">
						<label class="form-label small" for="ban_days">{pal_ban_days}</label>
						<input class="form-control form-control-sm" id="ban_days" name="days" type="number" min="0" value="0">
					</div>
					<div class="col-3">
						<label class="form-label small" for="ban_hours">{pal_ban_hours}</label>
						<input class="form-control form-control-sm" id="ban_hours" name="hours" type="number" min="0" value="0">
					</div>
					<div class="col-3">
						<label class="form-label small" for="ban_minutes">{pal_ban_minutes}</label>
						<input class="form-control form-control-sm" id="ban_minutes" name="minutes" type="number" min="0" value="0">
					</div>
					<div class="col-3">
						<label class="form-label small" for="ban_seconds">{pal_ban_seconds}</label>
						<input class="form-control form-control-sm" id="ban_seconds" name="seconds" type="number" min="0" value="0">
					</div>
				</div>
				<div class="mt-2">
					<label class="form-label small" for="ban_reason">{pal_ban_reason}</label>
					<input class="form-control form-control-sm" id="ban_reason" name="reason" type="text" maxlength="200" value="">
				</div>
				<p class="small text-body-secondary mt-2 mb-0">{pal_ban_hint}</p>
			</div>
		</form>
	</div>

	<div class="col-lg-6{pal_unban_form_class}">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_unban_title}</span>
				<button class="btn btn-sm btn-outline-success" type="submit">{pal_unban_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="unban">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<p class="small text-body-secondary mb-0">{pal_unban_hint}</p>
			</div>
		</form>
	</div>

	<div class="col-lg-6{pal_delete_form_class}">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_delete_title}</span>
				<button class="btn btn-sm btn-outline-dark" type="submit">{pal_delete_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="delete">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<p class="small text-body-danger mb-0">{pal_delete_hint}</p>
			</div>
		</form>
	</div>

	<div class="col-lg-6{pal_restore_form_class}">
		<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
			<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
				<span class="fw-semibold">{pal_restore_title}</span>
				<button class="btn btn-sm btn-outline-success" type="submit">{pal_restore_apply}</button>
			</div>
			<div class="card-body">
				<input type="hidden" name="do" value="restore">
				<input type="hidden" name="player" value="{player_id}">
				<input type="hidden" name="tab" value="{player_return_tab}">
				<input type="hidden" name="_token" value="{player_token}">
				<p class="small text-body-secondary mb-0">{pal_restore_hint}</p>
			</div>
		</form>
	</div>

	<div class="col-lg-6">{pal_role_card}</div>
</div>
