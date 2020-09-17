<?php

namespace Drupal\loom_deploy\Manager;

use Drupal\Core\Form\FormStateInterface;
use Drupal\loom_deploy\Deploy\DeployFieldTypeInterface;
use function in_array;
use function serialize;

class DeployFormManager {

  private $types = [];

  public function addType(DeployFieldTypeInterface $type): DeployFormManager {
    $this->types[] = $type;
    return $this;
  }

  /**
   * @return DeployFieldTypeInterface[]
   */
  public function getTypes(): array {
    return $this->types;
  }

  public function getTypeClass($field_type): ?DeployFieldTypeInterface {
    foreach ($this->getTypes() as $type) {
      if (in_array($field_type, $type->types())) {
        return $type;
      }
    }
    return NULL;
  }

  public function getWidget(array $field, FormStateInterface $form_state): array {
    $type = $this->getTypeClass($field['type']);
    if ($type === NULL) return $this->getDefaultWidget($field);
    return $type->widget($field, $form_state);
  }

  public function getDefaultWidget($field): array {
    return [
      '#type' => 'textarea',
      '#title' => $field['ident'],
      '#default_value' => serialize($field['value']),
    ];
  }

}
