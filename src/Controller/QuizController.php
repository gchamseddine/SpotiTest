<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class QuizController extends AbstractController
{
    #[Route(
        '/quiz/start/{id}',
        name: 'quiz_start',
        methods: ['POST']
    )]
    public function start(
        string $id,
        Request $request,
        SessionInterface $session,
        SpotifyService $spotify,
        #[Autowire(service: 'limiter.quiz_start')]
        RateLimiterFactory $quizStartLimiter
    ): Response {

        $limiter = $quizStartLimiter->create(
            $request->getClientIp() ?? 'unknown'
        );

        if (!$limiter->consume()->isAccepted()) {
            return new Response(
                'Too many quiz start attempts. Please try again shortly.',
                429
            );
        }

        if (!$session->has('spotify_refresh_token')) {
            return $this->redirectToRoute('spotify_login');
        }

        $accessToken =
            $spotify->getValidAccessToken($session);

        $playlistItems = $spotify->getUsablePlaylistTracks(
            $id,
            $accessToken
        );

        $tracks = $this->extractQuizTracks($playlistItems);

        $rounds = $request->request->getInt('rounds');
        $clipLength = $request->request->getInt('clipLength');
        $guessMode = $request->request->get('guessMode');

        // Validate number of rounds
        if ($rounds < 1) {
            $this->addFlash(
                'error',
                'You must play at least 1 round.'
            );

            return $this->redirectToRoute(
                'playlist_show',
                ['id' => $id]
            );
        }

        if ($rounds > count($tracks)) {
            $this->addFlash(
                'error',
                sprintf(
                    'This playlist only contains %d usable songs.',
                    count($tracks)
                )
            );

            return $this->redirectToRoute(
                'playlist_show',
                ['id' => $id]
            );
        }

        // Validate clip length
        if (
            $clipLength < 5 ||
            $clipLength > 30
        ) {
            $this->addFlash(
                'error',
                'Clip length must be between 5 and 30 seconds.'
            );

            return $this->redirectToRoute(
                'playlist_show',
                ['id' => $id]
            );
        }

        // Validate guess mode
        if (!in_array(
            $guessMode,
            ['both', 'artist', 'title'],
            true
        )) {
            $this->addFlash(
                'error',
                'Invalid guess mode.'
            );

            return $this->redirectToRoute(
                'playlist_show',
                ['id' => $id]
            );
        }

        shuffle($tracks);

        $selectedTracks = array_slice(
            $tracks,
            0,
            $rounds
        );

        // Clear results from any previous game
        $session->remove('quiz_results');

        $session->set('quiz', [
            'playlistId' => $id,
            'rounds' => count($selectedTracks),
            'clipLength' => $clipLength,
            'guessMode' => $guessMode,
            'tracks' => $selectedTracks,
            'currentRound' => 0,
            'score' => 0.0,
            'results' => [],

            'currentState' => [
                'titleCorrect' => false,
                'artistCorrect' => false,
            ],
        ]);

        return $this->redirectToRoute('quiz_play');
    }


    #[Route('/quiz', name: 'quiz_play')]
    public function play(
        SessionInterface $session
    ): Response {
        if (!$session->has('quiz')) {
            return $this->redirectToRoute('home');
        }

        $quiz = $session->get('quiz');

        if (
            $quiz['currentRound'] >=
            $quiz['rounds']
        ) {
            return $this->redirectToRoute(
                'quiz_results'
            );
        }

        $currentTrack =
            $quiz['tracks'][$quiz['currentRound']];

        return $this->render(
            'quiz/play.html.twig',
            [
                'round' =>
                    $quiz['currentRound'] + 1,

                'totalRounds' =>
                    $quiz['rounds'],

                'clipLength' =>
                    $quiz['clipLength'],

                'guessMode' =>
                    $quiz['guessMode'],

                // Only send the URI to the browser.
                // Do NOT send the hidden answer.
                'trackUri' =>
                    $currentTrack['uri'],
            ]
        );
    }


    #[Route(
        '/quiz/answer',
        name: 'quiz_answer',
        methods: ['POST']
    )]
    public function answer(
        Request $request,
        SessionInterface $session
    ): Response {
        $quiz = $session->get('quiz');

        if (!$quiz) {
            return $this->json([
                'error' => 'No quiz in progress.',
            ], 400);
        }

        $currentTrack =
            $quiz['tracks'][$quiz['currentRound']];

        $data = $request->toArray();

        $guess = trim(
            $data['guess'] ?? ''
        );

        $correctTitle =
            $currentTrack['title'];

        $correctArtists =
            $currentTrack['artists'];

        $normalizedGuess =
            $this->normalizeAnswer($guess);

        $titleCorrect = false;
        $artistCorrect = false;


        // Check title
        if (
            $quiz['guessMode'] === 'both' ||
            $quiz['guessMode'] === 'title'
        ) {
            $titleCorrect =
                $normalizedGuess ===
                $this->normalizeAnswer(
                    $correctTitle
                );
        }


        // Check artist
        if (
            $quiz['guessMode'] === 'both' ||
            $quiz['guessMode'] === 'artist'
        ) {
            foreach (
                $correctArtists as $artist
            ) {
                if (
                    $normalizedGuess ===
                    $this->normalizeAnswer(
                        $artist
                    )
                ) {
                    $artistCorrect = true;
                    break;
                }
            }
        }


        $state = $quiz['currentState'];


        // Award title points once
        if (
            $titleCorrect &&
            !$state['titleCorrect']
        ) {
            $state['titleCorrect'] = true;

            if (
                $quiz['guessMode'] === 'both'
            ) {
                $quiz['score'] += 0.5;
            }

            if (
                $quiz['guessMode'] === 'title'
            ) {
                $quiz['score'] += 1;
            }
        }


        // Award artist points once
        if (
            $artistCorrect &&
            !$state['artistCorrect']
        ) {
            $state['artistCorrect'] = true;

            if (
                $quiz['guessMode'] === 'both'
            ) {
                $quiz['score'] += 0.5;
            }

            if (
                $quiz['guessMode'] === 'artist'
            ) {
                $quiz['score'] += 1;
            }
        }


        $quiz['currentState'] = $state;

        $session->set('quiz', $quiz);


        return $this->json([
            'titleCorrect' =>
                $state['titleCorrect'],

            'artistCorrect' =>
                $state['artistCorrect'],

            'score' =>
                $quiz['score'],
        ]);
    }


    #[Route(
        '/quiz/next',
        name: 'quiz_next',
        methods: ['POST']
    )]
    public function next(
        SessionInterface $session
    ): Response {
        $quiz = $session->get('quiz');

        if (!$quiz) {
            return $this->json([
                'finished' => true,
                'resultsUrl' =>
                    $this->generateUrl('home'),
            ]);
        }

        $currentTrack =
            $quiz['tracks'][$quiz['currentRound']];

        $state =
            $quiz['currentState'];


        // Save the result of this round
        $quiz['results'][] = [
            'id' =>
                $currentTrack['id'],

            'title' =>
                $currentTrack['title'],

            'artists' =>
                $currentTrack['artists'],

            'titleCorrect' =>
                $state['titleCorrect'],

            'artistCorrect' =>
                $state['artistCorrect'],
        ];


        $quiz['currentRound']++;


        // Game finished
        if (
            $quiz['currentRound'] >=
            $quiz['rounds']
        ) {
            $session->set(
                'quiz_results',
                [
                    'playlistId' =>
                        $quiz['playlistId'],

                    'rounds' =>
                        $quiz['rounds'],

                    'clipLength' =>
                        $quiz['clipLength'],

                    'guessMode' =>
                        $quiz['guessMode'],

                    'score' =>
                        $quiz['score'],

                    'maxScore' =>
                        $quiz['rounds'],

                    'results' =>
                        $quiz['results'],
                ]
            );

            $session->remove('quiz');

            return $this->json([
                'finished' => true,

                'resultsUrl' =>
                    $this->generateUrl(
                        'quiz_results'
                    ),
            ]);
        }


        // Reset state for next song
        $quiz['currentState'] = [
            'titleCorrect' => false,
            'artistCorrect' => false,
        ];

        $session->set('quiz', $quiz);


        $nextTrack =
            $quiz['tracks'][$quiz['currentRound']];


        // The same quiz page stays loaded.
        // JS receives only the next track URI.
        return $this->json([
            'finished' => false,

            'round' =>
                $quiz['currentRound'] + 1,

            'trackUri' =>
                $nextTrack['uri'],
        ]);
    }


    #[Route(
        '/quiz/results',
        name: 'quiz_results'
    )]
    public function results(
        SessionInterface $session
    ): Response {
        $results =
            $session->get('quiz_results');

        if (!$results) {
            return $this->redirectToRoute(
                'home'
            );
        }

        return $this->render(
            'quiz/results.html.twig',
            [
                'game' => $results,
            ]
        );
    }


    #[Route(
        '/quiz/quit',
        name: 'quiz_quit',
        methods: ['POST']
    )]
    public function quit(
        SessionInterface $session
    ): Response {
        $quiz = $session->get('quiz');

        if (!$quiz) {
            return $this->json([
                'url' =>
                    $this->generateUrl('home'),
            ]);
        }

        $playlistId =
            $quiz['playlistId'];

        $session->remove('quiz');

        return $this->json([
            'url' =>
                $this->generateUrl(
                    'playlist_show',
                    ['id' => $playlistId]
                ),
        ]);
    }


    #[Route(
        '/quiz/retry',
        name: 'quiz_retry',
        methods: ['POST']
    )]
    public function retry(
        SessionInterface $session,
        SpotifyService $spotify
    ): Response {
        $results =
            $session->get('quiz_results');

        if (!$results) {
            return $this->redirectToRoute(
                'home'
            );
        }

        if (
            !$session->has(
                'spotify_refresh_token'
            )
        ) {
            return $this->redirectToRoute(
                'spotify_login'
            );
        }

        $accessToken =
            $spotify->getValidAccessToken(
                $session
            );

        $playlistItems =
            $spotify->getUsablePlaylistTracks(
                $results['playlistId'],
                $accessToken
            );

        $tracks =
            $this->extractQuizTracks(
                $playlistItems
            );


        /*
         * Try to avoid songs from the previous game.
         * If there aren't enough alternatives,
         * fall back to the entire playlist.
         */
        $previousIds = array_column(
            $results['results'],
            'id'
        );

        $newTracks = array_values(
            array_filter(
                $tracks,
                fn (array $track) =>
                !in_array(
                    $track['id'],
                    $previousIds,
                    true
                )
            )
        );

        if (
            count($newTracks) >=
            $results['rounds']
        ) {
            $tracks = $newTracks;
        }

        shuffle($tracks);

        $selectedTracks = array_slice(
            $tracks,
            0,
            $results['rounds']
        );


        $session->set('quiz', [
            'playlistId' =>
                $results['playlistId'],

            'rounds' =>
                count($selectedTracks),

            'clipLength' =>
                $results['clipLength'],

            'guessMode' =>
                $results['guessMode'],

            'tracks' =>
                $selectedTracks,

            'currentRound' => 0,

            'score' => 0.0,

            'results' => [],

            'currentState' => [
                'titleCorrect' => false,
                'artistCorrect' => false,
            ],
        ]);

        $session->remove('quiz_results');

        return $this->redirectToRoute(
            'quiz_play'
        );
    }


    /**
     * Convert Spotify playlist items into the
     * small structure SpotiTest actually needs.
     */
    private function extractQuizTracks(
        array $playlistItems
    ): array {
        $tracks = [];

        foreach ($playlistItems as $item) {

            $track =
                $item['item'] ?? null;

            if (
                !$track ||
                empty($track['id']) ||
                empty($track['uri']) ||
                empty($track['name']) ||
                ($track['is_playable'] ?? true) === false
            ) {
                continue;
            }

            $tracks[] = [
                'id' => $track['id'],
                'uri' => $track['uri'],
                'title' => $track['name'],

                'artists' => array_map(
                    fn (array $artist) =>
                    $artist['name'],

                    $track['artists'] ?? []
                ),
            ];
        }

        return $tracks;
    }


    /**
     * Normalize an answer before comparing it.
     */
    private function normalizeAnswer(
        string $value
    ): string {
        $value =
            mb_strtolower(trim($value));


        // "Song (feat. Artist)"
        // "Song [ft. Artist]"
        $value = preg_replace(
            '/\s*[\(\[]\s*(feat\.?|ft\.?|featuring)\s+.*?[\)\]]/iu',
            '',
            $value
        );


        // "Song - feat. Artist"
        $value = preg_replace(
            '/\s*[-–—]\s*(feat\.?|ft\.?|featuring)\s+.*$/iu',
            '',
            $value
        );

        // Remove artist additions after "+".
        // Example: "Stateside + Zara Larsson" -> "Stateside"
        $value = preg_replace(
            '/\s+\+\s+.*$/u',
            '',
            $value
        );

        // Ignore punctuation.
        // Example: "Joyride." -> "Joyride"
        $value = preg_replace(
            '/[^\p{L}\p{N}\s]/u',
            '',
            $value
        );

        // Normalize repeated spaces.
        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim($value);
    }
}
