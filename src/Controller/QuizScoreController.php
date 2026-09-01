<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\QuizScoreRepository;

final class QuizScoreController extends AbstractController
{
    #[Route('/scores', name: 'quiz_scores')]
    public function index(
        QuizScoreRepository $quizScoreRepository
    ): Response {
        $user = $this->getUser();

        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        $bothScores = $quizScoreRepository->findBy(
            [
                'owner' => $user,
                'guessMode' => 'both',
            ],
            [
                'score' => 'DESC',
                'playedAt' => 'DESC',
            ],
            10
        );

        $titleScores = $quizScoreRepository->findBy(
            [
                'owner' => $user,
                'guessMode' => 'title',
            ],
            [
                'score' => 'DESC',
                'playedAt' => 'DESC',
            ],
            10
        );

        $artistScores = $quizScoreRepository->findBy(
            [
                'owner' => $user,
                'guessMode' => 'artist',
            ],
            [
                'score' => 'DESC',
                'playedAt' => 'DESC',
            ],
            10
        );

        return $this->render(
            'quiz_score/index.html.twig',
            [
                'bothScores' => $bothScores,
                'titleScores' => $titleScores,
                'artistScores' => $artistScores,
            ]
        );
    }
}
