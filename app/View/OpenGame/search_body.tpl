<form action="/game/search" method="post">
	<div class="card xnova-panel border-0 shadow-sm mb-3">
		<div class="card-header fw-semibold">
			<i class="bi bi-search" aria-hidden="true"></i> {Search_in_all_game}
		</div>
		<div class="card-body">
			<div class="row g-2 align-items-center">
				<div class="col-12 col-sm-4 col-lg-3">
					<label class="visually-hidden" for="search-type">{Search_in_all_game}</label>
					<select class="form-select" name="type" id="search-type">
						<option value="playername"{type_playername}>{Player_name}</option>
						<option value="planetname"{type_planetname}>{Planet_name}</option>
						{ally_options}
					</select>
				</div>
				<div class="col-12 col-sm-8 col-lg-6">
					<label class="visually-hidden" for="search-text">{Search}</label>
					<input class="form-control" type="text" name="searchtext" id="search-text" value="{searchtext}">
				</div>
				<div class="col-12 col-lg-3">
					<button type="submit" class="btn btn-primary w-100">
						<i class="bi bi-search" aria-hidden="true"></i> {Search}
					</button>
				</div>
			</div>
		</div>
	</div>
</form>
{search_results}
