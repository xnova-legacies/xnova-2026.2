<div class="col">
	<div class="card h-100 xnova-building-card">
		<div class="card-body d-flex gap-3">
			<a href="/game/infos?gid={unit_id}" class="flex-shrink-0 align-self-start">
				<img src="{dpath}buildings/{unit_id}.gif" width="80" height="80" class="rounded xnova-building-img" alt="{unit_name}">
			</a>
			<div class="min-w-0">
				<a class="fw-semibold" href="/game/infos?gid={unit_id}">{unit_name}</a> {unit_count}
				<div class="small text-body-secondary">{unit_descr}</div>
				<div class="small">{unit_price}</div>
				<div class="small">{unit_time}</div>
			</div>
		</div>
		<div class="card-footer bg-transparent xnova-building-action">{unit_input}</div>
	</div>
</div>
