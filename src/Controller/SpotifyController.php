<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SpotifyController
{
    #[Route('/api/spotify/playlists', name: 'spotify_search_playlists')]
    public function search(
        Request $request,
        SpotifyService $spotify
    ): JsonResponse {
        $query = $request->query->get('q', '');

        if ($query === '') {
            return new JsonResponse([]);
        }

        $data = $spotify->searchPlaylists($query);

        return new JsonResponse($data);
    }
}
