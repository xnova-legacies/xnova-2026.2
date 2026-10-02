<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">
		<i class="bi bi-person-plus" aria-hidden="true"></i> {declaration_title}
	</div>
	<form action="/game/add-declare" method="post">
		<input type="hidden" name="mode" value="addit">
		<div class="card-body">
			<p class="text-body-secondary">{declaration_intro}</p>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-6 col-form-label" for="dec1">{declaration_player1}</label>
				<div class="col-sm-6">
					<input class="form-control" type="text" name="dec1" id="dec1" value="">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-6 col-form-label" for="dec2">{declaration_player2}</label>
				<div class="col-sm-6">
					<input class="form-control" type="text" name="dec2" id="dec2" value="">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-6 col-form-label" for="dec3">{declaration_player3}</label>
				<div class="col-sm-6">
					<input class="form-control" type="text" name="dec3" id="dec3" value="">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-6 col-form-label" for="reason">{declaration_reason}</label>
				<div class="col-sm-6">
					<input class="form-control" type="text" name="reason" id="reason" value="">
				</div>
			</div>
		</div>
		<div class="card-footer text-end">
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-send" aria-hidden="true"></i> {adm_am_add}
			</button>
		</div>
	</form>
</div>
