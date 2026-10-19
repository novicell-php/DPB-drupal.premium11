<?php

declare(strict_types=1);

namespace Drupal\premium_canvas\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\premium_canvas\PagePresentation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PresentationConstraintValidator extends ConstraintValidator {

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof FieldItemListInterface || $value->value !== 'canvas') {
      return;
    }
    $node = $value->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }
    $page = PagePresentation::alternative($node);
    if (!$page || !$page->isPublished() || $page->getComponentTree()->isEmpty()) {
      $this->context->buildViolation($constraint->message)->atPath('0.value')->addViolation();
    }
  }

}
