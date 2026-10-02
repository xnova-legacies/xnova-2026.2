<script type="text/javascript" src="/scripts/time.js"></script>

<div class="card xnova-overview border-0 shadow-sm">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<a class="fw-semibold text-decoration-none" href="/game/overview?mode=renameplanet" title="{Planet_menu}">{Planet} &laquo;&nbsp;{planet_name}&nbsp;&raquo;</a>
		<span class="small text-body-secondary">{user_username}</span>
	</div>

	<div class="card-body p-0">
		<ul class="nav nav-tabs xnova-overview-tabs px-3 pt-2">
			<li class="nav-item"><a class="nav-link{tab_overview_active}" href="/game/overview">{ov_tab_situation}</a></li>
			<li class="nav-item"><a class="nav-link{tab_resources_active}" href="/game/overview?tab=resources">{ov_tab_resources}</a></li>
			<li class="nav-item"><a class="nav-link{tab_empire_active}" href="/game/overview?tab=empire">{ov_tab_empire}</a></li>
			<li class="nav-item"><a class="nav-link{tab_tech_active}" href="/game/overview?tab=tech">{ov_tab_tech}</a></li>
		</ul>

		<div class="xnova-overview-tab{tab_situation_hidden}">
		{Have_new_message}
		{progression_alerts}
		{NewsFrame}

		<table class="table table-sm table-borderless align-middle mb-0 xnova-overview-table">
			<tbody>
				<tr>
					<th scope="row" class="xnova-label">{Planet}</th>
					<td>
						<div class="row g-3 align-items-start">
							<div class="col-12 col-md-auto text-center">
								<img src="{dpath}planeten/{planet_image}.jpg" width="200" height="200" class="img-fluid rounded" alt="{planet_name}">
							</div>
							<div class="col-12 col-md">
								{moon_block}
								<div class="row row-cols-2 row-cols-sm-2 row-cols-lg-3 g-2">{anothers_planets}</div>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row" class="xnova-label">{Diameter}</th>
					<td>{planet_diameter} km (<a href="#" title="{Developed_fields}">{planet_field_current}</a> / <a href="#" title="{max_eveloped_fields}">{planet_field_max}</a> {fields})</td>
				</tr>
				<tr>
					<th scope="row" class="xnova-label">{Developed_fields}</th>
					<td>
						<div class="progress xnova-progress" role="progressbar" aria-label="{Developed_fields}" aria-valuenow="{case_barre_pourcent}" aria-valuemin="0" aria-valuemax="100">
							<div class="progress-bar" style="width: {case_barre_pourcent}%; background-color: {case_barre_barcolor};">{case_pourcentage}</div>
						</div>
					</td>
				</tr>

				{progression_block}

				<tr>
					<th scope="row" class="xnova-label">{Temperature}</th>
					<td>{ov_temp_from} {planet_temp_min}{ov_temp_unit} {ov_temp_to} {planet_temp_max}{ov_temp_unit}</td>
				</tr>
				<tr>
					<th scope="row" class="xnova-label">{Position}</th>
					<td><a href="/game/galaxy?mode=0&galaxy={galaxy_galaxy}&system={galaxy_system}">[{galaxy_galaxy}:{galaxy_system}:{galaxy_planet}]</a></td>
				</tr>
				<tr>
					<th scope="row" class="xnova-label">{ov_local_cdr}</th>
					<td>{Metal} : {metal_debris} / {Crystal} : {crystal_debris}{debris_extra}{get_link}</td>
				</tr>

				<tr>
					<th scope="row" class="xnova-label">{Points}</th>
					<td>
						<div class="row row-cols-1 row-cols-sm-2 g-1 xnova-kv">
							<div class="col"><span class="text-body-secondary">{ov_pts_build} :</span> <strong>{user_points}</strong></div>
							<div class="col"><span class="text-body-secondary">{ov_pts_fleet} :</span> <strong>{user_fleet}</strong></div>
							<div class="col"><span class="text-body-secondary">{ov_pts_reche} :</span> <strong>{player_points_tech}</strong></div>
							<div class="col"><span class="text-body-secondary">{ov_pts_total} :</span> <strong>{total_points}</strong></div>
						</div>
						<div class="mt-1 small text-body-secondary">({Rank} <a href="/game/stat?range={u_user_rank}">{user_rank}</a> {of} {max_users})</div>
					</td>
				</tr>
				<tr>
					<th scope="row" class="xnova-label">{Raids}</th>
					<td>
						<div class="row row-cols-1 row-cols-sm-3 g-1 xnova-kv">
							<div class="col"><span class="text-body-secondary">{NumberOfRaids} :</span> <strong>{raids}</strong></div>
							<div class="col"><span class="text-body-secondary">{RaidsWin} :</span> <strong>{raidswin}</strong></div>
							<div class="col"><span class="text-body-secondary">{RaidsLoose} :</span> <strong>{raidsloose}</strong></div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		</div>

		<div class="xnova-overview-tab{tab_resources_hidden}">{overview_resources}</div>

		<div class="xnova-overview-tab{tab_empire_hidden}">{overview_empire}</div>

		<div class="xnova-overview-tab{tab_tech_hidden}">{overview_tech}</div>

		{bannerframe}
		{ExternalTchatFrame}
	</div>
</div>

{ClickBanner}