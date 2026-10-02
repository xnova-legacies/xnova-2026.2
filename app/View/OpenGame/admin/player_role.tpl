<form class="card border-0 shadow-sm h-100" method="post" action="{player_action}">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<span class="fw-semibold">{pal_role_title}</span>
		<button class="btn btn-sm btn-outline-primary{pal_role_form_class}" type="submit">{pal_role_apply}</button>
	</div>

	<div class="card-body">
		<input type="hidden" name="do" value="role">
		<input type="hidden" name="player" value="{player_id}">
		<input type="hidden" name="tab" value="{player_return_tab}">
		<input type="hidden" name="_token" value="{player_token}">

		<div class="alert alert-info d-flex align-items-center gap-2 mb-0{pal_role_super_class}" role="alert">
			<i class="bi bi-shield-lock" aria-hidden="true"></i>
			<span>{pal_role_super_note}</span>
		</div>

		<div class="{pal_role_form_class}">
			<label class="form-label small" for="pal_role_select">{pal_role_label}</label>
			<select class="form-select form-select-sm" id="pal_role_select" name="role_id">
				<option value="0"{pal_role_none_selected}>{pal_role_none}</option>
				{pal_role_options}
			</select>

			<p class="small text-body-secondary mt-2 mb-0">
				{pal_role_hint} <a href="/back/roles">{pal_role_manage}</a>
			</p>
		</div>
	</div>
</form>
