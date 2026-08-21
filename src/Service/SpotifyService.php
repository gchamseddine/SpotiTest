<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getAccessToken(): string
    {
        $clientId = $_ENV['SPOTIFY_CLIENT_ID'];
        $clientSecret = $_ENV['SPOTIFY_CLIENT_SECRET'];

        $response = $this->httpClient->request(
            'POST',
            'https://accounts.spotify.com/api/token',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                ],
            ]
        );


        $data = $response->toArray();

        return $data['access_token'];
    }

    public function searchPlaylists(string $query): array
    {
        $token = $this->getAccessToken();

        $response = $this->httpClient->request(
            'GET',
            'https://api.spotify.com/v1/search',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'q' => $query,
                    'type' => 'playlist',
                    'limit' => 10,
                ],
            ]
        );

        return $response->toArray();
    }

}
