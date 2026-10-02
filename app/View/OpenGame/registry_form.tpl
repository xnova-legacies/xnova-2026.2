<div class="card xnova-panel border-0 shadow-sm mb-3 mx-auto">
	<div class="card-header fw-semibold">
		<i class="bi bi-person-plus" aria-hidden="true"></i> {registry}
	</div>
	<form action="" method="post">
		<div class="card-body">
			<div class="text-center mb-3"><img src="/images/xnova-wordmark.png" width="280" height="88" class="img-fluid" alt="XNova"></div>
			<p class="text-body-secondary small">{form}</p>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="reg-character">{GameName}</label>
				<div class="col-sm-8">
					<input class="form-control" type="text" name="character" id="reg-character" size="20" maxlength="20"
						onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="reg-pass">{neededpass}</label>
				<div class="col-sm-8">
					<input class="form-control" type="password" name="passwrd" id="reg-pass" size="20" maxlength="20" autocomplete="new-password"
						onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="reg-email">{E-Mail}</label>
				<div class="col-sm-8">
					<input class="form-control" type="text" name="email" id="reg-email" size="20" maxlength="40" autocomplete="email"
						onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="reg-planet">{MainPlanet}</label>
				<div class="col-sm-8">
					<input class="form-control" type="text" name="planet" id="reg-planet" size="20" maxlength="20"
						onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
				</div>
			</div>
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label" for="reg-sex">{Sex}</label>
				<div class="col-sm-8">
					<select class="form-select" name="sex" id="reg-sex">
						<option value="">{Undefined}</option>
						<option value="M">{Male}</option>
						<option value="F">{Female}</option>
					</select>
				</div>
			</div>
			<div class="row mb-3 align-items-center{secu_hidden}">
				<label class="col-sm-4 col-form-label" for="reg-secu">{code_secu}</label>
				<div class="col-sm-8">
					<span class="me-2">{secu_nombre1} + {secu_nombre2} =</span>
					<input class="form-control d-inline-block w-auto" name="secu" id="reg-secu" maxlength="3" type="text">
				</div>
			</div>
			<div class="form-check">
				<input class="form-check-input" type="checkbox" name="rgt" id="reg-rgt">
				<label class="form-check-label" for="reg-rgt">{accept}</label>
			</div>
		</div>
		<div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
			<a class="btn btn-outline-secondary" href="/front/login">
				<i class="bi bi-arrow-left" aria-hidden="true"></i> {Back}
			</a>
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-check2-circle" aria-hidden="true"></i> {signup}
			</button>
		</div>
	</form>
</div>
