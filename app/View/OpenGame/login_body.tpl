<div class="card xnova-login-card border-0 shadow-sm mb-3 mx-auto">
	<div class="card-header fw-semibold text-center">
		<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> {Login}
	</div>
	<form method="post" class="mb-0">
		<div class="card-body">
			<div class="mb-3">
				<label class="form-label" for="login-username">{User_name}</label>
				<input class="form-control" type="text" name="username" id="login-username" value="" autocomplete="username" autofocus>
			</div>
			<div class="mb-3">
				<label class="form-label" for="login-password">{Password}</label>
				<input class="form-control" type="password" name="password" id="login-password" value="" autocomplete="current-password">
			</div>
			<div class="form-check mb-2">
				<input class="form-check-input" type="checkbox" name="rememberme" id="login-remember">
				<label class="form-check-label" for="login-remember">{Remember_me}</label>
			</div>
			<a class="small" href="/front/lostpassword">{PasswordLost}</a>
		</div>
		<div class="card-footer text-center">
			<button type="submit" class="btn btn-primary w-100">
				<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> {Login}
			</button>
		</div>
	</form>
</div>

<div class="card xnova-login-card border-0 shadow-sm mx-auto text-center">
	<div class="card-body">
		<h2 class="h5">{log_welcome} {servername}</h2>
		<p class="text-body-secondary small">{servername} {log_desc} {servername}.</p>
		<a class="btn btn-success" href="/game/reg">
			<i class="bi bi-person-plus" aria-hidden="true"></i> {log_toreg}
		</a>
		<hr>
		<div class="row row-cols-1 row-cols-sm-3 g-2 small">
			<div class="col">
				<div class="text-body-secondary">{log_online}</div>
				<div class="fw-semibold text-success">{online_users}</div>
			</div>
			<div class="col">
				<div class="text-body-secondary">{log_lastreg}</div>
				<div class="fw-semibold">{last_user}</div>
			</div>
			<div class="col">
				<div class="text-body-secondary">{log_numbreg}</div>
				<div class="fw-semibold">{users_amount}</div>
			</div>
		</div>
		<hr>
		<div class="d-flex flex-wrap justify-content-center gap-3 small">
			<a href="/game/reg">{log_reg}</a>
			<a href="{forum_url}">Forum</a>
			<a href="/front/contact">Contact</a>
			<a href="/front/credit">{log_cred}</a>
		</div>
	</div>
</div>
