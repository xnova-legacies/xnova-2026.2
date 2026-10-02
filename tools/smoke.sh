#!/bin/sh
#
# Fumée HTTP : la pile répond, et aucune page servie ne contient d'erreur PHP.
#
# Usage : sh tools/smoke.sh [base]        (défaut : http://localhost:8080)
#
# C'est le contrôle des workflows (`.github/workflows/*.yml`) et le même geste en local,
# avant de pousser : la qualité est vérifiée par les tests unitaires, la **mise en
# route** par ce script.
#
# Pourquoi un script ici plutôt que `test_game.ps1` : celui-ci est en PowerShell pour un
# poste Windows (il connaît `curl.exe`, l'installation, la connexion et les 40 adresses
# du jeu) ; celui-ci tient en POSIX, tourne sur un runner Linux comme dans un Git Bash,
# et ne demande **aucune** installation — il regarde ce qu'un univers neuf expose.
#
# Ce qui fait échouer la fumée :
#   - un code 5xx (ou aucune réponse) ;
#   - une page dont le corps contient « Fatal error », « Parse error », « Uncaught » ou
#     un avertissement PHP. Piège connu du projet : PHP sort un `Fatal error` en **200**
#     quand `display_errors` est actif, donc le code seul ne suffit pas.
#
# Les adresses testées sont celles d'un dépôt fraîchement cloné : la pile monte, la page
# de connexion et l'installateur répondent, le jeu renvoie vers l'installation ou la
# connexion (302). Aucune base pré-remplie n'est nécessaire.

set -eu

BASE="${1:-http://localhost:8080}"
CORPS="$(mktemp)"
trap 'rm -f "$CORPS"' EXIT

# Motifs d'erreur PHP (identiques à ceux de test_game.ps1).
ERREURS='Fatal error|Parse error|Uncaught |Warning</b>:|Undefined (variable|array key|constant)'

ADRESSEES='/install/ /front/login /game/overview /back/overview'

echecs=0

for chemin in $ADRESSEES; do
    code=$(curl -sS -o "$CORPS" -w '%{http_code}' "$BASE$chemin" 2>/dev/null || echo '000')

    cas='ok'
    if [ "$code" = '000' ]; then
        cas='aucune reponse'
    elif [ "$code" -ge 500 ]; then
        cas="code $code"
    elif grep -Eq "$ERREURS" "$CORPS"; then
        cas="erreur PHP dans le corps ($(grep -Eo "$ERREURS" "$CORPS" | head -n 1))"
    fi

    if [ "$cas" = 'ok' ]; then
        printf '  %-18s %s  %s\n' "$chemin" "$code" 'ok'
    else
        printf '  %-18s %s  %s\n' "$chemin" "$code" "$cas"
        echecs=$((echecs + 1))
    fi
done

if [ "$echecs" -gt 0 ]; then
    echo "Fumee en echec : $echecs adresse(s)."
    exit 1
fi

echo 'Fumee HTTP : tout repond, aucune erreur PHP.'
