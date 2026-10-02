
    <a style="cursor: pointer;" onmouseover="
this.T_WIDTH=200;
this.T_OFFSETX=-20;
this.T_OFFSETY=-30;
this.T_STICKY=true;
this.T_TEMP={T_TEMP};
return escape('<table width=\'190\'><tr><td class=\'c\' colspan=\'2\'>Joueur {username}</td></tr><tr><td><a href=\'/game/profil/messages?mode=write&id={user_id}\'>Envoyer un message</a></td></tr><tr><td><a href=\'/game/buddy?a=2&u={user_id}\' onclick=\'var w=window.open(this.getAttribute(&quot;href&quot;),&quot;Buddy&quot;,&quot;resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=550,height=360,top=0,left=0&quot;);if(w){w.focus();}return false;\'>Demande d\'ami</a></td></tr></table>');">
      <span class="noob">{username}</span>
    </a>