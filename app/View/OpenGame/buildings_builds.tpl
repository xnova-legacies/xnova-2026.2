<div class="card xnova-buildings border-0 shadow-sm" data-xnova-autorefresh="revision">
	<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
		<span class="fw-semibold">{bld_usedcells}</span>
		<span class="small">
			<span class="text-success fw-semibold">{planet_field_current}</span>
			<span class="text-body-secondary">/</span>
			<span class="text-danger fw-semibold">{planet_field_max}</span>
			<span class="text-body-secondary">{bld_theyare} {field_libre} {bld_cellfree}</span>
		</span>
	</div>

	<div class="xnova-queue xnova-queue-source" data-xnova-queue="buildings">
		<div class="list-group list-group-flush" data-xnova-queue-live>{QueueList}</div>
	</div>

	<div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3 p-3">
		{BuildingsList}
	</div>
</div>