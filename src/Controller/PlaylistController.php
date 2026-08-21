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

        $accessToken = $session->get('spotify_access_token');

        $playlist = $spotify->getPlaylist(
            $id,
            $accessToken
        );

        $tracks = $spotify->getPlaylistTracks(
            $id,
            $accessToken
        );

        return $this->render('playlist/show.html.twig', [
            'playlist' => $playlist,
            'totalSongs' => count($tracks),
        ]);
    }
}
