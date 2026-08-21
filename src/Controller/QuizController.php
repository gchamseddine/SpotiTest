<?php

namespace App\Controller;

use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        SpotifyService $spotify
    ): Response {
        if (!$session->has('spotify_access_token')) {
            return $this->redirectToRoute('spotify_login');
        }

        $accessToken = $session->get('spotify_access_token');

        $tracks = $spotify->getPlaylistTracks(
            $id,
            $accessToken
        );

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
                    'This playlist only contains %d songs.',
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

        /*
         * Randomize the playlist and select
         * only the requested number of tracks.
         */
        shuffle($tracks);

        $selectedTracks = array_slice(
            $tracks,
            0,
            $rounds
        );

        $quizTracks = [];

        foreach ($selectedTracks as $item) {
            $track = $item['item'] ?? null;

            if ($track === null) {
                continue;
            }

            $quizTracks[] = [
                'id' => $track['id'],
                'uri' => $track['uri'],
                'title' => $track['name'],
                'artists' => array_map(
                    fn ($artist) => $artist['name'],
                    $track['artists'] ?? []
                ),
            ];
        }

        // Temporary quiz state
        $session->set('quiz', [
            'playlistId' => $id,
            'rounds' => count($quizTracks),
            'clipLength' => $clipLength,
            'guessMode' => $guessMode,
            'tracks' => $quizTracks,
            'currentRound' => 0,
            'score' => 0.0,
            'results' => [],

            'currentState' => [
                'titleCorrect' => false,
                'artistCorrect' => false,
                'titleGuess' => null,
                'artistGuess' => null,
                'lastGuess' => null,
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

        $currentTrack = $quiz['tracks'][$quiz['currentRound']];

        return $this->render('quiz/play.html.twig', [
            'round' => $quiz['currentRound'] + 1,
            'totalRounds' => $quiz['rounds'],
            'clipLength' => $quiz['clipLength'],
            'guessMode' => $quiz['guessMode'],
            'trackUri' => $currentTrack['uri'],
        ]);
    }

    #[Route('/quiz/answer', name: 'quiz_answer', methods: ['POST'])]
    public function answer(
        Request $request,
        SessionInterface $session
    ): Response {
        $quiz = $session->get('quiz');

        if (!$quiz) {
            return $this->json([
                'error' => 'No quiz in progress.'
            ], 400);
        }

        $currentTrack =
            $quiz['tracks'][$quiz['currentRound']];

        $data = $request->toArray();

        $guess = trim($data['guess'] ?? '');

        $correctTitle =
            $currentTrack['title'];

        $correctArtists =
            $currentTrack['artists'];

        $normalize = function (string $value): string {

            $value = mb_strtolower(trim($value));

            // Remove featured artists in parentheses/brackets:
            // Song (feat. Artist)
            // Song (ft. Artist)
            // Song [featuring Artist]
            $value = preg_replace(
                '/\s*[\(\[]\s*(feat\.?|ft\.?|featuring)\s+.*?[\)\]]/iu',
                '',
                $value
            );

            // Remove featured artists written after a dash:
            // Song - feat. Artist
            // Song - featuring Artist
            $value = preg_replace(
                '/\s*[-–—]\s*(feat\.?|ft\.?|featuring)\s+.*$/iu',
                '',
                $value
            );

            // Ignore repeated spaces
            $value = preg_replace('/\s+/', ' ', $value);

            return trim($value);
        };

        $normalizedGuess =
            $normalize($guess);

        $titleCorrect = false;
        $artistCorrect = false;

        if (
            $quiz['guessMode'] === 'both' ||
            $quiz['guessMode'] === 'title'
        ) {
            $titleCorrect =
                $normalizedGuess ===
                $normalize($correctTitle);
        }

        if (
            $quiz['guessMode'] === 'both' ||
            $quiz['guessMode'] === 'artist'
        ) {
            foreach ($correctArtists as $artist) {

                if (
                    $normalizedGuess ===
                    $normalize($artist)
                ) {
                    $artistCorrect = true;
                    break;
                }
            }
        }

        $state = $quiz['currentState'];

        $state['lastGuess'] = $guess;


        // TITLE
        if (
            $titleCorrect &&
            !$state['titleCorrect']
        ) {
            $state['titleCorrect'] = true;
            $state['titleGuess'] = $guess;

            if ($quiz['guessMode'] === 'both') {
                $quiz['score'] += 0.5;
            }

            if ($quiz['guessMode'] === 'title') {
                $quiz['score'] += 1;
            }
        }


        // ARTIST
        if (
            $artistCorrect &&
            !$state['artistCorrect']
        ) {
            $state['artistCorrect'] = true;
            $state['artistGuess'] = $guess;

            if ($quiz['guessMode'] === 'both') {
                $quiz['score'] += 0.5;
            }

            if ($quiz['guessMode'] === 'artist') {
                $quiz['score'] += 1;
            }
        }


        $quiz['currentState'] = $state;

        $session->set('quiz', $quiz);


        return $this->json([
            'titleCorrect' => $state['titleCorrect'],
            'artistCorrect' => $state['artistCorrect'],
            'score' => $quiz['score'],
        ]);
    }

    #[Route(
        '/quiz/next',
        name: 'quiz_next',
        methods: ['POST']
    )]
    public function next(
        Request $request,
        SessionInterface $session
    ): Response {
        $quiz = $session->get('quiz');

        if (!$quiz) {
            return $this->redirectToRoute('home');
        }

        $currentTrack =
            $quiz['tracks'][$quiz['currentRound']];

        $state = $quiz['currentState'];


        // Capture whatever was still in the guess box
        // when time ran out / user gave up.
        $data = $request->toArray();

        $finalGuess =
            trim($data['lastGuess'] ?? '');

        if ($finalGuess !== '') {
            $state['lastGuess'] = $finalGuess;
        }


        $quiz['results'][] = [
            'title' => $currentTrack['title'],
            'artists' => $currentTrack['artists'],

            'titleCorrect' => $state['titleCorrect'],
            'artistCorrect' => $state['artistCorrect'],

            'titleGuess' => $state['titleGuess'],
            'artistGuess' => $state['artistGuess'],

            'lastGuess' => $state['lastGuess'],
        ];


        $quiz['currentRound']++;


        // GAME FINISHED
        if ($quiz['currentRound'] >= $quiz['rounds']) {

            $session->set('quiz_results', [
                'score' => $quiz['score'],
                'maxScore' => $quiz['rounds'],
                'guessMode' => $quiz['guessMode'],
                'results' => $quiz['results'],
            ]);

            $session->remove('quiz');

            return $this->redirectToRoute(
                'quiz_results'
            );
        }


        // Reset state for the next song
        $quiz['currentState'] = [
            'titleCorrect' => false,
            'artistCorrect' => false,
            'titleGuess' => null,
            'artistGuess' => null,
            'lastGuess' => null,
        ];

        $session->set('quiz', $quiz);

        return $this->redirectToRoute('quiz_play');
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
            return $this->redirectToRoute('home');
        }

        return $this->render(
            'quiz/results.html.twig',
            [
                'game' => $results,
            ]
        );
    }
}
