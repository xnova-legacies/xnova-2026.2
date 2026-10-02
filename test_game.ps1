param([string]$BaseUrl = "http://localhost:8080", [string]$User = "admin", [string]$Pass = "admin123")
$jar = "$env:TEMP\game_test_cookies.txt"
Remove-Item $jar -ErrorAction SilentlyContinue
curl.exe -s -c $jar -b $jar -o NUL $BaseUrl/front/login
curl.exe -s -b $jar -c $jar -o NUL -X POST $BaseUrl/front/login -d "username=$User&password=$Pass"
$urls = @(
    "front/login","front/logout","front/contact","front/lostpassword","front/changelog",
    "front/credit","game/overview","game/buildings","game/buildings?mode=fleet",
    "game/buildings?mode=research","game/buildings?mode=defense","game/fleet",
    "game/galaxy","game/alliance","game/alliance/info?a=1","game/profil",
    "game/profil/messages","game/profil/notes","game/profil/options",
    "game/profil/officier","game/profil/imperium","game/profil/techtree",
    "game/profil/techdetails?techid=12","game/infos?gid=1","game/infos?gid=210","game/infos?gid=216",
    "game/leftmenu","game/chat","game/search","game/stat","game/records",
    "game/resources","game/marchand","game/marchand?mode=marche","game/neusuw","game/multi","game/acs",
    "overview.php","login.php","alliance.php","infos.php?gid=1","common.php"
)
$ok = 0; $fail = 0
$body = "$env:TEMP\game_test_body.html"
foreach ($u in $urls) {
    # Le corps est efface avant chaque essai : sans cela, un curl qui n'obtient rien
    # (conteneur qui redemarre) laissait le fichier de l'essai precedent, et l'adresse
    # suivante etait declaree en erreur PHP a cause de lui.
    Remove-Item $body -ErrorAction SilentlyContinue
    $code = curl.exe -s -b $jar -c $jar -o $body -w "%{http_code}" "$BaseUrl/$u"
    $html = if (Test-Path $body) { Get-Content $body -Raw } else { "" }
    # 200 : page servie ; 301 : adresse historique d'un module (redirigée) ;
    # 302 : page privée sans session ; 403 : fichier de Coeur d'application refusé.
    $expected = if ($u -eq "common.php") { "403" } else { "200|301|302" }
    # Un « Fatal error » part en **200** avec `display_errors` : le code HTTP ne suffit
    # pas à dire qu'une page est saine (vécu : la page du marché servait une erreur
    # fatale en 200, et le crawl la comptait comme réussie).
    $phpError = $html -match 'Fatal error|Parse error|Uncaught |Warning</b>:|Undefined (variable|array key|constant)'
    if (($code -match "^($expected)$") -and (-not $phpError)) {
        $ok++
    } else {
        $fail++
        if ($phpError) { Write-Host "FAIL $u => $code (erreur PHP dans la page)" }
        else { Write-Host "FAIL $u => $code" }
    }
}
Write-Host "$ok/$($urls.Count) OK, $fail fails"
