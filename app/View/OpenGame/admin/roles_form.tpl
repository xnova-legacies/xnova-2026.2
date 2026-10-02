<form class="card xnova-panel border-0 shadow-sm mt-3" action="{role_action}" method="post">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<span class="fw-semibold">{role_form_title}</span>
		<button class="btn btn-sm btn-outline-primary{role_save_class}" type="submit" name="do" value="save">
			<i class="bi bi-check2" aria-hidden="true"></i> {acl_save}
		</button>
	</div>

	<div class="card-body">
		<input type="hidden" name="_token" value="{csrf_token}">
		<input type="hidden" name="id" value="{role_id}">

		<div class="alert alert-info d-flex align-items-center gap-2 mt-3{role_locked_class}" role="alert">
			<i class="bi bi-lock" aria-hidden="true"></i>
			<span>{acl_locked_note}</span>
		</div>

		<div class="row g-2">
			<div class="col-12 col-md-4">
				<label class="form-label" for="role_label">{acl_label}</label>
				<input class="form-control form-control-sm" type="text" id="role_label" name="label" value="{role_label}" maxlength="100" required{role_disabled}>
			</div>
			<div class="col-12 col-md-8">
				<label class="form-label" for="role_description">{acl_description}</label>
				<input class="form-control form-control-sm" type="text" id="role_description" name="description" value="{role_description}" maxlength="255"{role_disabled}>
			</div>
		</div>

		<div class="alert alert-warning d-flex align-items-center gap-2 mt-3{acl_all_class}" role="alert">
			<i class="bi bi-shield-check" aria-hidden="true"></i>
			<span>{acl_all_note}</span>
		</div>

		<div class="mt-3{acl_boxes_class}">
			<div class="fw-semibold mb-2">{acl_permissions}</div>
			{acl_permission_groups}
		</div>

		<div class="small text-body-secondary mt-3{acl_name_class}">
			{acl_name_hint} <span class="font-monospace">{role_name}</span>
		</div>
	</div>
</form>
