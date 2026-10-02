<form action="{credit_action}" method="post" class="row g-3">
	<div class="col-12 col-lg-6">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-header fw-semibold">{cred_info}</div>
			<div class="card-body">
				<p class="small text-body-secondary mb-0">{cred_infotxt}</p>
			</div>
		</div>
	</div>
	<div class="col-12 col-lg-6">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-header fw-semibold">{cred_credit}</div>
			<div class="card-body">
				<dl class="row mb-0 small">
					<dt class="col-4">Raito</dt>
					<dd class="col-8">{cred_creat} / {cred_prog}</dd>
					<dt class="col-4">Chlorel</dt>
					<dd class="col-8">{cred_master} {cred_prog}</dd>
					<dt class="col-4">e-Zobar</dt>
					<dd class="col-8">{cred_design} / {cred_prog}</dd>
					<dt class="col-4">Flousedid</dt>
					<dd class="col-8 mb-0">{cred_web}</dd>
				</dl>
			</div>
		</div>
	</div>

	<div class="col-12">
		<div class="card border-0 shadow-sm">
			<div class="card-header fw-semibold">{cred_ext}</div>
			<div class="card-body d-flex flex-column gap-3">
				<div class="form-check">
					<input class="form-check-input" id="cred_frame" type="checkbox" name="ExtCopyFrame"{checked_extcopy}>
					<label class="form-check-label" for="cred_frame">{cred_added}</label>
				</div>
				<div class="row g-3">
					<div class="col-12 col-lg-6">
						<label class="form-label small text-body-secondary mb-0" for="cred_owner">{cred_name}</label>
						<textarea class="form-control form-control-sm" id="cred_owner" name="ExtCopyOwner" rows="5">{ext_owner}</textarea>
					</div>
					<div class="col-12 col-lg-6">
						<label class="form-label small text-body-secondary mb-0" for="cred_funct">{cred_funct}</label>
						<textarea class="form-control form-control-sm" id="cred_funct" name="ExtCopyFunct" rows="5">{ext_funct}</textarea>
					</div>
				</div>
			</div>
			<div class="card-footer">
				<input type="hidden" name="opt_save" value="1">
				<input type="hidden" name="_token" value="{csrf_token}">
				<button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save" aria-hidden="true"></i> {cred_save}</button>
			</div>
		</div>
	</div>
</form>
