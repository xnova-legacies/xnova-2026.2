<script src="/scripts/cntchar.js"></script>
<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold"><i class="bi bi-person-plus" aria-hidden="true"></i> {buddy_title}</div>
	<form action="/game/buddy" method="post">
		<input type="hidden" name="a" value="1">
		<input type="hidden" name="s" value="3">
		<input type="hidden" name="e" value="1">
		<input type="hidden" name="u" value="{buddy_id}">
		<div class="card-body">
			<div class="row mb-3 align-items-center">
				<label class="col-sm-4 col-form-label">{Player}</label>
				<div class="col-sm-8"><p class="form-control-plaintext fw-semibold mb-0">{buddy_username}</p></div>
			</div>
			<div class="row mb-3">
				<label class="col-sm-4 col-form-label" for="text">{Request_text}</label>
				<div class="col-sm-8">
					<textarea class="form-control" name="text" id="text" cols="60" rows="8" oninput="cntchar(5000)"></textarea>
					<div class="form-text"><span id="cntChars">0</span> / 5000 {characters}</div>
				</div>
			</div>
		</div>
		<div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
			<a class="btn btn-outline-secondary" href="/game/buddy"><i class="bi bi-arrow-left" aria-hidden="true"></i> {Back}</a>
			<button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> {Send}</button>
		</div>
	</form>
</div>
