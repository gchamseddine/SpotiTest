<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlaylistController extends AbstractController
{
    #[Route('/playlist/{id}', name: 'playlist_show')]
    public function show(string $id): Response
    {
        return $this->render('playlist/show.html.twig', [
            'playlistId' => $id,
        ]);
    }
}
