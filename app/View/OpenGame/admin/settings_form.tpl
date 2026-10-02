<form action="{settings_action}" method="post">
	<input type="hidden" name="opt_save" value="1">
	<input type="hidden" name="_token" value="{csrf_token}">

	<div class="row g-3">
		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{adm_opt_game_settings}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_game_name">{adm_opt_game_name}</label>
						<input class="form-control form-control-sm" id="set_game_name" type="text" name="game_name" maxlength="40" value="{game_name}">
					</div>
					<div class="row g-2">
						<div class="col-4">
							<label class="form-label small text-body-secondary mb-0" for="set_game_speed">{adm_opt_game_gspeed}</label>
							<input class="form-control form-control-sm" id="set_game_speed" type="number" min="0.01" step="any" name="game_speed" value="{game_speed}">
						</div>
						<div class="col-4">
							<label class="form-label small text-body-secondary mb-0" for="set_fleet_speed">{adm_opt_game_fspeed}</label>
							<input class="form-control form-control-sm" id="set_fleet_speed" type="number" min="0.01" step="any" name="fleet_speed" value="{fleet_speed}">
						</div>
						<div class="col-4">
							<label class="form-label small text-body-secondary mb-0" for="set_multiplier">{adm_opt_game_pspeed}</label>
							<input class="form-control form-control-sm" id="set_multiplier" type="number" min="0.01" step="any" name="resource_multiplier" value="{resource_multiplier}">
						</div>
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_forum">{adm_opt_game_forum}</label>
						<input class="form-control form-control-sm" id="set_forum" type="text" name="forum_url" maxlength="254" value="{forum_url}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_stat">{stat_settings_desc}</label>
						<div class="input-group input-group-sm">
							<input class="form-control" id="set_stat" type="number" name="stat_settings" value="{stat_settings}">
							<span class="input-group-text">{stat_units}</span>
						</div>
					</div>
					<div class="form-check">
						<input class="form-check-input" id="set_closed" type="checkbox" name="closed"{checked_game_disable}>
						<label class="form-check-label" for="set_closed">{adm_opt_game_online}</label>
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_close_reason">{adm_opt_game_offreaso}</label>
						<textarea class="form-control form-control-sm" id="set_close_reason" name="close_reason" rows="2">{close_reason}</textarea>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{adm_opt_plan_settings}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_fields">{adm_opt_plan_initial}</label>
						<input class="form-control form-control-sm" id="set_fields" type="number" min="1" name="initial_fields" value="{initial_fields}">
					</div>
					<div class="row g-2">
						<div class="col-6">
							<label class="form-label small text-body-secondary mb-0" for="set_metal">{Metal}</label>
							<input class="form-control form-control-sm" id="set_metal" type="number" min="0" name="metal_basic_income" value="{metal_basic_income}">
						</div>
						<div class="col-6">
							<label class="form-label small text-body-secondary mb-0" for="set_crystal">{Crystal}</label>
							<input class="form-control form-control-sm" id="set_crystal" type="number" min="0" name="crystal_basic_income" value="{crystal_basic_income}">
						</div>
						<div class="col-6">
							<label class="form-label small text-body-secondary mb-0" for="set_deut">{Deuterium}</label>
							<input class="form-control form-control-sm" id="set_deut" type="number" min="0" name="deuterium_basic_income" value="{deuterium_basic_income}">
						</div>
						<div class="col-6">
							<label class="form-label small text-body-secondary mb-0" for="set_energy">{Energy}</label>
							<input class="form-control form-control-sm" id="set_energy" type="number" min="0" name="energy_basic_income" value="{energy_basic_income}">
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{messages_settings}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_bbcode">{bbcode_settings}</label>
						<input class="form-control form-control-sm" id="set_bbcode" type="number" min="0" max="1" name="bbcode_field" value="{enable_bbcode}">
					</div>
					<div class="form-check">
						<input class="form-check-input" id="set_news" type="checkbox" name="newsframe"{checked_news}>
						<label class="form-check-label" for="set_news">{adm_opt_game_oth_news}</label>
					</div>
					<textarea class="form-control form-control-sm" name="NewsText" rows="3">{OverviewNewsText}</textarea>
					<div class="form-check">
						<input class="form-check-input" id="set_chat" type="checkbox" name="chatframe"{checked_chat}>
						<label class="form-check-label" for="set_chat">{adm_opt_game_oth_chat}</label>
					</div>
					<textarea class="form-control form-control-sm" name="ExternChat" rows="3">{OverviewExternChatCmd}</textarea>
					<div class="form-check">
						<input class="form-check-input" id="set_banner" type="checkbox" name="googlead"{checked_banner}>
						<label class="form-check-label" for="set_banner">{adm_opt_game_oth_adds}</label>
					</div>
					<textarea class="form-control form-control-sm" name="GoogleAds" rows="3">{OverviewClickBanner}</textarea>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{multi_bot_settings}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_bot">{bot_active}</label>
						<input class="form-control form-control-sm" id="set_bot" type="number" min="0" max="1" name="bot_enable" value="{enable_bot}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_bot_name">{bot_name_multi}</label>
						<input class="form-control form-control-sm" id="set_bot_name" type="text" name="name_bot" value="{bot_name}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_bot_adress">{bot_adress_multi}</label>
						<input class="form-control form-control-sm" id="set_bot_adress" type="text" name="adress_bot" value="{bot_adress}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_ban">{bot_ban_duration}</label>
						<input class="form-control form-control-sm" id="set_ban" type="number" min="0" name="duration_ban" value="{ban_duration}">
					</div>
					<div class="form-check">
						<input class="form-check-input" id="set_forumbanner" type="checkbox" name="bannerframe"{checked_forumbanner}>
						<label class="form-check-label" for="set_forumbanner">{adm_opt_game_oth_bann}</label>
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_banner_src">{banner}</label>
						<input class="form-control form-control-sm" id="set_banner_src" type="text" name="banner_source_post" value="{banner_source_post}">
					</div>
					<div class="form-check">
						<input class="form-check-input" id="set_debug" type="checkbox" name="debug"{checked_debug}>
						<label class="form-check-label" for="set_debug">{adm_opt_game_debugmod}</label>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{adm_opt_menu_link_enable}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_link">{adm_opt_menu_link_enable}</label>
						<input class="form-control form-control-sm" id="set_link" type="number" min="0" max="1" name="enable_link_" value="{link_enable}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_link_name">{adm_opt_menu_link_text}</label>
						<input class="form-control form-control-sm" id="set_link_name" type="text" name="name_link_" value="{link_name}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_link_url">{adm_opt_menu_link_url}</label>
						<input class="form-control form-control-sm" id="set_link_url" type="text" name="url_link_" value="{link_url}">
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card border-0 shadow-sm h-100">
				<div class="card-header fw-semibold">{adm_opt_control_pages}</div>
				<div class="card-body d-flex flex-column gap-2">
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_announces">{enable_the_anounces}</label>
						<input class="form-control form-control-sm" id="set_announces" type="number" min="0" max="1" name="enable_announces_" value="{enable_announces}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_marchand">{enable_the_marchand}</label>
						<input class="form-control form-control-sm" id="set_marchand" type="number" min="0" max="1" name="enable_marchand_" value="{enable_marchand}">
					</div>
					<div>
						<label class="form-label small text-body-secondary mb-0" for="set_notes">{enable_the_notes}</label>
						<input class="form-control form-control-sm" id="set_notes" type="number" min="0" max="1" name="enable_notes_" value="{enable_notes}">
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="mt-3">
		<button class="btn btn-primary" type="submit"><i class="bi bi-save" aria-hidden="true"></i> {adm_opt_btn_save}</button>
	</div>
</form>
