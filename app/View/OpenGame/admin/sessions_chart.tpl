	<div class="col-12 col-xl-6">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-header fw-semibold">{ses_chart_title}</div>
			<div class="card-body">
				<svg viewBox="0 0 600 160" preserveAspectRatio="none" class="w-100 text-primary xnova-session-chart" role="img" aria-label="{ses_chart_title}">
					<polyline points="{ses_chart_area}" fill="currentColor" opacity="0.15" stroke="none"></polyline>
					<polyline points="{ses_chart_line}" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"></polyline>
				</svg>
				<div class="d-flex justify-content-between small text-body-secondary mt-1">{ses_chart_labels}</div>
			</div>
		</div>
	</div>
