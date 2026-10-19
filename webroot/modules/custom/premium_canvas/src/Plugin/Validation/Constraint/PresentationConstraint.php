<?php

declare(strict_types=1);

namespace Drupal\premium_canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'PremiumCanvasPresentation',
  label: new TranslatableMarkup('Published Canvas alternative', [], ['context' => 'Validation']),
)]
final class PresentationConstraint extends SymfonyConstraint {

  public string $message = 'Publish a Canvas alternative with content before selecting Canvas.';

}
