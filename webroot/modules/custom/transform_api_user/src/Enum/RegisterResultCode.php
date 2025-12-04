<?php

namespace Drupal\transform_api_user\Enum;

class RegisterResultCode {

  public static string $NO_APPROVAL_REQUIRED = 'register_no_approval_required';

  public static string $WITHOUT_EMAIL = 'register_without_email';

  public static string $ADMIN_CREATED = 'register_admin_created';

  public static string $ADMIN_CREATED_NO_MAIL = 'register_admin_created_no_mail';

  public static string $PENDING_APPROVAL = 'register_pending_approval';

  public static string $PENDING_EMAIL_VERIFICATION = 'register_pending_email_verification';

  public static string $EMAIL_EXISTS = 'register_email_exists';

  public static string $FIELD_IS_REQUIRED = 'register_field_is_required';

  public static string $CANT_SEND_MAIL = 'register_cant_send_mail';

  public static string $GENDER_NOT_RECOGNIZED = 'register_gender_not_recognized';

  public static string $PHONE_NUMBER_WRONG_FORMAT = 'register_phone_number_wrong_format';

}
