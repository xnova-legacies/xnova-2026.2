<div class="card xnova-panel-wide border-0 shadow-sm mb-3">
	<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
		<span class="fw-semibold"><i class="bi bi-box-seam" aria-hidden="true"></i> {ins_appname}</span>
		<span class="small text-body-secondary">{ins_tx_sys} &mdash; {ins_tx_state} {ins_state}</span>
	</div>
	<div class="card-body">
		<ul class="nav nav-pills flex-wrap gap-2 mb-4">
			<li class="nav-item"><a class="nav-link {ins_nav_intro}" href="index.php?mode=intro">{ins_mnu_intro}</a></li>
			<li class="nav-item"><a class="nav-link {ins_nav_ins}" href="index.php?mode=ins&amp;page=1">{ins_mnu_inst}</a></li>
			<li class="nav-item"><a class="nav-link {ins_nav_bye}" href="index.php?mode=bye">{ins_mnu_quit}</a></li>
		</ul>
		<form action="{dis_ins_btn}" method="post">
			{ins_page}
		</form>
	</div>
</div>
