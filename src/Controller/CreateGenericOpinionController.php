<?php

namespace App\Controller;

use App\Repository\ProfessorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UniversityRepository;
use App\Repository\DegreeRepository;
use App\Repository\SubjectRepository;
use App\Entity\Opinion;
use App\Entity\University;
use App\Entity\Subject;
use App\Entity\Degree;
use App\Entity\Professor;
use Doctrine\ORM\EntityManagerInterface;

class CreateGenericOpinionController extends AbstractController
{

    private $universityRepository;
    private $degreeRepository;
    private $subjectRepository;
    private $professorRepository;
    private $entityManager;

    public function __construct(
        UniversityRepository $universityRepository,
        DegreeRepository $degreeRepository,
        SubjectRepository $subjectRepository,
        ProfessorRepository $professorRepository,
        EntityManagerInterface $entityManager
        )
    {
        $this->universityRepository = $universityRepository;
        $this->degreeRepository = $degreeRepository;
        $this->subjectRepository = $subjectRepository;
        $this->professorRepository = $professorRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/opinion/new-generic/{universityId?}/{degreeId?}/{subjectId?}', 
    name: 'app_create_generic_opinion')]
    public function createGenericOpinion(
        Request $request,
        ?int $universityId = null,
        ?int $degreeId = null,
        ?int $subjectId = null,
    ): Response
    {

        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        $session = $request->getSession();
        $referer = $session->get('referer');

        if (!$user->isVerified()) {
            if ($referer) {
                return $this->redirect($referer);
            } else {
                return $this->redirectToRoute('app_home');
            }
        }

        $university = null;
        $degree = null;
        $subject = null;

        $form = $this->createForm(\App\Form\GenericOpinionFormType::class);

        if($universityId){
            
            $university = $this->universityRepository->find($universityId);
            if (!$university) {
                $this->addFlash('error', 'Universidad no encontrada.');
                if ($referer) {
                    return $this->redirect($referer);
                } else {
                    return $this->redirectToRoute('app_home');
                }
            }

            if($degreeId){

                $degree = $this->degreeRepository->find($degreeId);
                if (!$degree) {
                    $this->addFlash('error', 'Grado no encontrado.');
                    if ($referer) {
                        return $this->redirect($referer);
                    } else {
                        return $this->redirectToRoute('app_home');
                    }
                }
                // Check if the degree is in that university
                if ($degree->getUniversity() !== $university) {
                    $this->addFlash('error', 'El grado especificado no pertenece a la universidad especificada.');
                    if ($referer) {
                        return $this->redirect($referer);
                    } else {
                        return $this->redirectToRoute('app_home');
                    }
                }

                if($subjectId){
                
                    $subject = $this->subjectRepository->find($subjectId);
                    if (!$subject) {
                        $this->addFlash('error', 'Asignatura no encontrada');
                        if ($referer) {
                            return $this->redirect($referer);
                        } else {
                            return $this->redirectToRoute('app_home');
                        }
                    }
                    // Check if the subject is in that degree
                    if ($subject->getDegree() !== $degree) {
                        $this->addFlash('error', 'Asignatura especificada no pertenece al grado especificado');
                        if ($referer) {
                            return $this->redirect($referer);
                        } else {
                            return $this->redirectToRoute('app_home');
                        }
                    }

                    $form->get('year')->setData($subject->getYear());

                }
            }
        }


        $form->handleRequest($request);

        $errors = $form->getErrors(true);
        foreach ($errors as $error) {
            dump($error->getMessage());
        }
        
        if ($form->isSubmitted() ) {
            
            $checkUniversity = $form->get('university')->getData();
            $checkDegree = $form->get('degree')->getData();
            $checkSubject = $form->get('subject')->getData();
            $checkYear = $form->get('year')->getData();

            $errors = [];

            if ($checkUniversity == null) {
                $errors['university'] = 'El campo "universidad" es obligatorio';
            }

            if ($checkDegree == null) {
                $errors['degree'] = 'El campo "grado" es obligatorio';
            }

            if ($checkSubject == null) {
                $errors['subject'] = 'El campo "asignatura" es obligatorio';
            }

            if ($checkYear == null) {
                $errors['year'] = 'El campo "año" es obligatorio';
            }

            if (count($errors) > 0) {
                // Render the form again and pass the errors
                return $this->render('opinion/new_generic.html.twig', [
                    'form' => $form,
                    'selectedUniversity' => $university,
                    'selectedDegree' => $degree,
                    'selectedSubject' => $subject,
                    'selectedYear' => $subject ? $subject->getYear():null,
                    'errors' => $errors,
                ]);

            } else {
                //no hay errores

                $obtainedUniversity = $form->get('university')->getData();
                $obtainedDegree = $form->get('degree')->getData();
                $obtainedSubject = $form->get('subject')->getData();
                $obtainedProfessor = $form->get('professor')->getData();
                $obtainedComment = $form->get('comment')->getData();
                $obtainedScore = $form->get('givenScore')->getData();
                $obtainedYear = $form->get('year')->getData();

                if(empty($obtainedProfessor)){
                    $obtainedProfessor = null;
                }

                $finalUniversity = null;
                $finalDegree = null;
                $finalSubject = null;
                $finalProfessor = null;

                // si es una universidad que existe
                if( is_numeric($obtainedUniversity)){
                    $finalUniversity = $this->universityRepository->find($obtainedUniversity);
                
                    if($finalUniversity){
                        //quizas exista el grado, quizas no

                        //si existe el grado
                        if(is_numeric($obtainedDegree)){
                            $finalDegree = $this->degreeRepository->find($obtainedDegree);

                            if($finalDegree){
                                //puede que exista asignatura puede que no

                                // si existe asignatura
                                if(is_numeric($obtainedSubject)){
                                    $finalSubject = $this->subjectRepository->find($obtainedSubject);

                                    if($finalSubject){
                                        // puede que exista profesor puede que no
                                    
                                        //primero mirar si no es null
                                        if($obtainedProfessor !== null){

                                            //si existe el profesor
                                            if(is_numeric($obtainedProfessor)){
                                                $finalProfessor = $this->professorRepository->find($obtainedProfessor);
                                            }
                                            // si no existe
                                            else{
                                                //crear el profesor
                                                $finalProfessor = new Professor();
                                                $finalProfessor->setName($obtainedProfessor);
                                            }

                                            //Asignar el profesor (Existente o no) con asignatura
                                            $finalSubject->addProfessor($finalProfessor);
                                        }
                                    }
                                    
                                }
                                // si no existe asignatura
                                else{
                                    // tampoco existirá profesor¿?¿?
                                    //un profesor puede dar varias asignaturas...

                                    //hay que crear asignatura
                                    $finalSubject = new Subject();
                                    $finalSubject->setName($obtainedSubject);
                                    $finalSubject->setYear($obtainedYear);

                                    // si el profesor no es null hay que crearlo 
                                    if($obtainedProfessor !== null){
                                        
                                        if(is_numeric($obtainedProfessor)){
                                            $finalProfessor = $this->professorRepository->find($obtainedProfessor);
                                        }
                                        else{
                                            $finalProfessor = new Professor();
                                            $finalProfessor->setName($obtainedProfessor);
                                        }
                                       
                                        //asignarlo con la asignatura
                                        $finalSubject->addProfessor($finalProfessor);

                                    }
                                    
                                }

                                // se enlaza la asignatura (existente o no) con el grado
                                $finalDegree->addSubject($finalSubject);
                            }
                            
                        }
                        //si no existe el grado
                        else{
                            // tampoco existira asignatura, profesor

                            // hay que crear el grado
                            $finalDegree = new Degree();
                            $finalDegree->setName($obtainedDegree);

                            //hay que crear asignatura 
                            $finalSubject = new Subject();
                            $finalSubject->setName($obtainedSubject);
                            $finalSubject->setYear($obtainedYear);

                            //Asignar asignatura con el grado
                            $finalDegree->addSubject($finalSubject);

                            //ver si se ha introducido profesor
                            if($obtainedProfessor !== null){

                                if(is_numeric($obtainedProfessor)){

                                    $finalProfessor = $this->professorRepository->find($obtainedProfessor);

                                }
                                else{
                                    //crear el profesor
                                    $finalProfessor = new Professor();
                                    $finalProfessor->setName($obtainedProfessor);
                                    
                                }
                                //Asignarlo con la asignatura
                                $finalSubject->addProfessor($finalProfessor);
                                
                            }
                        }
                        
                        // se asocia el grado (Existente o no) con la universidad
                        $finalUniversity->addDegree($finalDegree);
                        // $this->entityManager->persist($finalDegree);
                    }
                    
                }
                // si es una universidad que NO existe
                else{
                    // crear la universidad
                    $finalUniversity = new University();
                    $finalUniversity->setName($obtainedUniversity);

                    //el grado, asignatura, profesor tampoco existirán

                    // crear grado asignarlo con universidad
                    $finalDegree = new Degree();
                    $finalDegree->setName($obtainedDegree);
                    $finalUniversity->addDegree($finalDegree);
                    // $this->entityManager->persist($finalDegree);

                    // crear asignatura asignarla con grado
                    $finalSubject = new Subject();
                    $finalSubject->setName($obtainedSubject);
                    $finalSubject->setYear($obtainedYear);
                    $finalDegree->addSubject($finalSubject);
                    

                    // si profesor no es null, crear profesor y asignarlo con asignatura
                    if($obtainedProfessor !== null){

                        $finalProfessor = new Professor();
                        $finalProfessor->setName($obtainedProfessor);
                        $finalSubject->addProfessor($finalProfessor);

                    }
                }

                // asignar opinion con asignatura o con profesor

                $opinion = new Opinion();
                $opinion->setOwner($user);

                // si no hay profesor la opinion sera de una asignatura
                if($finalProfessor == null){
                    $opinion->setSubject($finalSubject);
                }
                else{
                    // si hay profesor la opinion sera de ese profesor
                    $opinion->setProfessor($finalProfessor);
                }

                //agregar a la opinion el comentario y el score
                $opinion->setComment($obtainedComment);
                $opinion->setGivenScore($obtainedScore);

                //persistir todo
                if($obtainedProfessor !== null){
                    $this->entityManager->persist($finalProfessor);
                }
                $this->entityManager->persist($finalSubject);
                $this->entityManager->persist($finalDegree);

                $this->entityManager->persist($finalUniversity);
                
                $this->entityManager->persist($opinion);
                // $this->entityManager->persist($f);

                $this->entityManager->flush();

                if ($referer) {
                    return $this->redirect($referer);
                } else {
                    return $this->redirectToRoute('app_home');
                }

            }

        }

        return $this->render('opinion/new_generic.html.twig', [
            'form' => $form,
            'selectedUniversity' => $university,
            'selectedDegree' => $degree,
            'selectedSubject' => $subject,
            'selectedYear' => $subject ? $subject->getYear():null,
        ]);
    }

}
