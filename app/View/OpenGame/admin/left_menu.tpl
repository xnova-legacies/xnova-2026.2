<nav class="left-menu card border-0 shadow-sm" aria-label="{admin}">
  <div class="card-header text-center">
    <div class="fw-semibold">{servername}</div>
    <div class="small mt-1">
      <a href="/front/changelog" class="text-danger">{XNovaRelease}</a>
    </div>
  </div>

  <div class="list-group list-group-flush">
    <div class="section-label{section_admin_class}">{admin}</div>
    <a class="list-group-item list-group-item-action{adm_over_class}" href="/back/overview" accesskey="v">{adm_over}</a>
    <a class="list-group-item list-group-item-action{adm_conf_class}" href="/back/settings" accesskey="e">{adm_conf}</a>
    <a class="list-group-item list-group-item-action{adm_reset_class}" href="/back/reset" accesskey="e">{adm_reset}</a>
    <a class="list-group-item list-group-item-action{adm_extcopy_class}" href="/back/credit" accesskey="e">{adm_extcopy}</a>
    <a class="list-group-item list-group-item-action{adm_roles_class}" href="/back/roles" accesskey="r">{adm_roles}</a>
    <a class="list-group-item list-group-item-action{adm_modules_class}" href="/back/modules" accesskey="m">{adm_modules}</a>
    {adm_modules_links}

    <div class="section-label{section_player_class}">{player}</div>
    <a class="list-group-item list-group-item-action{adm_plrlst_class}" href="/back/userlist" accesskey="a">{adm_plrlst}</a>
    <a class="list-group-item list-group-item-action{adm_player_class}" href="/back/player" accesskey="g">{adm_player}</a>
    <a class="list-group-item list-group-item-action{adm_actions_class}" href="/back/actions" accesskey="j">{adm_actions}</a>
    <a class="list-group-item list-group-item-action{adm_multi_class}" href="/back/multi" accesskey="a">{adm_multi}</a>

    <div class="section-label{section_tool_class}">{tool}</div>
    <a class="list-group-item list-group-item-action{adm_fleet_class}" href="/back/flying-fleets" accesskey="k">{adm_fleet}</a>
    <a class="list-group-item list-group-item-action{adm_build_class}" href="/back/queue-fix" accesskey="p">{adm_build}</a>

    <div class="section-label{section_Messages_class}">{Messages}</div>
    <a class="list-group-item list-group-item-action{adm_msg_class}" href="/back/messagelist" accesskey="k">{adm_msg}</a>
    <a class="list-group-item list-group-item-action{adm_notes_class}" href="/back/notes" accesskey="n">{adm_notes}</a>
    <a class="list-group-item list-group-item-action{adm_msg_all_class}" href="/back/message-all" accesskey="k">{adm_msg_all}</a>
    <a class="list-group-item list-group-item-action{adm_chat_class}" href="/back/chat" accesskey="p">{adm_chat}</a>

    <div class="section-label{section_infog_class}">{infog}</div>
    <a class="list-group-item list-group-item-action{adm_help_class}" href="http://www.xnova.fr/forum/index.php" accesskey="3">{adm_help}</a>
    <a class="list-group-item list-group-item-action text-danger{adm_back_class}" href="/game/overview" accesskey="i">{adm_back}</a>

    <div class="list-group-item text-center small text-muted">
      <a href="/front/credit" accesskey="T">XNova Team</a><br>
      &copy; Copyright 2008
    </div>
  </div>
</nav>
