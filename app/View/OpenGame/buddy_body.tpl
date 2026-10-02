<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold"><i class="bi bi-people" aria-hidden="true"></i> {buddy_title}</div>
	<div class="card-body pb-0{buddy_actions_class}">
		<a class="btn btn-sm btn-outline-primary" href="?a=1"><i class="bi bi-inbox" aria-hidden="true"></i> {Requests}</a> <a class="btn btn-sm btn-outline-primary" href="?a=1&amp;e=1"><i class="bi bi-send" aria-hidden="true"></i> {My_requests}</a>
	</div>
	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle mb-0">
			<thead>
				<tr>
					<th scope="col" style="width: 3rem;"></th>
					<th scope="col">{buddy_col_user}</th>
					<th scope="col">{Alliance}</th>
					<th scope="col">{Coordinates}</th>
					<th scope="col">{buddy_col_last}</th>
					<th scope="col" class="text-end"></th>
				</tr>
			</thead>
			<tbody>
				{buddy_rows}
				<tr class="{buddy_empty_class}"><td colspan="6" class="text-center text-body-secondary py-4">{buddy_empty}</td></tr>
			</tbody>
		</table>
	</div>
	<div class="card-footer{buddy_footer_class}">
		<a class="btn btn-outline-secondary" href="/game/buddy"><i class="bi bi-arrow-left" aria-hidden="true"></i> {Back}</a>
	</div>
</div>
