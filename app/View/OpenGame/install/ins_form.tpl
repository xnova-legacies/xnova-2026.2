<p>{ins_tx_inst1}<br>{ins_tx_inst2}<br>{ins_tx_inst3}</p>

<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-host">{ins_form_server}</label>
	<div class="col-sm-8"><input class="form-control" type="text" name="host" id="ins-host" value="{ins_db_host}"></div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-db">{ins_form_db}</label>
	<div class="col-sm-8"><input class="form-control" type="text" name="db" id="ins-db" value="{ins_db_name}"></div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-prefix">{ins_form_prefix}</label>
	<div class="col-sm-8"><input class="form-control" type="text" name="prefix" id="ins-prefix" value="{ins_db_prefix}"></div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-user">{ins_form_login}</label>
	<div class="col-sm-8"><input class="form-control" type="text" name="user" id="ins-user" value="{ins_db_user}" autocomplete="username"></div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-pass">{ins_form_pass}</label>
	<div class="col-sm-8"><input class="form-control" type="password" name="password" id="ins-pass" value="{ins_db_password}" autocomplete="current-password"></div>
</div>

<div class="text-end">
	<button type="button" class="btn btn-primary" onclick="submit();">
		<i class="bi bi-check2" aria-hidden="true"></i> {ins_btn_inst}
	</button>
</div>
