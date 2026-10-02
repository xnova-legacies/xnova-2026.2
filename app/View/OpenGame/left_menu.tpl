<nav class="left-menu card border-0 shadow-sm" aria-label="{Overview}">
  <div class="card-header text-center">
    <a href="/game/overview" class="d-inline-block mb-2">
      <img src="/images/xnova-logo-128.png" width="72" height="72" alt="XNova" class="img-fluid">
    </a>
    <div class="fw-semibold">{servername}</div>
    <div class="small mt-1">
      <a href="/front/changelog" class="text-danger">{XNovaRelease}</a>
    </div>
  </div>

  <div class="list-group list-group-flush">
    <div class="section-label">{devlp}</div>
    <a class="list-group-item list-group-item-action" href="/game/overview" accesskey="g">{Overview}</a>
    <a class="list-group-item list-group-item-action" href="/game/buildings" accesskey="b">{Buildings}</a>
    <a class="list-group-item list-group-item-action" href="/game/buildings?mode=research" accesskey="r">{Research}</a>
    <a class="list-group-item list-group-item-action" href="/game/buildings?mode=fleet" accesskey="f">{Shipyard}</a>
    <a class="list-group-item list-group-item-action" href="/game/buildings?mode=defense" accesskey="d">{Defense}</a>
    {officier_link}
    {marchand_link}

    <div class="section-label">{navig}</div>
    {alliance_link}
    <a class="list-group-item list-group-item-action" href="/game/fleet" accesskey="t">{Fleet}</a>
    <a class="list-group-item list-group-item-action" href="/game/profil/messages" accesskey="c">{Messages}</a>

    <div class="section-label">{observ}</div>
    <a class="list-group-item list-group-item-action" href="/game/galaxy?mode=0" accesskey="s">{Galaxy}</a>
    {records_link}
    <a class="list-group-item list-group-item-action" href="/game/stat?range={user_rank}" accesskey="k">{Statistics}</a>
    <a class="list-group-item list-group-item-action" href="/game/search" accesskey="b">{Search}</a>
    <!-- {announce_link} -->

    <div class="section-label">{commun}</div>
    {chat_link}
    <!-- <a class="list-group-item list-group-item-action" href="{forum_url}" accesskey="1">{Board}</a> -->
    <!-- <a class="list-group-item list-group-item-action" href="/game/profil/options?tab=multi" accesskey="1">{multi}</a> -->
    <a class="list-group-item list-group-item-action" href="/game/rules" accesskey="c">{Rules}</a>
    <!-- <a class="list-group-item list-group-item-action" href="/front/contact" accesskey="3">{Contact}</a> -->
    <a class="list-group-item list-group-item-action" href="/game/profil/options" accesskey="o">{Options}</a>
    {ADMIN_LINK}
    {added_link}
    <a class="list-group-item list-group-item-action text-danger" href="javascript:top.location.href='/front/logout'" accesskey="s">{Logout}</a>

    <div class="section-label">{infog}</div>
    {server_info}

    <div class="list-group-item text-center small text-muted">
      <a href="/front/credit" accesskey="T">XNova Team</a><br>
      &copy; Copyright 2008
    </div>
  </div>
</nav>