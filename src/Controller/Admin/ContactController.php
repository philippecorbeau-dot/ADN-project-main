<?php

namespace App\Controller\Admin;

use App\Entity\Mail\Contact;
use App\Entity\User\User;
use App\Repository\Mail\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/admin/modern/contacts', name: 'admin_modern_contacts_')]
#[IsGranted('ROLE_USER')]
class ContactController extends AbstractController
{
    private ContactRepository $contactRepository;
    private EntityManagerInterface $entityManager;
    
    // Rôles autorisés pour ce module
    private const ALLOWED_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_ADMIN_OPERATOR', 'ROLE_ADMIN_SUPPORT'];

    public function __construct(ContactRepository $contactRepository, EntityManagerInterface $entityManager)
    {
        $this->contactRepository = $contactRepository;
        $this->entityManager = $entityManager;
    }
    
    /**
     * Vérifie si l'utilisateur a accès au module contacts
     */
    private function checkAccess(): void
    {
        foreach (self::ALLOWED_ROLES as $role) {
            if ($this->isGranted($role)) {
                return;
            }
        }
        throw new AccessDeniedException('Vous n\'avez pas accès à ce module. Rôles requis : ' . implode(', ', self::ALLOWED_ROLES));
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $this->checkAccess();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Récupération des filtres
        $subject = $request->query->get('subject');
        $search = $request->query->get('search');

        // Construction de la requête
        $queryBuilder = $this->contactRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($subject !== null && $subject !== '') {
            $queryBuilder->andWhere('c.subject = :subject')
                        ->setParameter('subject', $subject);
        }

        if ($search) {
            $queryBuilder->andWhere('c.firstname LIKE :search OR c.lastname LIKE :search OR c.email LIKE :search OR c.message LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        // Comptage total
        $totalContacts = (clone $queryBuilder)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalContacts / $limit);

        // Récupération des contacts
        $contacts = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin_modern/contacts/index.html.twig', [
            'contacts' => $contacts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalContacts' => $totalContacts,
            'subjects' => Contact::SUBJECT_LIST,
            'currentSubject' => $subject,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Contact $contact): Response
    {
        $this->checkAccess();
        
        return $this->render('admin_modern/contacts/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/toggle-read', name: 'toggle_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleRead(Contact $contact, Request $request): Response
    {
        $this->checkAccess();
        
        if (!$this->isCsrfTokenValid('toggle_read' . $contact->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_modern_contacts_show', ['id' => $contact->getId()]);
        }
        
        $contact->setIsRead(!$contact->isRead());
        $this->entityManager->flush();
        
        $status = $contact->isRead() ? 'lu' : 'non lu';
        $this->addFlash('success', "Contact marqué comme {$status}.");
        
        return $this->redirectToRoute('admin_modern_contacts_show', ['id' => $contact->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Contact $contact, Request $request): Response
    {
        $this->checkAccess();
        
        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($contact);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Le message de contact a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_modern_contacts_index');
    }
}
