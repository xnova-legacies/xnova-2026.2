{buildinglist}

<form action="/game/buildings?mode=defense" method="post" class="xnova-shipyard" data-ajax="/game/api/shipyard/add" data-ajax-units="defense" data-ajax-reload="1">
	<div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
		{buildlist}
	</div>
	<div class="text-center mt-3">
		<button type="submit" class="btn btn-primary">{Construire}</button>
	</div>
</form>