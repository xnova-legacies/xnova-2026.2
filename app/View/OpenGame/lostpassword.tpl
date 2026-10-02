<div class="card xnova-panel border-0 shadow-sm mb-3 mx-auto">
	<div class="card-header fw-semibold">
		<i class="bi bi-key" aria-hidden="true"></i> {ResetPass}
	</div>
	<form action="?action=1" method="post">
		<div class="card-body">
			<p class="text-body-secondary small">{TextPass1} {servername} {TextPass2}</p>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="lp-pseudo">{pseudo}</label>
				<div class="col-sm-8">
					<input class="form-control" type="text" name="pseudo" id="lp-pseudo" maxlength="30" value="" autocomplete="username">
				</div>
			</div>
			<div class="row align-items-center">
				<label class="col-sm-4 col-form-label" for="lp-email">{email}</label>
				<div class="col-sm-8">
					<input class="form-control" type="text" name="email" id="lp-email" maxlength="50" value="" autocomplete="email">
				</div>
			</div>
		</div>
		<div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
			<a class="btn btn-outline-secondary" href="/front/login">
				<i class="bi bi-arrow-left" aria-hidden="true"></i> {Back}
			</a>
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-send" aria-hidden="true"></i> {ButtonSendPass}
			</button>
		</div>
	</form>
</div>
