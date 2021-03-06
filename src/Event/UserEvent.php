<?php

namespace App\Event;


use App\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class UserEvent extends Event
{

    public const SIGNUP_REQUEST = 'user.signup.request';

    public const EMAIL_CONFIRM_REQUEST = 'user.email_confirm.request';

    public const RESET_PASSWORD_REQUEST = 'user.reset_password.request';

    public const RESET_PASSWORD_CONFIRM = 'user.reset_password.confirm';

    /**
     * @var User
     */
    private $user;

    /**
     * RegistrationSuccessEvent constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     *
     * @return UserEvent
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }
}
