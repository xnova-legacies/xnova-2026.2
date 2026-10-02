<tr class="xnova-module-row{module_row_class}">
	<td class="fw-semibold">
		{module_row_label}
		<span class="badge text-bg-light{module_row_native_class}">{mod_native_badge}</span>
	</td>
	<td class="small text-break">
		{module_row_description}
		<span class="text-body-secondary d-block{module_row_version_class}">{module_row_version}</span>
	</td>
	<td class="small font-monospace">{module_row_permission}</td>
	<td class="small font-monospace">{module_row_page}</td>
	<td class="small">
		{module_row_dependencies}
		<span class="badge text-bg-danger d-block{module_row_warning_class}">{module_row_warning}</span>
	</td>
	<td><span class="badge{module_row_state_class}">{module_row_state}</span></td>
	<td class="text-end text-nowrap">
		<button class="btn btn-sm{module_row_toggle_class} py-0 px-1" type="submit" name="toggle" value="{module_row_name}">
			{module_row_toggle_label}
		</button>
		<button class="btn btn-sm btn-outline-secondary py-0 px-1{module_row_archive_class}" type="submit" name="archive" value="{module_row_name}" title="{mod_archive_hint}">
			{mod_archive}
		</button>
		<button class="btn btn-sm btn-outline-success py-0 px-1{module_row_restore_class}" type="submit" name="restore" value="{module_row_name}">
			{mod_archive_restore}
		</button>
	</td>
</tr>
