<form action="{page_url}" method="get" class="d-flex flex-wrap align-items-end gap-2">
	<div>
		<label class="form-label small text-body-secondary mb-0" for="mlst_type">{mlst_hdr_type}</label>
		<select class="form-select form-select-sm" id="mlst_type" name="type" onchange="this.form.submit()">
			{type_options}
		</select>
	</div>
	<input type="hidden" name="state" value="{state_value}">
	<div>
		<button class="btn btn-sm btn-outline-primary" type="submit">{mlst_hdr_filter}</button>
	</div>
</form>

<div class="d-flex flex-wrap align-items-center gap-2 mt-2">
	<div class="btn-group" role="group">{mlst_states}</div>
</div>

{mlst_page_bar}

<form action="{page_url}" method="post">
	<input type="hidden" name="type" value="{type}">
	<input type="hidden" name="page" value="{page}">
	<input type="hidden" name="state" value="{state_value}">
	<input type="hidden" name="_token" value="{csrf_token}">
	<div class="d-flex flex-wrap align-items-end gap-2">
		<button class="btn btn-sm btn-outline-danger{mlst_live_class}" type="submit" name="do" value="delsel" onclick="return confirm('{mlst_confirm_sel}')">
			<i class="bi bi-trash3" aria-hidden="true"></i> {mlst_bt_delsel}
		</button>
		<div>
			<label class="form-label small text-body-secondary mb-0" for="mlst_deldate">{mlst_hdr_delfrom}</label>
			<input class="form-control form-control-sm" id="mlst_deldate" type="date" name="deldate">
		</div>
		<button class="btn btn-sm btn-outline-danger{mlst_live_class}" type="submit" name="do" value="deldat" onclick="return confirm('{mlst_confirm_date}')">
			<i class="bi bi-calendar-x" aria-hidden="true"></i> {mlst_bt_deldate}
		</button>
		<button class="btn btn-sm btn-outline-success{mlst_restore_selection_class}" type="submit" name="do" value="restore">
			<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> {mlst_bt_restore}
		</button>
	</div>
	<div class="table-responsive mt-3">
		<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
			<thead>
				<tr>{mlst_head}</tr>
			</thead>
			<tbody>{mlst_data_rows}</tbody>
		</table>
	</div>
	{mlst_pagination}
</form>
