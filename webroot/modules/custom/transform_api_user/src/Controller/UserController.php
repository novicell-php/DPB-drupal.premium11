<?php

namespace Drupal\transform_api_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use Drupal\jwt_auth_issuer\Controller\JwtAuthIssuerController;
use Drupal\transform_api_user\Enum\RegisterResultCode;
use Drupal\transform_api_user\Exception\InternalServerErrorHttpException;
use Drupal\transform_api_user\Validator\RegisterValidator;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserController extends ControllerBase {

  private JwtAuth $auth;

  /**
   * @var \Drupal\user\UserStorageInterface
   */
  private UserStorageInterface $userStorage;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  public function __construct(JwtAuth $auth, UserStorageInterface $user_storage, LoggerInterface $logger) {
    $this->auth = $auth;
    $this->userStorage = $user_storage;
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jwt.authentication.jwt'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('logger.factory')->get('user')
    );
  }


  /**
   * Generate.
   *
   * @return string
   *   Return Hello string.
   */
  public function tokenResponse() {
    $response = new \stdClass();
    $token = $this->auth->generateToken();
    if ($token === FALSE) {
      $response->error = "Error. Please set a key in the JWT admin page.";
      return new JsonResponse($response, 500);
    }

    $response->token = $token;
    return new JsonResponse($response);
  }

  /**
   * [POST] /transform/user/register
   *
   * Registers a user.
   * See more: https://novicell.atlassian.net/l/cp/dEikWpuM.
   */
  public function register(Request $request): JsonResponse {
    $values = json_decode($request->getContent(), TRUE);

    /** @var RegisterValidator $validator */
    $validator = \Drupal::service(RegisterValidator::class);

    $validator->validateEmptyMail($values['mail']);
    $validator->validateEmptyName($values['field_name']);
    $validator->validateEmptyGender($values['field_gender']);
    $validator->validateEmptyPhoneNumber($values['field_phone_number']);

    $validator->validateExistingMail($values['mail']);
    $validator->validateGender($values['field_gender']);
    $validator->validatePhoneNumber($values['field_phone_number']);

    $account = User::create();

    // This form is used for two cases:
    // - Self-register (route = 'user.register').
    // - Admin-create (route = 'user.admin_create').
    // If the current user has permission to create users then it must be the
    // second case.
    $admin = $account->access('create');

    // Because the user status has security implications, users are blocked by
    // default when created programmatically and need to be actively activated
    // if needed. When administrators create users from the user interface,
    // however, we assume that they should be created as activated by default.
    if ($admin || \Drupal::config('user.settings')
        ->get('register') == UserInterface::REGISTER_VISITORS) {
      $account->activate();
    }

    $pass = (!\Drupal::config('user.settings')->get('verify_mail') || $admin)
      ? $values['pass']
      : \Drupal::service('password_generator')->generate();

    $values['pass'] = $pass;
    $values['init'] = $values['mail'];
    if ($admin) {
      $notify = !empty($values['notify']);
    }
    else {
      $notify = FALSE;
    }

    /** @var EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $base_fields = array_keys($entityFieldManager->getBaseFieldDefinitions('user'));
    foreach ($account->getFields() as $field_name => $field) {
      if (in_array($field_name, ['name', 'pass', 'mail', 'init', 'timezone'])) {
        if (isset($values[$field_name])) {
          $account->set($field_name, $values[$field_name]);
        }
      }
      elseif (in_array($field_name, $base_fields)) {
        continue;
      }
      else {
        if (isset($values[$field_name])) {
          $account->set($field_name, $values[$field_name]);
        }
      }
    }
    $account->setChangedTime(\Drupal::service('datetime.time')
      ->getRequestTime());

    // Set the username based on the email address and save the account.
    email_registration_user_insert($account);


    \Drupal::logger('user')
      ->notice('New user: %name %email.', [
        '%name' => $account->getAccountName(),
        '%email' => '<' . $account->getEmail() . '>',
        'type' => $account->toLink($this->t('Edit'), 'edit-form')->toString(),
      ]);

    // Add plain text password into user account to generate mail tokens.
    $account->password = $pass;

    // New administrative account without notification.
    if ($admin && !$notify) {
      return $this->createJsonResponse(
        $this->t('Created a new user account for <a href=":url">%name</a>. No email has been sent.', [
          ':url' => $account->toUrl()->toString(),
          '%name' => $account->getAccountName(),
        ]),
        RegisterResultCode::$ADMIN_CREATED_NO_MAIL
      );
    }
    // No email verification required; log in user immediately.
    elseif (!$admin && !\Drupal::config('user.settings')
        ->get('verify_mail') && $account->isActive()) {
      _user_mail_notify(RegisterResultCode::$NO_APPROVAL_REQUIRED, $account);
      user_login_finalize($account);
      return $this->createJsonResponse(
        $this->t('Registration successful. You are now logged in.'),
        RegisterResultCode::$NO_APPROVAL_REQUIRED,
      );
    }
    // No administrator approval required.
    elseif ($account->isActive() || $notify) {
      if (!$account->getEmail() && $notify) {
        return $this->createJsonResponse(
          $this->t('The new user <a href=":url">%name</a> was created without an email address, so no welcome message was sent.', [
            ':url' => $account->toUrl()->toString(),
            '%name' => $account->getAccountName(),
          ]),
          RegisterResultCode::$WITHOUT_EMAIL,
        );
      }
      else {
        $op = $notify ? RegisterResultCode::$ADMIN_CREATED : RegisterResultCode::$NO_APPROVAL_REQUIRED;
        if (_user_mail_notify($op, $account)) {
          if ($notify) {
            return $this->createJsonResponse(
              $this->t('A welcome message with further instructions has been emailed to the new user <a href=":url">%name</a>.', [
                ':url' => $account->toUrl()->toString(),
                '%name' => $account->getAccountName(),
              ]),
              RegisterResultCode::$ADMIN_CREATED);
          }
          else {
            return $this->createJsonResponse(
              $this->t('A welcome message with further instructions has been sent to your email address.'),
              RegisterResultCode::$PENDING_EMAIL_VERIFICATION
            );
          }
        }
        else {
          throw new InternalServerErrorHttpException(
            $this->t('Unable to send email. Contact the site administrator if the problem persists.'),
            RegisterResultCode::$CANT_SEND_MAIL,
          );
        }
      }
    }
    // Administrator approval required.
    else {
      _user_mail_notify(RegisterResultCode::$PENDING_APPROVAL, $account);
      return $this->createJsonResponse(
        $this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.<br />In the meantime, a welcome message with further instructions has been sent to your email address.'),
        RegisterResultCode::$PENDING_APPROVAL
      );
    }
  }

  /**
   * Create a success JsonResponse with message and result code.
   */
  private function createJsonResponse(string $message, string $resultCode): JsonResponse {
    return new JsonResponse([
      'status' => $message,
      'resultCode' => $resultCode,
    ]);
  }

  /**
   * [PUT] /api/user/{user}/edit
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\user\Entity\User $user
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function editUser(Request $request, User $user): JsonResponse {
    $values = json_decode($request->getContent(), TRUE);
    if (isset($values['field_address'])) {
      $user->set('field_address', $values['field_address']);
    }
    if (isset($values['field_alias'])) {
      $user->set('field_alias', $values['field_alias']);
    }
    if (isset($values['field_birthday'])) {
      $user->set('field_birthday', $values['field_birthday']);
    }
    if (isset($values['field_city'])) {
      $user->set('field_city', $values['field_city']);
    }
    if (isset($values['field_gender'])) {
      $user->set('field_gender', $values['field_gender']);
    }
    if (isset($values['field_name'])) {
      $user->set('field_name', $values['field_name']);
    }
    if (isset($values['field_newsletter'])) {
      $user->set('field_newsletter', $values['field_newsletter']);
    }
    if (isset($values['field_zip_code'])) {
      $user->set('field_zip_code', $values['field_zip_code']);
    }
    if (isset($values['field_phone_number'])) {
      $user->set('field_phone_number', $values['field_phone_number']);
    }
    $user->save();

    $token = $this->auth->generateToken();
    if ($token === FALSE) {
      return new JsonResponse(['error' => $this->t("Error. Please set a key in the JWT admin page.")], 500);
    }
    return new JsonResponse(['message' => $this->t('User profile has been updated.'), 'token' => $token]);
  }

  /**
   * [PUT] /api/user/{user}/changepw
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\user\Entity\User $user
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function changePassword(Request $request, User $user): JsonResponse {
    $values = json_decode($request->getContent(), TRUE);
    if (empty($values['old_password'])) {
      return new JsonResponse(['error' => $this->t("Your current password is missing or incorrect.")], Response::HTTP_BAD_REQUEST);
    }
    if (empty($values['new_password'])) {
      return new JsonResponse(['error' => $this->t('@name field is required.', ['@name' => $this->t('New password')])], Response::HTTP_BAD_REQUEST);
    }
    /** @var \Drupal\Core\Password\PasswordInterface $password_hasher */
    $passwordHasher = \Drupal::service('password');
    if ($passwordHasher->check($values['old_password'], $user->getPassword())) {
      $user->setPassword($values['new_password']);
      $user->save();
    } else {
      return new JsonResponse(['error' => $this->t("Your current password is missing or incorrect.")], Response::HTTP_BAD_REQUEST);
    }

    return new JsonResponse(['message' => $this->t('User profile has been updated.')]);
  }

  /**
   * Redirects to the user password reset form.
   *
   * In order to never disclose a reset link via a referrer header this
   * controller must always return a redirect response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param int $uid
   *   User ID of the user requesting reset.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The redirect response.
   */
  public function resetPass(Request $request, $uid, $timestamp, $hash) {
    $values = json_decode($request->getContent(), TRUE);
    if (empty($values['new_password'])) {
      return new JsonResponse(['error' => $this->t('@name field is required.', ['@name' => $this->t('New password')])], Response::HTTP_BAD_REQUEST);
    }
    $response = new \stdClass();
    $account = $this->currentUser();
    // When processing the one-time login link, we have to make sure that a user
    // isn't already logged in.
    if ($account->isAuthenticated()) {
      // The current user is already logged in.
      if ($account->id() != $uid) {
        /** @var \Drupal\user\UserInterface $reset_link_user */
        if ($reset_link_user = $this->userStorage->load($uid)) {
          $response->error = $this->t('Another user (%other_user) is already logged into the site on this computer, but you tried to use a one-time link for user %resetting_user. Please log out and try using the link again.',
              [
                '%other_user' => $account->getAccountName(),
                '%resetting_user' => $reset_link_user->getAccountName(),
              ])->__toString();
        }
        else {
          // Invalid one-time link specifies an unknown user.
          $response->error = $this->t('The one-time login link you clicked is invalid.')->__toString();
        }
        return new JsonResponse($response, Response::HTTP_BAD_REQUEST);
      }
    }

    /** @var \Drupal\user\UserInterface $reset_link_user */
    $reset_link_user = $this->userStorage->load($uid);
    if ($error = $this->determineError($reset_link_user, $timestamp, $hash)) {
      $response->error = $error;
      return new JsonResponse($response, Response::HTTP_BAD_REQUEST);
    }

    $reset_link_user->setPassword($values['new_password']);
    $reset_link_user->save();
    user_login_finalize($reset_link_user);
    $this->logger->notice('User %name used one-time login link at time %timestamp.', ['%name' => $reset_link_user->getDisplayName(), '%timestamp' => $timestamp]);
    $jwtAuthIssuerController = new JwtAuthIssuerController($this->auth);
    return $jwtAuthIssuerController->tokenResponse();
  }

  /**
   * Validates user, hash, and timestamp.
   *
   * This method allows the 'user.reset' and 'user.reset.login' routes to use
   * the same logic to check the user, timestamp and hash and redirect to the
   * same location with the same messages.
   *
   * @param \Drupal\user\UserInterface|null $user
   *   User requesting reset. NULL if the user does not exist.
   * @param int $timestamp
   *   The current timestamp.
   * @param string $hash
   *   Login link hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   Returns a redirect if the information is incorrect. It redirects to
   *   'user.pass' route with a message for the user.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If $uid is for a blocked user or invalid user ID.
   */
  protected function determineError(?UserInterface $user, int $timestamp, string $hash): ?string {
    $current = \Drupal::time()->getRequestTime();
    // Verify that the user exists and is active.
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    // No time out for first time login.
    if ($user->getLastLoginTime() && $current - $timestamp > $timeout) {
      return $this->t('You have tried to use a one-time login link that has expired. Please request a new one using the form below.')->__toString();
    }
    elseif ($user->isAuthenticated() && ($timestamp >= $user->getLastLoginTime()) && ($timestamp <= $current) && hash_equals($hash, user_pass_rehash($user, $timestamp))) {
      // The information provided is valid.
      return NULL;
    }

    return $this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.')->__toString();
  }

}
