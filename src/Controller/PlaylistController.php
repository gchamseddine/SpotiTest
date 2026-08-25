<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class PlaylistController extends AbstractController
{
    #[Route('/playlist/{id}', name: 'playlist_show')]
    public function show(
        string $id,
        SessionInterface $session,
        SpotifyService $spotify
    ): Response {
        if (!$session->has('spotify_access_token')) {
            return $this->redirectToRoute('spotify_login');
        }

        try {
            $accessToken =
                $spotify->getValidAccessToken($session);

            $playlist = $spotify->getPlaylist(
                $id,
                $accessToken
            );

            $tracks = $spotify->getUsablePlaylistTracks(
                $id,
                $accessToken
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

            throw $e;
        }

        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'totalSongs' => count($tracks),
        ]);
    }
}
