<div class="col">
	<div class="card h-100 xnova-building-card">
		<div class="card-body d-flex gap-3">
			<a href="/game/infos?gid={i}" class="flex-shrink-0 align-self-start">
				<img src="{dpath}buildings/{i}.gif" width="80" height="80" class="rounded xnova-building-img" alt="{n}">
			</a>
			<div class="min-w-0">
				<a class="fw-semibold" href="/game/infos?gid={i}">{n}</a>{nivel}
				<div class="small text-body-secondary">{descriptions}</div>
				<div class="small">{price}</div>
				<div class="small">{time}</div>
				<div class="small">{rest_price}</div>
			</div>
		</div>
		<div class="card-footer bg-transparent xnova-building-action">{click}</div>
	</div>
</div>