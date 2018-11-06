<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserEvent;
use App\FlashMessage\UserMessage as Flash;
use App\Form\UserNewPasswordType;
use App\Form\UserRegisterType;
use App\Form\UserResetPasswordType;
use App\Security\UserLoginAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

class UserController extends AbstractController
{

    private $guardAuthenticatorHandler;

    private $userLoginAuthenticator;

    private $eventDispatcher;

    /**
     * UserController constructor.
     *
     * @param GuardAuthenticatorHandler $guardAuthenticatorHandler
     * @param UserLoginAuthenticator $userLoginAuthenticator
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
      GuardAuthenticatorHandler $guardAuthenticatorHandler,
      UserLoginAuthenticator $userLoginAuthenticator,
      EventDispatcherInterface $eventDispatcher
    ) {
        $this->guardAuthenticatorHandler = $guardAuthenticatorHandler;
        $this->userLoginAuthenticator = $userLoginAuthenticator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/register", name="user_register", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     *
     * @return Response
     */
    public function register(
      Request $request,
      UserPasswordEncoderInterface $userPasswordEncoder
    ): Response {
        $user = new User();
        $form = $this->createForm(UserRegisterType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encodedPassword = $userPasswordEncoder->encodePassword($user,
              $user->getPlainPassword());
            $user->setPassword($encodedPassword);

            $event = new UserEvent($user);
            $this->eventDispatcher->dispatch(UserEvent::REGISTER_REQUEST,
              $event);

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', Flash::REGISTRATION_CONFIRMED);

            return $this->guardAuthenticatorHandler
              ->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $this->userLoginAuthenticator,
                'main'
              );
        }

        return $this->render('user/signup.html.twig', [
          'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(
     *     "/register/confirm/{token}",
     *     name="user_email_confirm",
     *     methods={"GET"}
     * )
     *
     * @param string $token
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     */
    public function registerConfirm(
      string $token,
      Request $request
    ): Response {
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $em->getRepository(User::class)
          ->findOneByConfirmationToken($token);

        if (!$user instanceof User || !$user->isConfirmationTokenNonExpired()) {
            $this->addFlash('danger', Flash::INVALID_TOKEN);

            return $this->redirectToRoute('home');
        }

        if (!$user->isEqualTo($this->getUser())) {
            $this->addFlash('danger', Flash::EMAIL_CONFIRM_USER_DIFF);

            return $this->redirectToRoute('home');
        }

        $event = new UserEvent($user);
        $this->eventDispatcher->dispatch(UserEvent::REGISTER_CONFIRM, $event);

        $em->flush();

        $this->addFlash('success', Flash::REGISTRATION_SUCCESS);

        return $this->guardAuthenticatorHandler
          ->authenticateUserAndHandleSuccess(
            $user,
            $request,
            $this->userLoginAuthenticator,
            'main'
          );
    }

    /**
     * @Route(
     *     "/reset_password",
     *     name="user_reset_password",
     *     methods={"GET","POST"}
     * )
     *
     * @param Request $request
     *
     * @return Response
     */
    public function resetPassword(Request $request): Response
    {
        $form = $this->createForm(UserResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)
              ->findOneByEmailOrUsername($formData['login_credential']);

            if (!$user) {
                $form->addError(new FormError(Flash::RESET_PASSWORD_REQUEST_FAIL));
            } else {
                $event = new UserEvent($user);
                $this->eventDispatcher
                  ->dispatch(UserEvent::RESET_PASSWORD_REQUEST, $event);

                $em->flush();

                $this->addFlash('success',
                  Flash::RESET_PASSWORD_REQUEST_SUCCESS);

            }
        }

        return $this->render('user/reset_password.html.twig', [
          'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(
     *     "/reset_password/confirm/{token}",
     *     name="reset_password_confirm",
     *     methods={"GET", "POST"}
     * )
     *
     * @param string $token
     * @param Request $request
     *
     * @param UserPasswordEncoderInterface $encoder
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     */
    public function resetPasswordConfirm(
      string $token,
      Request $request,
      UserPasswordEncoderInterface $encoder
    ): Response {
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $em->getRepository(User::class)
          ->findOneByConfirmationToken($token);

        if (!$token || !$user instanceof User || !$user->isConfirmationTokenNonExpired()) {
            $this->addFlash('danger', Flash::INVALID_TOKEN);

            return $this->redirectToRoute('user_reset_password');
        }

        if (in_array(User::ROLE_USER_UNCONFIRMED, $user->getRoles())) {
            $this->addFlash('danger', Flash::RESET_PASSWORD_FAIL);

            return $this->redirectToRoute('user_reset_password');
        }

        $event = new UserEvent($user);
        $this->eventDispatcher
          ->dispatch(UserEvent::RESET_PASSWORD_CONFIRM, $event);

        $form = $this->createForm(UserNewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            $password = $encoder->encodePassword($user, $plainPassword);
            $user->setPassword($password);
            $em->flush();

            $this->addFlash('success', Flash::RESET_PASSWORD_SUCCESS);

            return $this->guardAuthenticatorHandler
              ->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $this->userLoginAuthenticator,
                'main'
              );
        }

        return $this->render('user/new_password.html.twig', [
          'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{username}", name="user_show", methods="GET")
     *
     * @param User $user
     *
     * @return Response
     */
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', ['user' => $user]);
    }

    /**
     * @Route(
     *     "/{username}/collections",
     *     name="user_show_game_collections",
     *     methods="GET"
     * )
     * @param \App\Entity\User $user
     *
     * @return Response
     */
    public function showGameCollections(User $user): Response
    {
        return $this->render('user/show_game_collections.html.twig', [
          'user' => $user
        ]);
    }
}
