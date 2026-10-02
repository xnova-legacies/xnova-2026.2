<tr>
	<td>
		<input type="hidden" name="showmes{message_id}" value="1">
		<input class="form-check-input" type="checkbox" name="delmes{message_id}" data-xnova-check="messages" aria-label="{mess_action}">
	</td>
	<td class="text-nowrap small">{message_date}</td>
	<td class="text-nowrap">{message_from}</td>
	<td class="fw-semibold">
		{message_subject}
		<a class="btn btn-sm btn-outline-secondary{message_answer_class}" href="{message_answer_href}">
			<i class="bi bi-reply" aria-hidden="true"></i> {mess_answer}
		</a>
	</td>
</tr>
<tr>
	<td></td>
	<td colspan="3" class="pb-3">
		<div class="xnova-message-body">{message_body}</div>
	</td>
</tr>
