<?php

namespace Drupal\transform_api_user\Validator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\kino_api\Enum\RegisterResultCode;
use Drupal\kino_api\Exception\BadRequestHttpException;

class RegisterValidator {

  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(TranslationInterface $stringTranslation, EntityTypeManagerInterface $entityTypeManager) {
    $this->stringTranslation = $stringTranslation;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Throws exception if mail is empty.
   */
  public function validateEmptyMail(?string $mail): void {
    if (empty($mail)) {
      $this->throwFieldIsRequiredException('mail', 'E-mail');
    }
  }

  /**
   * Throws exception if field_name is empty.
   */
  public function validateEmptyName(?string $gender): void {
    if (empty($gender)) {
      $this->throwFieldIsRequiredException('field_name', 'Name');
    }
  }

  /**
   * Throws exception if field_gender is empty.
   */
  public function validateEmptyGender(?string $gender): void {
    if (empty($gender)) {
      $this->throwFieldIsRequiredException('field_gender', 'Gender');
    }
  }

  /**
   * Throws exception if field_gender is empty.
   */
  public function validateEmptyPhoneNumber(?string $phoneNumber): void {
    if (empty($phoneNumber)) {
      $this->throwFieldIsRequiredException('field_phone_number', 'Phone number');
    }
  }

  private function throwFieldIsRequiredException(string $machineFieldName, string $humanFieldName) {
    throw new BadRequestHttpException(
      $this->t('@name field is required.', ['@name' => $this->t($humanFieldName)]),
      RegisterResultCode::$FIELD_IS_REQUIRED,
      ['field' => $machineFieldName]
    );
  }

  /**
   * Throws exception if mail already exists.
   */
  public function validateExistingMail(string $mail): void {
    $uids = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('mail', $mail)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($uids)) {
      throw new BadRequestHttpException(
        $this->t('E-mail already exists.'),
        RegisterResultCode::$EMAIL_EXISTS
      );
    }
  }

  /**
   * Throws exception if gender is not "man", "woman" or "not_specified".
   */
  public function validateGender(string $gender): void {
    $options = ['man', 'woman', 'other', 'not_specified'];
    if (!in_array($gender, $options)) {
      throw new BadRequestHttpException(
        $this->t('Gender is not recognized.'),
        RegisterResultCode::$GENDER_NOT_RECOGNIZED
      );
    }
  }

  public function validatePhoneNumber(string $field_phone_number): void {
    $countryCode = mb_substr($field_phone_number, 0, 3);
    $number = mb_substr($field_phone_number, 3);
    if ($countryCode !== "+45" || mb_strlen($number) !== 8 || !is_numeric($number)) {
      throw new BadRequestHttpException(
        $this->t('Phone number should consist of +45 and 10 digits in total.'),
        RegisterResultCode::$PHONE_NUMBER_WRONG_FORMAT
      );
    }
  }

}
