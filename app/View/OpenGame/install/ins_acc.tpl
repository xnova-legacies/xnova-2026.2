<p>{ins_tx_acc1}<br>{ins_tx_acc2}</p>

<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-adm-user">{ins_acc_user}</label>
	<div class="col-sm-8">
		<input class="form-control" type="text" name="adm_user" id="ins-adm-user" value="{ins_admin_user}" maxlength="20"
			onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
	</div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-adm-pass">{ins_acc_pass}</label>
	<div class="col-sm-8">
		<input class="form-control" type="password" name="adm_pass" id="ins-adm-pass" value="{ins_admin_password}" maxlength="20"
			onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
	</div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-adm-email">{ins_acc_email}</label>
	<div class="col-sm-8">
		<input class="form-control" type="text" name="adm_email" id="ins-adm-email" value="{ins_admin_email}" maxlength="40"
			onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
	</div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-adm-planet">{ins_acc_planet}</label>
	<div class="col-sm-8">
		<input class="form-control" type="text" name="adm_planet" id="ins-adm-planet" value="{ins_admin_planet}" maxlength="20"
			onkeypress="if (event.keyCode == 60 || event.keyCode == 62) return false;">
	</div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label" for="ins-adm-sex">{ins_acc_sex}</label>
	<div class="col-sm-8">
		<select class="form-select" name="adm_sex" id="ins-adm-sex">
			<option value="">{ins_acc_sex0}</option>
			<option value="M">{ins_acc_sex1}</option>
			<option value="F">{ins_acc_sex2}</option>
		</select>
	</div>
</div>

<div class="text-end">
	<button type="button" class="btn btn-primary" onclick="submit();">
		<i class="bi bi-check2" aria-hidden="true"></i> {ins_btn_creat}
	</button>
</div>
