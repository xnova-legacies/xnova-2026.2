<form action="{reset_action}" method="post" class="d-flex flex-column gap-3">
	<input type="hidden" name="mode" value="reset">
	<input type="hidden" name="_token" value="{csrf_token}">
	<div class="alert alert-danger mb-0">
		<i class="bi bi-exclamation-octagon" aria-hidden="true"></i> {adm_rz_text}
	</div>
	<div class="form-check">
		<input class="form-check-input" id="reset_confirm" type="checkbox" name="confirm">
		<label class="form-check-label" for="reset_confirm">{adm_rz_conf}</label>
	</div>
	<div>
		<button class="btn btn-sm btn-danger" type="submit">{adm_rz_doit}</button>
	</div>
</form>
