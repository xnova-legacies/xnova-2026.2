<div class="card border-0 shadow-sm mb-2 xnova-battle-rapidfire">
	<div class="card-header py-1 small fw-semibold">{rapidfire_title}</div>
	<div class="table-responsive {rapidfire_table_class}">
		<table class="table table-sm table-borderless align-middle mb-0">
			<thead>
				<tr class="small text-body-secondary">
					<th scope="col">{sys_rf_unit}</th>
					<th scope="col">{sys_rf_target}</th>
					<th scope="col" class="text-end">{sys_rf_shots}</th>
				</tr>
			</thead>
			<tbody>{rapidfire_list}</tbody>
		</table>
	</div>
	<div class="card-body py-2 small text-body-secondary {rapidfire_empty_class}">{sys_rf_none}</div>
	<p class="card-body py-2 small text-body-secondary mb-0 {rapidfire_table_class}">{sys_rf_hint}</p>
</div>
