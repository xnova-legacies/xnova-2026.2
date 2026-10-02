<?php

namespace App\Core;

final class Kernel
{
    private ?string $legacyInclude = null;

    public function __construct(
        private readonly Router $router,
    ) {
    }

    public function resolve(Request $request)
    {
        $relative = ltrim($request->path(), '/');

        $route = $this->router->match($relative);
        if ($route !== null) {
            return $route;
        }

        // URLs legacy xxx.php : redirection 301 vers /game/... si un controller existe
        if (str_ends_with($relative, '.php')) {
            $target = $this->router->redirect301($relative);
            if ($target !== null) {
                $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
                header('Location: ' . $target . ($query ? '?' . $query : ''), true, 301);

                exit;
            }
        }

        return $this->resolveLegacy($relative);
    }

    public function legacyInclude(): ?string
    {
        return $this->legacyInclude;
    }

    private function resolveLegacy(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, '..')) {
            $this->notFound();

            return null;
        }

        if (str_ends_with($relative, '/')) {
            $indexFile = $relative . 'index.php';

            if (is_file(APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $indexFile))) {
                $this->legacyInclude = $indexFile;

                return $indexFile;
            }

            $this->notFound();

            return null;
        }

        if (is_file(APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            $this->legacyInclude = $relative;

            return $relative;
        }

        $this->notFound();

        return null;
    }

    /**
     * Adresse inconnue : une page du thème, sous le code 404.
     *
     * Un corps en texte brut faisait ressembler une adresse inconnue à une panne. La
     * page vit dans `app/View/OpenGame/erreur_404.tpl` (aucun balisage ici) et elle est
     * **autonome** : le routeur tourne avant le démarrage du jeu, donc ni les constantes
     * ni les fonctions legacy n'existent à ce moment-là — emprunter la coquille des pages
     * du jeu se terminait en erreur fatale (`DEFAULT_SKINPATH`).
     *
     * Les libellés viennent de `system.mo`, le seul fichier de langue chargé côté jeu
     * **et** administration — cette page se montre aux joueurs comme aux visiteurs ;
     * les textes de repli évitent une page muette si la clé manque.
     */
    private function notFound(): void
    {
        $lang = array();

        try {
            Language::include('system');
            $lang = (array) ($GLOBALS['lang'] ?? array());
        } catch (\Throwable $error) {
            // Sans libellés, les textes de repli ci-dessous prennent la main.
        }

        $title = (string) ($lang['e404_title'] ?? 'Tu t\'es perdu dans l\'espace');

        $page = TemplateEngine::render('erreur_404', array(
            'lang' => (string) ($GLOBALS['user']['lang'] ?? 'fr'),
            'theme' => 'dark',
            'e404_title' => $title,
            'e404_text' => (string) ($lang['e404_text'] ?? 'Cette adresse ne mène nulle part : la page a peut-être été déplacée, ou le module qui la portait n\'est plus installé.'),
            'e404_home' => (string) ($lang['e404_home'] ?? '/game/overview'),
            'e404_home_label' => (string) ($lang['e404_home_label'] ?? 'Retour à la vue générale'),
        ));

        Response::html($page, 404)->send();
    }
}
