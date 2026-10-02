				<tr>
					<td>{act_time}</td>
					<td>{act_player}</td>
					<td>
						<div>{act_label}</div>
						<div class="small text-body-secondary{act_summary_class}">{act_summary}</div>
						<div class="small text-body-secondary">{act_detail}</div>
						<details class="small{act_payload_class}">
							<summary>{act_payload_title}</summary>
							<code>{act_payload}</code>
						</details>
					</td>
					<td>{act_method} <span class="badge text-bg-light border">{act_kind}</span></td>
					<td>{act_ip}</td>
				</tr>
