<tr class="xnova-message-row{mlst_row_class}">
	<td><input class="form-check-input" type="checkbox" name="sele[{mlst_id}]" value="on" data-xnova-check="messagelist" aria-label="{mlst_id}"></td>
	<td>{mlst_id}</td>
	<td class="small text-nowrap">{mlst_time}</td>
	<td>{mlst_from}</td>
	<td class="small">{mlst_to}</td>
	<td class="small text-break">{mlst_text}</td>
	<td class="text-end">
		<button class="btn btn-sm btn-outline-danger py-0 px-1{mlst_del_class}" type="submit" name="delid" value="{mlst_id}" title="{mlst_del_mess}">
			<i class="bi bi-trash" aria-hidden="true"></i>
			<span class="visually-hidden">{mlst_del_mess}</span>
		</button>
		<button class="btn btn-sm btn-outline-success py-0 px-1{mlst_rest_class}" type="submit" name="restid" value="{mlst_id}" title="{mlst_restore_one}">
			<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
			<span class="visually-hidden">{mlst_restore_one}</span>
		</button>
	</td>
</tr>
