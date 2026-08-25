<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        SessionInterface $session,
        SpotifyService $spotify
    ): Response {
        $spotifyConnected =
            $session->has('spotify_access_token');

        $playlists = [];

        if ($spotifyConnected) {
            try {
                $accessToken =
                    $spotify->getValidAccessToken($session);

                $cacheId =
                    $session->get('spotify_cache_id');

                $cacheKey =
                    'spotify_playlists_' . $cacheId;

                $data =
                    $spotify->getUserPlaylists(
                        $accessToken,
                        $cacheKey
                    );

                $playlists = array_filter(
                    $data['items'] ?? []
                );

            } catch (\RuntimeException $e) {

                if (
                    $e->getMessage() ===
                    'Spotify authorization expired.'
                ) {
                    $session->invalidate();

                    $this->addFlash(
                        'error',
                        'Your Spotify connection expired. Please connect Spotify again.'
                    );

                    return $this->redirectToRoute('home');
                }

                // Other errors, such as Spotify's rate limit.
                $this->addFlash(
                    'error',
                    $e->getMessage()
                );
            }
        }

        return $this->render('home/index.html.twig', [
            'spotifyConnected' => $spotifyConnected,
            'userPlaylists' => $playlists,
        ]);
    }
}
