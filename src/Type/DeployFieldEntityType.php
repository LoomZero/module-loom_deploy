<?php

namespace Drupal\loom_deploy\Type;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\loom_deploy\Deploy\DeployFieldTypeInterface;
use function explode;

class DeployFieldEntityType implements DeployFieldTypeInterface {

  public function types(): array {
    return ['entity'];
  }

  public function widget(array $field): array {
    return [
      '#type' => 'textfield',
      '#default_value' => $field['value']['type'] . ':' . $field['value']['id'],
    ];
  }

  public function validate(array $element, array $field, $value, FormStateInterface $form_state) {
    $value = $this->toValue($field, $value);

    $entity = Drupal::entityTypeManager()->getStorage($value['type'])->load($value['id']);
    if (empty($entity)) {
      $form_state->setError($element, 'The entity does not exist.');
    }
  }

  public function toValue(array $field, $value) {
    $parts = explode(':', $value);

    return [
      'id' => $parts[1],
      'type' => $parts[0],
    ];
  }

}
