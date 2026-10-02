<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">
		<i class="bi bi-cup-hot" aria-hidden="true"></i> {Vaccation_mode} {vacation_until}
	</div>
	<form action="/game/profil/options?mode=exit" method="post" data-ajax="/game/api/options/vacation" data-ajax-reload="1">
		<div class="card-body">
			<div class="row align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-exit-vacation" title="{vacations_tip}">
						{exit_vacations} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="exit_vacation" id="opt-exit-vacation"{opt_modev_exit}>
					</div>
				</div>
			</div>
		</div>
		<div class="card-footer text-end">
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-check2-circle" aria-hidden="true"></i> {save_settings}
			</button>
		</div>
	</form>
</div>
