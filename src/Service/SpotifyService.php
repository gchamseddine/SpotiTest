<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SpotifyService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getPlaylist(
        string $playlistId,
        string $accessToken
    ): array {
        $response = $this->httpClient->request(
            'GET',
            'https://api.spotify.com/v1/playlists/' . $playlistId,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        return $response->toArray();
    }

    public function getUserPlaylists(string $accessToken): array
    {
        $response = $this->httpClient->request(
            'GET',
            'https://api.spotify.com/v1/me/playlists',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'query' => [
                    'limit' => 50,
                ],
            ]
        );

        return $response->toArray();
    }

    public function getPlaylistTracks(
        string $playlistId,
        string $accessToken
    ): array {
        $allTracks = [];
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->httpClient->request(
                'GET',
                'https://api.spotify.com/v1/playlists/' . $playlistId . '/items',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                    'query' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'market' => 'from_token',
                    ],
                ]
            );

            $data = $response->toArray();

            foreach ($data['items'] ?? [] as $item) {
                if ($item !== null) {
                    $allTracks[] = $item;
                }
            }

            $offset += $limit;

        } while (!empty($data['next']));

        return $allTracks;
    }

    public function getUsablePlaylistTracks(
        string $playlistId,
        string $accessToken
    ): array {
        $items = $this->getPlaylistTracks(
            $playlistId,
            $accessToken
        );

        $usableTracks = [];

        foreach ($items as $item) {
            if (($item['is_local'] ?? false) === true) {
                continue;
            }

            $track = $item['item'] ?? null;

            if (
                !$track ||
                empty($track['id']) ||
                empty($track['uri']) ||
                empty($track['name']) ||
                ($track['is_playable'] ?? true) === false
            ) {
                continue;
            }

            $usableTracks[] = $item;
        }

        return $usableTracks;
    }

    public function getUsablePlaylistTrackCount(
        string $playlistId,
        string $accessToken
    ): int {
        $usableCount = 0;
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->httpClient->request(
                'GET',
                'https://api.spotify.com/v1/playlists/' . $playlistId . '/items',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                    'query' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'market' => 'from_token',

                        // Only request what we need for counting.
                        'fields' => 'items(is_local,item(id,is_playable)),next',
                    ],
                ]
            );

            $data = $response->toArray();

            foreach ($data['items'] ?? [] as $item) {

                if (($item['is_local'] ?? false) === true) {
                    continue;
                }

                $track = $item['item'] ?? null;

                if (
                    !$track ||
                    empty($track['id']) ||
                    ($track['is_playable'] ?? true) === false
                ) {
                    continue;
                }

                $usableCount++;
            }

            $offset += $limit;

        } while (!empty($data['next']));

        return $usableCount;
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->httpClient->request(
            'POST',
            'https://accounts.spotify.com/api/token',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                            $_ENV['SPOTIFY_CLIENT_ID']
                            . ':'
                            . $_ENV['SPOTIFY_CLIENT_SECRET']
                        ),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]
        );

        return $response->toArray();
    }

    public function getValidAccessToken(
        SessionInterface $session
    ): string {
        $accessToken = $session->get('spotify_access_token');

        $expiresAt = $session->get(
            'spotify_token_expires_at',
            0
        );

        // Still valid. The 60-second buffer prevents us
        // from using a token that's about to expire.
        if (
            $accessToken &&
            time() < $expiresAt - 60
        ) {
            return $accessToken;
        }

        $refreshToken =
            $session->get('spotify_refresh_token');

        if (!$refreshToken) {
            throw new \RuntimeException(
                'Spotify session expired.'
            );
        }

        $tokens =
            $this->refreshAccessToken($refreshToken);

        $session->set(
            'spotify_access_token',
            $tokens['access_token']
        );

        $session->set(
            'spotify_token_expires_at',
            time() + $tokens['expires_in']
        );

        /*
         * Spotify might return a new refresh token.
         * If it doesn't, keep the existing one.
         */
        if (isset($tokens['refresh_token'])) {
            $session->set(
                'spotify_refresh_token',
                $tokens['refresh_token']
            );
        }

        return $tokens['access_token'];
    }

}
