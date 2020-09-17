<?php

namespace Drupal\loom_deploy\Type;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\loom_deploy\Deploy\DeployFieldTypeInterface;

class DeployFieldEntityType implements DeployFieldTypeInterface {

  public function types(): array {
    return ['entity'];
  }

  public function widget(array $field, FormStateInterface $form_state): array {
    return [
      '#type' => 'textfield',
      '#default_value' => $field['value']['type'] . ':' . $field['value']['id'],
      '#description' => 'Entity pattern: <pre class="deploy-field-pre">[entity-type]:[entity-id]</pre></br>For example a node with id 12: <pre class="deploy-field-pre">node:12</pre></br>Or for a file with id 3: <pre class="deploy-field-pre">file:3</pre>',
    ];
  }

  public function validate(array $element, array $field, $value, FormStateInterface $form_state) {
    $value = $this->toValue($field, $value);

    $entity = $this->load($field, $value);
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

  public function load(array $field, $value) {
    return Drupal::entityTypeManager()->getStorage($value['type'])->load($value['id']);
  }

}
