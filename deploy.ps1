$ErrorActionPreference = 'Stop'

# `.env` n'est pas versionné (il choisit l'environnement) : sur un dépôt fraîchement
# cloné il n'existe pas encore, et le script démarrait alors sur une erreur de fichier
# introuvable. Sans lui, on part donc en `local`, ce qui est le bon défaut.
$environment = 'local'

if (Test-Path .env) {
    $declared = (Get-Content .env | Where-Object { $_ -match '^APP_ENV=' } | Select-Object -First 1) -replace '^APP_ENV=', ''
    if ($declared) {
        $environment = $declared
    }
}

if ($environment -notin @('local', 'dev', 'prod', 'review')) {
    throw "APP_ENV must be local, dev, prod or review. Current value: $environment"
}

$composeFiles = @('-f', 'docker-compose.yml')
$composeFiles += @('-f', "docker-compose.$environment.yml")

# Le fichier de l'environnement porte la connexion a la base et les reglages du jeu.
# S'il manque, Docker Compose s'arrete sur un « env file not found » : on prefere le
# dire ici, avec la commande qui le cree.
$envFile = "configs\.env.$environment"
if (-not (Test-Path $envFile)) {
    throw "Fichier d'environnement manquant : $envFile (copier configs/.env.example)."
}

# Le service temps reel n'est demarre que si le canal est active (WS_ENABLED=1
# dans configs/.env.<environnement>) : sinon le navigateur reste sur l'API JSON.
$wsValue = (Get-Content $envFile | Where-Object { $_ -match '^WS_ENABLED=' } | Select-Object -First 1) -replace '^WS_ENABLED=', ''
$wsEnabled = $wsValue -match '^(1|true|on|yes|oui)$'

# Le planificateur du classement, lui, tourne par defaut : STATS_ENABLED=0 suffit
# a l'arreter. Les deux profils se combinent, l'ordre n'importe pas.
$statsEnabled = $true
$statsValue = (Get-Content $envFile | Where-Object { $_ -match '^STATS_ENABLED=' } | Select-Object -First 1) -replace '^STATS_ENABLED=', ''
$statsEnabled = -not ($statsValue -match '^(0|false|off|no|non)$')

$profileArgs = @()
if ($wsEnabled) {
    $profileArgs += @('--profile', 'websocket')
    Write-Host 'Canal temps reel active (profil websocket).'
} else {
    Write-Host 'Canal temps reel desactive (WS_ENABLED=0) : repli sur l''API JSON.'
}

if ($statsEnabled) {
    $profileArgs += @('--profile', 'stats')
    Write-Host 'Mise a jour du classement active (profil stats).'
} else {
    Write-Host 'Mise a jour du classement desactivee (STATS_ENABLED=0).'
}

# `dev` (integration), `local` et `review` sont jetables : la base repart vierge a chaque
# deploiement, donc aucun schema remanie ni aucune donnee d'essai ne survit d'un
# deploiement a l'autre. `prod`, lui, garde ses donnees.
#
# Ce geste est necessaire **meme** quand la base n'est pas un volume : le `tmpfs` d'un
# `local` ne disparait qu'a la **recreation** du conteneur, et une migration deja
# appliquee n'est jamais rejouee (le registre `game_migrations` la retient). Une colonne
# renommee dans `001_initial_schema.php` laissait donc une base a l'ancien schema, et
# l'installeur — rappele a chaque demarrage puisque `docker-entrypoint.sh` vide
# `configs/config.php` — echouait sur « Unknown column ».
if ($environment -in @('dev', 'local', 'review')) {
    Write-Host 'Environnement jetable : la base repart vierge (volume supprime).'
    & docker compose @profileArgs @composeFiles down --volumes --remove-orphans

    # `configs/config.php` retient la connexion : sans base, le jeu ne peut plus rien
    # lire. Le vider ramene a l'installateur (`common.php` teste sa taille), exactement
    # comme le fait docker-entrypoint.sh en local. Le fichier est suivi par git avec les
    # valeurs de developpement : l'installateur le reecrit a l'identique, l'arbre de
    # travail redevient donc propre une fois l'installation terminee.
    [System.IO.File]::WriteAllText((Join-Path $PWD 'configs\config.php'), '')
    Write-Host 'Connexion videe : ouvrir http://localhost:8080/ pour reinstaller.'
}

& docker compose @profileArgs @composeFiles up -d --build
& docker compose @profileArgs @composeFiles ps
