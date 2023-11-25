<?php

namespace App\Controller\Event;

use App\Entity\Event;
use App\Form\EventFilterType;
use App\Form\EventType;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class EventController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $attachmentFile = $form->get('attachment_filename')->getData();
            $event = $form->getData();
            if ($attachmentFile) {
                $originalFilename = pathinfo($attachmentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $attachmentFile->guessExtension();
                $event->setAttachmentFilename($newFilename);
                try {
                    $attachmentFile->move(
                        $this->getParameter('attachment_directory'),
                        $newFilename
                    );
                } catch (FileException) {
                    $this->addFlash('failure', 'Error saving file');
                    return $this->redirectToRoute('app_index');
                }
            }
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Event added ');
        }

        return $this->render('event/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/events', name: 'app_events')]
    public function events(EntityManagerInterface $entityManager, Request $request): Response
    {
        $repository = $entityManager->getRepository(Event::class);

        $form = $this->createForm(EventFilterType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData();
            $queryBuilder = $repository->createQueryBuilderByFormFilter($filters);
        } else {
            $queryBuilder = $repository->createQueryBuilder('event');
        }
        $query = $queryBuilder->getQuery();
        $event_array = $query->getScalarResult();
        $count = count($event_array);
        $events = $query->getResult();
        $participants = $repository->getParticipantsFromResult($event_array);

        return $this->render('event/events.html.twig', [
            'form' => $form,
            'events' => $events,
            'count' => $count,
            'participants' => $participants
        ]);
    }
    #[Route('/administration', name: 'app_administration')]
    public function administration(EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Event::class);
        $events = $repository->findAll();
        return $this->render('event/administration.html.twig', [
            'events' => $events
        ]);
    }
    #[Route('event/{id}', name: 'app_details')]
    public function details(Event $event): Response
    {
        return $this->render('event/details.html.twig', [
            'event' => $event
        ]);
    }
}
