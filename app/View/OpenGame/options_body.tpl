<ul class="nav nav-tabs px-1 pb-2">
	<li class="nav-item"><a class="nav-link{tab_options_active}" href="/game/profil/options">{tab_label_options}</a></li>
	<li class="nav-item"><a class="nav-link{tab_multi_active}" href="/game/profil/options?tab=multi">{tab_label_multi}</a></li>
</ul>

<div class="xnova-options-tab{tab_options_hidden}">
<form action="{PHP_SELF}?mode=change" method="post" data-ajax="/game/api/options/save" data-ajax-reload="1">
	<div class="card xnova-panel border-0 shadow-sm mb-3">
		<div class="card-header fw-semibold">
			<i class="bi bi-gear" aria-hidden="true"></i> {Options}
		</div>
		<div class="card-body">
			{opt_adm_frame}

			<h3 class="xnova-form-section">{userdata}</h3>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-username">{username}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="text" name="db_character" id="opt-username" value="{opt_usern_data}" autocomplete="username">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-oldpass">{lastpassword}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="password" name="db_password" id="opt-oldpass" value="" autocomplete="current-password">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-newpass1">{newpassword}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="password" name="newpass1" id="opt-newpass1" maxlength="40" autocomplete="new-password">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-newpass2">{newpasswordagain}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="password" name="newpass2" id="opt-newpass2" maxlength="40" autocomplete="new-password">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-email" title="{emaildir_tip}">
						{emaildir} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="text" name="db_email" id="opt-email" maxlength="100" value="{opt_mail1_data}" autocomplete="email">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					{permanentemaildir}
				</div>
				<div class="col-sm-5 col-lg-4 text-body-secondary">
					{opt_mail2_data}
				</div>
			</div>

			<h3 class="xnova-form-section">{general_settings}</h3>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-sort">{opt_lst_ord}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<select class="form-select" name="settings_sort" id="opt-sort">
						{opt_lst_ord_data}
					</select>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-order">{opt_lst_cla}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<select class="form-select" name="settings_order" id="opt-order">
						{opt_lst_cla_data}
					</select>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-dpath">{skins_example}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="input-group">
						<input class="form-control" type="text" name="dpath" id="opt-dpath" maxlength="80" value="{opt_dpath_data}">
						<select class="form-select" name="dpaths" aria-label="{skins_example}">
							<option selected="selected"> </option>
							{opt_lst_skin_data}
						</select>
					</div>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-design">{opt_chk_skin}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="design" id="opt-design"{opt_sskin_data}>
					</div>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-avatar">{avatar_example}</label>
					<a class="small" href="https://www.google.com/imghp" target="_blank" rel="noopener noreferrer">
						<i class="bi bi-search" aria-hidden="true"></i> {Search}
					</a>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="text" name="avatar" id="opt-avatar" maxlength="80" value="{opt_avata_data}">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-noipcheck" title="{untoggleip_tip}">
						{untoggleip} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="noipcheck" id="opt-noipcheck"{opt_noipc_data}>
					</div>
				</div>
			</div>

			<h3 class="xnova-form-section">{galaxyvision_options}</h3>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-probes" title="{spy_cant_tip}">
						{spy_cant} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="number" max="99" name="spy_count" id="opt-probes" value="{opt_probe_data}">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-tooltip">{tooltip_time}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="input-group">
						<input class="form-control" type="number" min="0" max="99" name="settings_tooltiptime" id="opt-tooltip" value="{opt_toolt_data}">
						<span class="input-group-text">{seconds}</span>
					</div>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-fleetactions">{mess_ammount_max}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<input class="form-control" type="number" min="0" max="99" name="settings_fleetactions" id="opt-fleetactions" value="{opt_fleet_data}">
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-allylogo">{show_ally_logo}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_allylogo" id="opt-allylogo"{opt_allyl_data}>
					</div>
				</div>
			</div>

			<div class="row mb-2">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0">{shortcut}</label>
				</div>
				<div class="col-sm-5 col-lg-4 text-body-secondary">{show}</div>
			</div>

			<div class="row mb-2 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-esp"><i class="bi bi-binoculars" aria-hidden="true"></i> {spy}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_esp" id="opt-settings-esp"{user_settings_esp}>
					</div>
				</div>
			</div>

			<div class="row mb-2 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-wri"><i class="bi bi-envelope" aria-hidden="true"></i> {write_a_messege}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_wri" id="opt-settings-wri"{user_settings_wri}>
					</div>
				</div>
			</div>

			<div class="row mb-2 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-bud"><i class="bi bi-person-plus" aria-hidden="true"></i> {add_to_buddylist}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_bud" id="opt-settings-bud"{user_settings_bud}>
					</div>
				</div>
			</div>

			<div class="row mb-2 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-mis"><i class="bi bi-rocket" aria-hidden="true"></i> {attack_with_missile}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_mis" id="opt-settings-mis"{user_settings_mis}>
					</div>
				</div>
			</div>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-rep"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {show_report}</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_rep" id="opt-settings-rep"{user_settings_rep}>
					</div>
				</div>
			</div>

			<h3 class="xnova-form-section{bots_section_class}">{bots_section}</h3>

			<div class="row mb-3 align-items-center{bots_section_class}">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-settings-bots" title="{bots_interaction_tip}">
						<i class="bi bi-robot" aria-hidden="true"></i> {bots_interaction} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
					<div class="small text-body-secondary">{bots_interaction_universe}</div>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="settings_bots" id="opt-settings-bots"{user_settings_bots}{user_settings_bots_disabled}>
					</div>
				</div>
			</div>

			<h3 class="xnova-form-section">{delete_vacations}</h3>

			<div class="row mb-3 align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0" for="opt-vacation" title="{vacations_tip}">
						{mode_vacations} <i class="bi bi-info-circle text-body-secondary" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="vacation_mode" id="opt-vacation"{opt_modev_data}>
					</div>
				</div>
			</div>

			<div class="row align-items-center">
				<div class="col-sm-7 col-lg-8">
					<label class="mb-0 text-danger" for="opt-delete-account" title="{deleteaccount_tip}">
						{deleteaccount} <i class="bi bi-info-circle" aria-hidden="true"></i>
					</label>
				</div>
				<div class="col-sm-5 col-lg-4">
					<div class="form-check form-switch mb-0">
						<input class="form-check-input" type="checkbox" role="switch" name="no_javascript" id="opt-delete-account"{opt_delac_data}>
					</div>
				</div>
			</div>
		</div>
		<div class="card-footer text-end">
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-check2-circle" aria-hidden="true"></i> {save_settings}
			</button>
		</div>
	</div>
</form></div>

<div class="xnova-options-tab{tab_multi_hidden}">{options_multi}</div>