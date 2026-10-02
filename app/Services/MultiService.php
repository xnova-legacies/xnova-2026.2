<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MultiRepository;

/**
 * Déclaration de multi-comptes (ex `MultiController`).
 *
 * Le formulaire est rendu ici pour que la page Options, dans son onglet
 * « Déclaration de multi », affiche exactement le même contenu que l'ancienne
 * page `/game/add-declare` (qui redirige désormais vers cet onglet).
 */
final class MultiService
{
    /**
     * Formulaire de déclaration. `$action` permet de poster sur la page courante
     * (onglet des options) comme sur l'ancienne adresse.
     */
    public function buildForm(string $action): string
    {
        global $lang;

        includeLang('multi');
        includeLang('system');

        $parse = array(
            'Declaration' => $lang['Declaration'] ?? '',
            'DeclarationText' => $lang['DeclarationText'] ?? '',
            'multi_send' => $lang['multi_send'] ?? '',
            'multi_action' => $action,
        );

        return parsetemplate(gettemplate('multi'), $parse);
    }

    /**
     * Enregistre une déclaration. Retourne false si le texte est vide (message
     * d'origine conservé par l'appelant).
     */
    public function declare(int $userId, string $text): bool
    {
        $text = trim($text);

        if ($text === '') {
            return false;
        }

        (new MultiRepository())->insertDeclaration($userId, $text);

        return true;
    }
}
