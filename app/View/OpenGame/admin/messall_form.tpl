<form action="{messall_action}" method="post" class="row g-2">
	<div class="col-sm-6">
		<label class="form-label small text-body-secondary mb-0" for="msg_all_subject">{adm_msg_all_subject}</label>
		<input class="form-control form-control-sm" id="msg_all_subject" type="text" name="temat" maxlength="100" required>
	</div>
	<div class="col-12">
		<label class="form-label small text-body-secondary mb-0" for="msg_all_text">{adm_msg_all_text}</label>
		<textarea class="form-control form-control-sm" id="msg_all_text" name="tresc" rows="8" required></textarea>
	</div>
	<div class="col-12">
		<button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-megaphone" aria-hidden="true"></i> {adm_msg_all_send}</button>
	</div>
	<input type="hidden" name="_token" value="{csrf_token}">
</form>
