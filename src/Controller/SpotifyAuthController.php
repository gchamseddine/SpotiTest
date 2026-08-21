<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyAuthController extends AbstractController
{
    #[Route('/auth/spotify', name: 'spotify_login')]
    public function login(SessionInterface $session): Response
    {
        $state = bin2hex(random_bytes(32));

        $session->set('spotify_oauth_state', $state);

        $params = [
            'client_id' => $_ENV['SPOTIFY_CLIENT_ID'],
            'response_type' => 'code',
            'redirect_uri' => $_ENV['SPOTIFY_REDIRECT_URI'],
            'state' => $state,

            'scope' => implode(' ', [
                'playlist-read-private',
                'playlist-read-collaborative',
                'streaming',
                'user-read-playback-state',
                'user-modify-playback-state',
            ]),
        ];

        $url = 'https://accounts.spotify.com/authorize?' .
            http_build_query($params);

        return $this->redirect($url);
    }

    #[Route('/auth/spotify/callback', name: 'spotify_callback')]
    public function callback(
        Request $request,
        SessionInterface $session,
        HttpClientInterface $httpClient
    ): Response {
        $state = $request->query->get('state');
        $expectedState = $session->get('spotify_oauth_state');

        if (
            !$state ||
            !$expectedState ||
            !hash_equals($expectedState, $state)
        ) {
            throw $this->createAccessDeniedException(
                'Invalid Spotify OAuth state.'
            );
        }

        $session->remove('spotify_oauth_state');

        $code = $request->query->get('code');

        if (!$code) {
            throw $this->createAccessDeniedException(
                'Spotify authorization failed.'
            );
        }

        $response = $httpClient->request(
            'POST',
            'https://accounts.spotify.com/api/token',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                            $_ENV['SPOTIFY_CLIENT_ID'] .
                            ':' .
                            $_ENV['SPOTIFY_CLIENT_SECRET']
                        ),
                    'Content-Type' =>
                        'application/x-www-form-urlencoded',
                ],

                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $_ENV['SPOTIFY_REDIRECT_URI'],
                ],
            ]
        );

        $tokens = $response->toArray();

        $session->set(
            'spotify_access_token',
            $tokens['access_token']
        );

        if (isset($tokens['refresh_token'])) {
            $session->set(
                'spotify_refresh_token',
                $tokens['refresh_token']
            );
        }

        $session->set(
            'spotify_token_expires_at',
            time() + $tokens['expires_in']
        );

        return $this->redirectToRoute('home');
    }

    #[Route('/auth/spotify/logout', name: 'spotify_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('spotify_access_token');
        $session->remove('spotify_refresh_token');
        $session->remove('spotify_token_expires_at');
        $session->remove('spotify_oauth_state');

        return $this->redirectToRoute('home');
    }

}
