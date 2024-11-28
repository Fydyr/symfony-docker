<?php

namespace App\Controller;

use DateTimeImmutable;
use App\Entity\Article;
use App\Form\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticleController extends AbstractController
{
    #[Route('/article/creer', name: 'app_article_creer')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, #[Autowire('%kernel.project_dir%/public/uploads/brochures')] string $fileDirectory): Response
    {
        $article = new Article();

        // Crée le formulaire à partir de ArticleType
        $form = $this->createForm(ArticleType::class, $article);

        // Gère la requête (remplit le formulaire avec les données de l'utilisateur)
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originaleFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originaleFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    // Déplacement du fichier dans le répertoire défini
                    $imageFile->move($fileDirectory, $newFilename);
                    // Enregistrer le chemin relatif dans la propriété 'image' de l'article
                    $article->setImage('uploads/brochures/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                }
            }



            $entityManager->persist($article);
            $entityManager->flush();
            $this->addFlash('success', 'Article Créer');
        }

        // Rend le template avec le formulaire
        return $this->render('article/creer.html.twig', [
            'controller_name' => 'ArticleController',
            'titre' => 'Créer un article',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/article/modifier/{id}', name: 'app_article_modifier')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            throw $this->createNotFoundException('L\'article n\'a pas été trouvé');
        }

        // Créer le formulaire avec ArticleType
        $form = $this->createForm(ArticleType::class, $article);

        // Traiter la requête du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder les modifications
            $entityManager->flush();

            // Ajouter un message flash
            $this->addFlash('success', 'Article modifié avec succès !');
        }

        return $this->render('article/modifier.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/article/supprimer/{id}', name: 'app_article_supprimer')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, EntityManagerInterface $entityManager): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            throw $this->createNotFoundException('L\'article n\'a pas été trouvé');
        }

        // Supprimer l'article
        $entityManager->remove($article);
        $entityManager->flush();

        // Ajouter un message flash
        $this->addFlash('success', 'Article supprimé avec succès !');

        // Rediriger après la suppression
        return $this->redirectToRoute('app_article_liste');
    }

    #[Route('/article/liste', name: 'app_article_liste')]
    public function list(EntityManagerInterface $entityManager): Response
    {
        // Récupérer tous les articles depuis la base de données
        $articles = $entityManager->getRepository(Article::class)->findAll();

        // Afficher les articles dans la vue Twig
        return $this->render('article/liste.html.twig', [
            'articles' => $articles,
        ]);
    }
}
