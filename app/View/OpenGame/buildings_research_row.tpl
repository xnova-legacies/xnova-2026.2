<div class="col">
	<div class="card h-100 xnova-building-card">
		<div class="card-body d-flex gap-3">
			<a href="/game/infos?gid={tech_id}" class="flex-shrink-0 align-self-start">
				<img src="{dpath}buildings/{tech_id}.gif" width="80" height="80" class="rounded xnova-building-img" alt="{tech_name}">
			</a>
			<div class="min-w-0">
				<a class="fw-semibold" href="/game/infos?gid={tech_id}">{tech_name}</a> {tech_level}
				<div class="small text-body-secondary">{tech_descr}</div>
				<div class="small">{tech_price}</div>
				<div class="small">{search_time}</div>
				<div class="small">{tech_restp}</div>
			</div>
		</div>
		<div class="card-footer bg-transparent xnova-building-action">{tech_link}</div>
	</div>
</div>